<?php
session_start();
require_once 'config.php';

// Admin kontrolü
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Kategori ID'sini al
$category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;

if (!$category_id) {
    $_SESSION['error'] = "Geçersiz kategori ID'si.";
    header("Location: categories.php");
    exit();
}

// Kategori bilgilerini al
try {
    $stmt = $db->prepare("
        SELECT c.*, p.name as parent_name, pp.name as grandparent_name
        FROM categories c
        LEFT JOIN categories p ON c.parent_id = p.id
        LEFT JOIN categories pp ON p.parent_id = pp.id
        WHERE c.id = ?
    ");
    $stmt->execute([$category_id]);
    $category = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$category) {
        $_SESSION['error'] = "Kategori bulunamadı.";
        header("Location: categories.php");
        exit();
    }
} catch (PDOException $e) {
    $_SESSION['error'] = "Veritabanı hatası: " . $e->getMessage();
    header("Location: categories.php");
    exit();
}

// Konu silme işlemi
if (isset($_POST['delete_topic'])) {
    $topic_id = $_POST['topic_id'];
    
    try {
        // Önce bu konuya ait soruları kontrol et
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM questions WHERE topic_id = ?");
        $stmt->execute([$topic_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['count'] > 0) {
            $_SESSION['error'] = "Bu konuya ait sorular bulunmaktadır. Önce ilgili soruları silmelisiniz.";
        } else {
            // Konuyu sil
            $stmt = $db->prepare("DELETE FROM topics WHERE id = ?");
            if ($stmt->execute([$topic_id])) {
                $_SESSION['success'] = "Konu başarıyla silindi.";
            } else {
                $_SESSION['error'] = "Konu silinirken bir hata oluştu.";
            }
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Veritabanı hatası: " . $e->getMessage();
    }
    
    header("Location: topics.php?category_id=" . $_GET['category_id']);
    exit();
}

// Konu durumunu güncelleme
if (isset($_POST['toggle_status'])) {
    $topic_id = $_POST['topic_id'];
    $new_status = $_POST['new_status'];
    
    $stmt = $db->prepare("UPDATE topics SET status = ? WHERE id = ?");
    if ($stmt->execute([$new_status, $topic_id])) {
        $_SESSION['success'] = "Konu durumu güncellendi.";
    } else {
        $_SESSION['error'] = "Konu durumu güncellenirken bir hata oluştu.";
    }
    header("Location: topics.php?category_id=" . $category_id);
    exit();
}

// Yeni konu ekleme
if (isset($_POST['add_topic'])) {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $status = isset($_POST['status']) ? 1 : 0;

    if (empty($name)) {
        $_SESSION['error'] = "Konu adı boş bırakılamaz.";
    } else {
        $stmt = $db->prepare("INSERT INTO topics (category_id, name, description, status) VALUES (?, ?, ?, ?)");
        if ($stmt->execute([$category_id, $name, $description, $status])) {
            $_SESSION['success'] = "Yeni konu başarıyla eklendi.";
        } else {
            $_SESSION['error'] = "Konu eklenirken bir hata oluştu.";
        }
    }
    header("Location: topics.php?category_id=" . $category_id);
    exit();
}

// Konu düzenleme
if (isset($_POST['edit_topic'])) {
    $topic_id = $_POST['topic_id'];
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $status = isset($_POST['status']) ? 1 : 0;

    if (empty($name)) {
        $_SESSION['error'] = "Konu adı boş bırakılamaz.";
    } else {
        $stmt = $db->prepare("UPDATE topics SET name = ?, description = ?, status = ? WHERE id = ?");
        if ($stmt->execute([$name, $description, $status, $topic_id])) {
            $_SESSION['success'] = "Konu başarıyla güncellendi.";
        } else {
            $_SESSION['error'] = "Konu güncellenirken bir hata oluştu.";
        }
    }
    header("Location: topics.php?category_id=" . $category_id);
    exit();
}

// Konuları çek
try {
    $stmt = $db->prepare("
        SELECT t.*, 
               (SELECT COUNT(*) FROM questions WHERE topic_id = t.id) as question_count
        FROM topics t
        WHERE t.category_id = ?
        ORDER BY t.name ASC
    ");
    $stmt->execute([$category_id]);
    $topics = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error'] = "Veritabanı hatası: " . $e->getMessage();
    $topics = [];
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konu Yönetimi - <?php echo htmlspecialchars($category['name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        .topic-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            transition: transform 0.2s;
        }
        
        .topic-card:hover {
            transform: translateY(-5px);
        }
        
        .topic-header {
            padding: 20px;
            border-bottom: 1px solid #e3e6f0;
        }
        
        .topic-body {
            padding: 20px;
        }
        
        .topic-stats {
            display: flex;
            justify-content: space-around;
            margin-bottom: 15px;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-value {
            font-size: 1.5rem;
            font-weight: bold;
            color: #4e73df;
        }
        
        .stat-label {
            font-size: 0.8rem;
            color: #858796;
        }
        
        .topic-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        .badge {
            padding: 6px 12px;
            font-weight: 500;
        }

        .breadcrumb-item {
            display: flex;
            align-items: center;
        }

        .breadcrumb-item i {
            margin-right: 5px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include 'sidebar.php'; ?>

            <!-- Ana İçerik -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="content-container mt-4">
                    <!-- Breadcrumb -->
                    <nav aria-label="breadcrumb" class="mb-4">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item">
                                <a href="categories.php" class="text-decoration-none">
                                    <i class="bi bi-grid"></i>Kategoriler
                                </a>
                            </li>
                            <?php if (isset($category['grandparent_name'])): ?>
                                <li class="breadcrumb-item">
                                    <?php echo htmlspecialchars($category['grandparent_name']); ?>
                                </li>
                            <?php endif; ?>
                            <?php if (isset($category['parent_name'])): ?>
                                <li class="breadcrumb-item">
                                    <?php echo htmlspecialchars($category['parent_name']); ?>
                                </li>
                            <?php endif; ?>
                            <li class="breadcrumb-item active">
                                <?php echo htmlspecialchars($category['name']); ?>
                            </li>
                        </ol>
                    </nav>

                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2 class="h3">
                            <?php echo htmlspecialchars($category['name']); ?> - Konular
                        </h2>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTopicModal">
                            <i class="bi bi-plus-lg"></i> Yeni Konu
                        </button>
                    </div>

                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?php 
                            echo $_SESSION['success'];
                            unset($_SESSION['success']);
                            ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php 
                            echo $_SESSION['error'];
                            unset($_SESSION['error']);
                            ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <div class="row">
                        <?php foreach ($topics as $topic): ?>
                            <div class="col-md-6 col-lg-4">
                                <div class="topic-card">
                                    <div class="topic-header">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <h5 class="mb-0">
                                                <?php echo htmlspecialchars($topic['name']); ?>
                                            </h5>
                                            <span class="badge <?php echo $topic['status'] ? 'bg-success' : 'bg-danger'; ?>">
                                                <?php echo $topic['status'] ? 'Aktif' : 'Pasif'; ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="topic-body">
                                        <?php if ($topic['description']): ?>
                                            <p class="mb-3"><?php echo nl2br(htmlspecialchars($topic['description'])); ?></p>
                                        <?php endif; ?>
                                        
                                        <div class="topic-stats">
                                            <div class="stat-item">
                                                <div class="stat-value"><?php echo $topic['question_count']; ?></div>
                                                <div class="stat-label">Soru</div>
                                            </div>
                                        </div>

                                        <div class="topic-actions">
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="topic_id" value="<?php echo $topic['id']; ?>">
                                                <input type="hidden" name="new_status" value="<?php echo $topic['status'] ? '0' : '1'; ?>">
                                                <button type="submit" name="toggle_status" class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-toggle-<?php echo $topic['status'] ? 'on' : 'off'; ?>"></i>
                                                </button>
                                            </form>
                                            
                                            <button type="button" class="btn btn-sm btn-outline-warning" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#editTopicModal"
                                                    data-topic='<?php echo json_encode($topic); ?>'>
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="topic_id" value="<?php echo $topic['id']; ?>">
                                                <button type="submit" name="delete_topic" 
                                                        class="btn btn-sm btn-outline-danger"
                                                        onclick="return confirm('Bu konuyu silmek istediğinizden emin misiniz?');">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Yeni Konu Ekleme Modalı -->
    <div class="modal fade" id="addTopicModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Yeni Konu Ekle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="addTopicForm">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="name" class="form-label">Konu Adı</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Açıklama</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="status" name="status" checked>
                                <label class="form-check-label" for="status">Aktif</label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" name="add_topic" class="btn btn-primary">Ekle</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Konu Düzenleme Modalı -->
    <div class="modal fade" id="editTopicModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Konu Düzenle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="editTopicForm">
                    <input type="hidden" name="topic_id" id="edit_topic_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="edit_name" class="form-label">Konu Adı</label>
                            <input type="text" class="form-control" id="edit_name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_description" class="form-label">Açıklama</label>
                            <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="edit_status" name="status">
                                <label class="form-check-label" for="edit_status">Aktif</label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" name="edit_topic" class="btn btn-primary">Kaydet</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    $(document).ready(function() {
        // Düzenleme modalını açma
        $('.btn-outline-warning').click(function() {
            const topicData = $(this).data('topic');
            $('#edit_topic_id').val(topicData.id);
            $('#edit_name').val(topicData.name);
            $('#edit_description').val(topicData.description);
            $('#edit_status').prop('checked', topicData.status == 1);
        });

        // Form validasyonları
        $('#addTopicForm, #editTopicForm').submit(function(e) {
            const nameInput = $(this).find('input[name="name"]');
            if (!nameInput.val().trim()) {
                alert('Konu adı boş bırakılamaz!');
                e.preventDefault();
            }
        });
    });
    </script>
</body>
</html>