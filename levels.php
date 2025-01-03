<?php
session_start();
require_once 'config.php';

// Admin kontrolü
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Seviye silme işlemi
if (isset($_POST['delete_level'])) {
    $level_id = $_POST['level_id'];
    
    // Önce bu seviyeye ait sınavları kontrol et
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM exam_categories WHERE level_id = ?");
    $stmt->execute([$level_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['count'] > 0) {
        $_SESSION['error'] = "Bu seviyeye ait sınavlar bulunmaktadır. Önce ilgili sınavları silmelisiniz.";
    } else {
        // Seviyeyi sil
        $stmt = $db->prepare("DELETE FROM levels WHERE id = ?");
        if ($stmt->execute([$level_id])) {
            $_SESSION['success'] = "Seviye başarıyla silindi.";
        } else {
            $_SESSION['error'] = "Seviye silinirken bir hata oluştu.";
        }
    }
    header("Location: levels.php");
    exit();
}

// Seviye durumunu güncelleme
if (isset($_POST['toggle_status'])) {
    $level_id = $_POST['level_id'];
    $new_status = $_POST['new_status'];
    
    $stmt = $db->prepare("UPDATE levels SET status = ? WHERE id = ?");
    if ($stmt->execute([$new_status, $level_id])) {
        $_SESSION['success'] = "Seviye durumu güncellendi.";
    } else {
        $_SESSION['error'] = "Seviye durumu güncellenirken bir hata oluştu.";
    }
    header("Location: levels.php");
    exit();
}

// Yeni seviye ekleme
if (isset($_POST['add_level'])) {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $point = (int)$_POST['point'];
    $status = isset($_POST['status']) ? 1 : 0;

    if (empty($name)) {
        $_SESSION['error'] = "Seviye adı boş bırakılamaz.";
    } else {
        $stmt = $db->prepare("INSERT INTO levels (name, description, point, status) VALUES (?, ?, ?, ?)");
        if ($stmt->execute([$name, $description, $point, $status])) {
            $_SESSION['success'] = "Yeni seviye başarıyla eklendi.";
        } else {
            $_SESSION['error'] = "Seviye eklenirken bir hata oluştu.";
        }
    }
    header("Location: levels.php");
    exit();
}

// Seviye düzenleme
if (isset($_POST['edit_level'])) {
    $level_id = $_POST['level_id'];
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $point = (int)$_POST['point'];
    $status = isset($_POST['status']) ? 1 : 0;

    if (empty($name)) {
        $_SESSION['error'] = "Seviye adı boş bırakılamaz.";
    } else {
        $stmt = $db->prepare("UPDATE levels SET name = ?, description = ?, point = ?, status = ? WHERE id = ?");
        if ($stmt->execute([$name, $description, $point, $status, $level_id])) {
            $_SESSION['success'] = "Seviye başarıyla güncellendi.";
        } else {
            $_SESSION['error'] = "Seviye güncellenirken bir hata oluştu.";
        }
    }
    header("Location: levels.php");
    exit();
}

// Tüm seviyeleri ve ilgili istatistikleri çek
$stmt = $db->query("
    SELECT 
        l.*,
        COUNT(DISTINCT ec.exam_id) as exam_count,
        COUNT(DISTINCT e.id) as active_exam_count
    FROM levels l
    LEFT JOIN exam_categories ec ON l.id = ec.level_id
    LEFT JOIN exams e ON ec.exam_id = e.id AND e.status = 'active'
    GROUP BY l.id
    ORDER BY l.name ASC
");
$levels = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seviye Yönetimi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        .level-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            transition: transform 0.2s;
        }
        
        .level-card:hover {
            transform: translateY(-5px);
        }
        
        .level-header {
            padding: 20px;
            border-bottom: 1px solid #e3e6f0;
        }
        
        .level-body {
            padding: 20px;
        }
        
        .level-stats {
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
        
        .level-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        .status-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
        }
        
        .status-active {
            background: #1cc88a20;
            color: #1cc88a;
        }
        
        .status-inactive {
            background: #e74a3b20;
            color: #e74a3b;
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
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2 class="h3">Seviye Yönetimi</h2>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addLevelModal">
                            <i class="bi bi-plus-lg"></i> Yeni Seviye
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
                        <?php foreach ($levels as $level): ?>
                            <div class="col-md-6 col-lg-4">
                                <div class="level-card">
                                    <div class="level-header">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <h5 class="mb-0">
                                                <?php echo htmlspecialchars($level['name']); ?>
                                            </h5>
                                            <span class="status-badge <?php echo $level['status'] ? 'status-active' : 'status-inactive'; ?>">
                                                <?php echo $level['status'] ? 'Aktif' : 'Pasif'; ?>
                                            </span>
                                        </div>
                                        <?php if ($level['description']): ?>
                                            <p class="text-muted small mb-0 mt-2">
                                                <?php echo htmlspecialchars($level['description']); ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="level-body">
                                        <div class="level-stats">
                                            <div class="stat-item">
                                                <div class="stat-value"><?php echo $level['point']; ?></div>
                                                <div class="stat-label">Puan</div>
                                            </div>
                                            <div class="stat-item">
                                                <div class="stat-value"><?php echo $level['exam_count']; ?></div>
                                                <div class="stat-label">Toplam Sınav</div>
                                            </div>
                                            <div class="stat-item">
                                                <div class="stat-value"><?php echo $level['active_exam_count']; ?></div>
                                                <div class="stat-label">Aktif Sınav</div>
                                            </div>
                                        </div>
                                        <div class="level-actions">
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="level_id" value="<?php echo $level['id']; ?>">
                                                <input type="hidden" name="new_status" value="<?php echo $level['status'] ? '0' : '1'; ?>">
                                                <button type="submit" name="toggle_status" class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-toggle-<?php echo $level['status'] ? 'on' : 'off'; ?>"></i>
                                                    <?php echo $level['status'] ? 'Pasife Al' : 'Aktif Et'; ?>
                                                </button>
                                            </form>
                                            <button type="button" class="btn btn-sm btn-outline-warning" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#editLevelModal" 
                                                    data-level='<?php echo json_encode($level); ?>'>
                                                <i class="bi bi-pencil"></i> Düzenle
                                            </button>
                                            <form method="POST" class="d-inline" name="delete_form">
                                                <input type="hidden" name="level_id" value="<?php echo $level['id']; ?>">
                                                <button type="submit" name="delete_level" class="btn btn-sm btn-outline-danger">
                                                    <i class="bi bi-trash"></i> Sil
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

    <!-- Yeni Seviye Ekleme Modalı -->
    <div class="modal fade" id="addLevelModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Yeni Seviye Ekle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="addLevelForm">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="name" class="form-label">Seviye Adı</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="point" class="form-label">Puan Değeri</label>
                            <input type="number" class="form-control" id="point" name="point" required min="0" value="0">
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
                        <button type="submit" name="add_level" class="btn btn-primary">Ekle</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Seviye Düzenleme Modalı -->
    <div class="modal fade" id="editLevelModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Seviye Düzenle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="editLevelForm">
                    <input type="hidden" name="level_id" id="edit_level_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="edit_name" class="form-label">Seviye Adı</label>
                            <input type="text" class="form-control" id="edit_name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_point" class="form-label">Puan Değeri</label>
                            <input type="number" class="form-control" id="edit_point" name="point" required min="0">
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
                        <button type="submit" name="edit_level" class="btn btn-primary">Kaydet</button>
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
            const levelData = $(this).data('level');
            $('#edit_level_id').val(levelData.id);
            $('#edit_name').val(levelData.name);
            $('#edit_point').val(levelData.point);
            $('#edit_description').val(levelData.description);
            $('#edit_status').prop('checked', levelData.status == 1);
        });

        // Silme işlemi onayı
        $('form[name="delete_form"]').submit(function(e) {
            if (!confirm('Bu seviyeyi silmek istediğinizden emin misiniz?')) {
                e.preventDefault();
            }
        });

        // Form validasyonları
        $('#addLevelForm, #editLevelForm').submit(function(e) {
            const nameInput = $(this).find('input[name="name"]');
            const pointInput = $(this).find('input[name="point"]');
            
            if (!nameInput.val().trim()) {
                alert('Seviye adı boş bırakılamaz!');
                e.preventDefault();
                return;
            }
            
            if (pointInput.val() < 0) {
                alert('Puan değeri 0\'dan küçük olamaz!');
                e.preventDefault();
                return;
            }
        });
    });
    </script>
</body>
</html>