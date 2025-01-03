<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'config.php';

// Admin kontrolü
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Toplu silme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_questions'])) {
    if (isset($_POST['question_ids']) && is_array($_POST['question_ids'])) {
        try {
            $ids = array_map('intval', $_POST['question_ids']);
            $placeholders = str_repeat('?,', count($ids) - 1) . '?';
            
            // Önce seçenekleri sil
            $stmt = $db->prepare("DELETE FROM question_options WHERE question_id IN ($placeholders)");
            $stmt->execute($ids);
            
            // Sonra soruları sil
            $stmt = $db->prepare("DELETE FROM questions WHERE id IN ($placeholders)");
            $stmt->execute($ids);
            
            header("Location: " . $_SERVER['PHP_SELF'] . "?deleted=true");
            exit;
        } catch (PDOException $e) {
            error_log("Silme hatası: " . $e->getMessage());
            header("Location: " . $_SERVER['PHP_SELF'] . "?error=delete");
            exit;
        }
    }
}

try {
    // Filtreleme parametreleri
    $filters = [
        'main_category_id' => isset($_GET['main_category_id']) ? (int)$_GET['main_category_id'] : null,
        'sub_category_id' => isset($_GET['sub_category_id']) ? (int)$_GET['sub_category_id'] : null,
        'sub_sub_category_id' => isset($_GET['sub_sub_category_id']) ? (int)$_GET['sub_sub_category_id'] : null,
        'topic_id' => isset($_GET['topic_id']) ? (int)$_GET['topic_id'] : null,
        'level_id' => isset($_GET['level_id']) ? (int)$_GET['level_id'] : null,
        'search' => isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '',
        'page' => isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1
    ];

    $per_page = 20;

    // Ana kategorileri çek
    $main_categories = $db->query("
        SELECT * FROM categories 
        WHERE parent_id IS NULL AND status = 1 
        ORDER BY name ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Alt kategorileri çek
    $sub_categories = [];
    if ($filters['main_category_id']) {
        $stmt = $db->prepare("
            SELECT * FROM categories 
            WHERE parent_id = ? AND status = 1 
            ORDER BY name ASC
        ");
        $stmt->execute([$filters['main_category_id']]);
        $sub_categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Alt-alt kategorileri çek
    $sub_sub_categories = [];
    if ($filters['sub_category_id']) {
        $stmt = $db->prepare("
            SELECT * FROM categories 
            WHERE parent_id = ? AND status = 1 
            ORDER BY name ASC
        ");
        $stmt->execute([$filters['sub_category_id']]);
        $sub_sub_categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Konuları çek
    $topics = [];
    if ($filters['sub_sub_category_id']) {
        $stmt = $db->prepare("
            SELECT * FROM topics 
            WHERE category_id = ? AND status = 1 
            ORDER BY name ASC
        ");
        $stmt->execute([$filters['sub_sub_category_id']]);
        $topics = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Seviyeleri çek
    $levels = $db->query("
        SELECT * FROM levels 
        WHERE status = 1 
        ORDER BY display_order ASC, name ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
	    // Soru filtreleme koşullarını oluştur
    $where = ['1=1'];
    $params = [];

    // Final kategori ID'sini belirle
    $final_category_id = $filters['sub_sub_category_id'] ?? 
                        $filters['sub_category_id'] ?? 
                        $filters['main_category_id'];

    if ($final_category_id) {
        $where[] = "q.category_id = :category_id";
        $params[':category_id'] = $final_category_id;
    }

    if ($filters['topic_id']) {
        $where[] = "q.topic_id = :topic_id";
        $params[':topic_id'] = $filters['topic_id'];
    }

    if ($filters['level_id']) {
        $where[] = "q.level_id = :level_id";
        $params[':level_id'] = $filters['level_id'];
    }

    if ($filters['search']) {
        $where[] = "(q.question_text LIKE :search OR q.tags LIKE :search_tag)";
        $search_term = "%" . $filters['search'] . "%";
        $params[':search'] = $search_term;
        $params[':search_tag'] = $search_term;
    }

    $where_clause = implode(" AND ", $where);

    // Toplam soru sayısını hesapla
    $count_stmt = $db->prepare("SELECT COUNT(*) FROM questions q WHERE $where_clause");
    $count_stmt->execute($params);
    $total_questions = $count_stmt->fetchColumn();

    $total_pages = ceil($total_questions / $per_page);
    $offset = ($filters['page'] - 1) * $per_page;

    // Soruları çek
    $questions_query = "
        SELECT 
            q.*,
            c.name as category_name,
            t.name as topic_name,
            l.name as level_name,
            GROUP_CONCAT(DISTINCT qo.option_text ORDER BY qo.id) as options,
            GROUP_CONCAT(DISTINCT qo.is_correct ORDER BY qo.id) as correct_answers
        FROM questions q
        LEFT JOIN categories c ON q.category_id = c.id
        LEFT JOIN topics t ON q.topic_id = t.id
        LEFT JOIN levels l ON q.level_id = l.id
        LEFT JOIN question_options qo ON q.id = qo.question_id
        WHERE $where_clause
        GROUP BY q.id
        ORDER BY q.created_at DESC
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $db->prepare($questions_query);
    $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // İstatistikleri çek
    $stats = $db->query("
        SELECT 
            (SELECT COUNT(*) FROM questions) as total_questions,
            (SELECT COUNT(*) FROM categories WHERE status = 1) as categories_count,
            (SELECT COUNT(*) FROM topics WHERE status = 1) as topics_count,
            (SELECT COUNT(*) FROM levels WHERE status = 1) as levels_count
    ")->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Veritabanı hatası: " . $e->getMessage());
    die("Veritabanı hatası oluştu. Lütfen sistem yöneticisi ile iletişime geçin.");
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Soru Bankası</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        .sidebar { height: 100vh; position: fixed; }
        .main-content { margin-left: 16.66%; }
        @media (max-width: 768px) { .main-content { margin-left: 0; } }
        .question-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .question-header { padding: 15px; border-bottom: 1px solid #eee; }
        .question-body { padding: 15px; }
        .question-footer {
            padding: 15px;
            border-top: 1px solid #eee;
            background: #f8f9fa;
        }
        .badge { margin-right: 5px; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1>Soru Bankası</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="add_questions.php" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-plus-lg"></i> Yeni Soru
                            </a>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#importModal">
                                <i class="bi bi-upload"></i> Excel İçe Aktar
                            </button>
                        </div>
                    </div>
                </div>

                <!-- İstatistikler -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Toplam Soru</h5>
                                <p class="card-text h2"><?php echo $stats['total_questions']; ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Kategoriler</h5>
                                <p class="card-text h2"><?php echo $stats['categories_count']; ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Konular</h5>
                                <p class="card-text h2"><?php echo $stats['topics_count']; ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Seviyeler</h5>
                                <p class="card-text h2"><?php echo $stats['levels_count']; ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filtreler -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Ana Kategori</label>
                                <select name="main_category_id" class="form-select" id="main-category-select">
                                    <option value="">Tümü</option>
                                    <?php foreach ($main_categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>" 
                                                <?php echo $filters['main_category_id'] == $category['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Alt Kategori</label>
                                <select name="sub_category_id" class="form-select" id="sub-category-select" 
                                        <?php echo empty($filters['main_category_id']) ? 'disabled' : ''; ?>>
                                    <option value="">Tümü</option>
                                    <?php foreach ($sub_categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>"
                                                <?php echo $filters['sub_category_id'] == $category['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
							                            <div class="col-md-3">
                                <label class="form-label">Alt Kategori 2</label>
                                <select name="sub_sub_category_id" class="form-select" id="sub-sub-category-select"
                                        <?php echo empty($filters['sub_category_id']) ? 'disabled' : ''; ?>>
                                    <option value="">Tümü</option>
                                    <?php foreach ($sub_sub_categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>"
                                                <?php echo $filters['sub_sub_category_id'] == $category['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Konu</label>
                                <select name="topic_id" class="form-select" id="topic-select"
                                        <?php echo empty($filters['sub_sub_category_id']) ? 'disabled' : ''; ?>>
                                    <option value="">Tümü</option>
                                    <?php foreach ($topics as $topic): ?>
                                        <option value="<?php echo $topic['id']; ?>"
                                                <?php echo $filters['topic_id'] == $topic['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($topic['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Seviye</label>
                                <select name="level_id" class="form-select">
                                    <option value="">Tümü</option>
                                    <?php foreach ($levels as $level): ?>
                                        <option value="<?php echo $level['id']; ?>"
                                                <?php echo $filters['level_id'] == $level['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($level['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-12">
                                <div class="input-group">
                                    <input type="text" name="search" class="form-control" 
                                           value="<?php echo htmlspecialchars($filters['search']); ?>"
                                           placeholder="Soru metni...">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-search"></i> Ara
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Sorular -->
                <form id="questionsForm" method="POST">
                    <?php if (!empty($questions)): ?>
                        <?php foreach ($questions as $question): ?>
                            <div class="question-card">
                                <div class="question-header">
                                    <div class="form-check">
                                        <input class="form-check-input question-checkbox" type="checkbox" 
                                               name="question_ids[]" value="<?php echo $question['id']; ?>">
                                    </div>
                                </div>
                                <div class="question-body">
                                    <?php if ($question['question_type'] === 'image' || $question['question_type'] === 'mixed'): ?>
                                        <div class="question-image mb-3">
                                            <img src="<?php echo htmlspecialchars($question['image_path']); ?>" 
                                                 class="img-fluid" alt="Soru görseli">
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($question['question_type'] === 'text' || $question['question_type'] === 'mixed'): ?>
                                        <div class="question-text">
                                            <?php echo nl2br(htmlspecialchars($question['question_text'])); ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($question['options']): ?>
                                        <div class="options-container mt-3">
                                            <?php 
                                            $options = explode(',', $question['options']);
                                            $correct_answers = explode(',', $question['correct_answers']);
                                            foreach ($options as $index => $option): 
                                            ?>
                                                <div class="option-item <?php echo $correct_answers[$index] ? 'correct-answer' : ''; ?>">
                                                    <?php echo chr(65 + $index) . ') ' . htmlspecialchars($option); ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
								                                <div class="question-footer">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <?php if ($question['category_name']): ?>
                                                <span class="badge bg-primary"><?php echo htmlspecialchars($question['category_name']); ?></span>
                                            <?php endif; ?>
                                            <?php if ($question['topic_name']): ?>
                                                <span class="badge bg-info"><?php echo htmlspecialchars($question['topic_name']); ?></span>
                                            <?php endif; ?>
                                            <?php if ($question['level_name']): ?>
                                                <span class="badge bg-secondary"><?php echo htmlspecialchars($question['level_name']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <a href="edit_question.php?id=<?php echo $question['id']; ?>" 
                                               class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-pencil"></i> Düzenle
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <!-- Sayfalama -->
                        <?php if ($total_pages > 1): ?>
                            <nav aria-label="Sayfalama">
                                <ul class="pagination justify-content-center">
                                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                        <li class="page-item <?php echo $filters['page'] == $i ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?>&<?php 
                                                echo http_build_query(array_filter([
                                                    'main_category_id' => $filters['main_category_id'],
                                                    'sub_category_id' => $filters['sub_category_id'],
                                                    'sub_sub_category_id' => $filters['sub_sub_category_id'],
                                                    'topic_id' => $filters['topic_id'],
                                                    'level_id' => $filters['level_id'],
                                                    'search' => $filters['search']
                                                ])); 
                                            ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>

                    <?php else: ?>
                        <div class="alert alert-info">
                            Gösterilecek soru bulunamadı.
                        </div>
                    <?php endif; ?>
                </form>

                <!-- Toplu İşlem Butonu -->
                <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1000;">
                    <button type="submit" form="questionsForm" name="delete_questions" 
                            class="btn btn-danger d-none" id="bulkDeleteBtn">
                        <i class="bi bi-trash"></i> Seçilenleri Sil
                    </button>
                </div>
            </main>
        </div>
    </div>

    <!-- Excel Import Modal -->
    <div class="modal fade" id="importModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Excel'den Soru İçe Aktar</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form action="import_questions.php" method="POST" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="excelFile" class="form-label">Excel Dosyası</label>
                            <input type="file" class="form-control" id="excelFile" name="excelFile" 
                                   accept=".xlsx,.xls" required>
                        </div>
                        <div class="mb-3">
                            <a href="templates/questions_template.xlsx" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-download"></i> Şablon İndir
                            </a>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-upload"></i> İçe Aktar
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Ana kategori değiştiğinde
$('#main-category-select').change(function() {
    const mainCategoryId = $(this).val();
    const subCategorySelect = $('#sub-category-select');
    const subSubCategorySelect = $('#sub-sub-category-select');
    const topicSelect = $('#topic-select');
    
    // Alt kategori ve konu seçimlerini sıfırla
    subCategorySelect.html('<option value="">Tümü</option>').prop('disabled', true);
    subSubCategorySelect.html('<option value="">Tümü</option>').prop('disabled', true);
    topicSelect.html('<option value="">Tümü</option>').prop('disabled', true);
    
    if (mainCategoryId) {
        // Alt kategorileri yükle
        $.ajax({
            url: 'get_categories.php',
            method: 'POST', // GET yerine POST kullan
            data: { parent_id: mainCategoryId },
            dataType: 'json',
            success: function(response) {
                console.log('Server yanıtı:', response); // Debug log
                subCategorySelect.html('<option value="">Tümü</option>');
                if (Array.isArray(response) && response.length > 0) {
                    response.forEach(function(category) {
                        subCategorySelect.append(`
                            <option value="${category.id}">
                                ${category.name}
                            </option>
                        `);
                    });
                    subCategorySelect.prop('disabled', false);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                console.error('Status:', status);
                console.error('Response:', xhr.responseText);
                alert('Kategoriler yüklenirken bir hata oluştu.');
            }
        });
    }
});

// Alt kategori değiştiğinde
$('#sub-category-select').change(function() {
    const subCategoryId = $(this).val();
    const subSubCategorySelect = $('#sub-sub-category-select');
    const topicSelect = $('#topic-select');
    
    // Alt-alt kategori ve konu seçimlerini sıfırla
    subSubCategorySelect.html('<option value="">Tümü</option>').prop('disabled', true);
    topicSelect.html('<option value="">Tümü</option>').prop('disabled', true);
    
    if (subCategoryId) {
        // Alt-alt kategorileri yükle
        $.ajax({
            url: 'get_categories.php',
            method: 'POST', // GET yerine POST kullan
            data: { parent_id: subCategoryId },
            dataType: 'json',
            success: function(response) {
                console.log('Server yanıtı:', response); // Debug log
                subSubCategorySelect.html('<option value="">Tümü</option>');
                if (Array.isArray(response) && response.length > 0) {
                    response.forEach(function(category) {
                        subSubCategorySelect.append(`
                            <option value="${category.id}">
                                ${category.name}
                            </option>
                        `);
                    });
                    subSubCategorySelect.prop('disabled', false);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                console.error('Status:', status);
                console.error('Response:', xhr.responseText);
                alert('Alt kategoriler yüklenirken bir hata oluştu.');
            }
        });
    }
});

// Alt-alt kategori değiştiğinde
$('#sub-sub-category-select').change(function() {
    const subSubCategoryId = $(this).val();
    const topicSelect = $('#topic-select');
    
    // Konu seçimini sıfırla
    topicSelect.html('<option value="">Tümü</option>').prop('disabled', true);
    
    if (subSubCategoryId) {
        // Konuları yükle
        $.ajax({
            url: 'get_topics.php',
            method: 'GET', // get_topics.php için GET metodu kullanılıyor
            data: { category_id: subSubCategoryId },
            dataType: 'json',
            success: function(response) {
                console.log('Server yanıtı:', response); // Debug log
                topicSelect.html('<option value="">Tümü</option>');
                if (Array.isArray(response) && response.length > 0) {
                    response.forEach(function(topic) {
                        topicSelect.append(`
                            <option value="${topic.id}">
                                ${topic.name}
                            </option>
                        `);
                    });
                    topicSelect.prop('disabled', false);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                console.error('Status:', status);
                console.error('Response:', xhr.responseText);
                alert('Konular yüklenirken bir hata oluştu.');
            }
        });
    }
});


    </script>
</body>
</html>