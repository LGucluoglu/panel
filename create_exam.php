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

try {
    // Ana kategorileri çek
    $stmt = $db->prepare("
        SELECT id, name 
        FROM categories 
        WHERE parent_id IS NULL AND status = 1 
        ORDER BY name ASC
    ");
    $stmt->execute();
    $mainCategories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Seviyeleri çek
    $stmt = $db->prepare("
        SELECT * FROM levels 
        WHERE status = 1 
        ORDER BY display_order ASC, name ASC
    ");
    $stmt->execute();
    $levels = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        // Form verilerini güvenli şekilde al
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $category_id = (int)($_POST['final_category_id'] ?? 0);
        $level_id = (int)($_POST['level_id'] ?? 0);
        $duration = (int)($_POST['duration'] ?? 0);
        $start_date = $_POST['start_date'] ?? '';
        $start_time = $_POST['start_time'] ?? '';
        $end_date = $_POST['end_date'] ?? '';
        $passing_score = !empty($_POST['passing_score']) ? (int)$_POST['passing_score'] : null;
        $question_count = (int)($_POST['question_count'] ?? 0);
        $status = isset($_POST['status']) ? 1 : 0;

        // Validasyon
        $errors = [];
        if (empty($title)) $errors[] = "Sınav başlığı gereklidir.";
        if (!$category_id) $errors[] = "Geçerli bir kategori seçmelisiniz.";
        if (!$level_id) $errors[] = "Geçerli bir seviye seçmelisiniz.";
        if ($duration < 1) $errors[] = "Sınav süresi en az 1 dakika olmalıdır.";
        if ($question_count < 1) $errors[] = "En az 1 soru seçmelisiniz.";
        if (!empty($_POST['passing_score']) && ($passing_score < 0 || $passing_score > 100)) {
            $errors[] = "Geçme notu 0-100 arasında olmalıdır.";
        }

        // Tarih kontrolü
        $start_datetime = strtotime($start_date . ' ' . $start_time);
        $end_datetime = strtotime($end_date . ' 23:59:59');
        
        if ($start_datetime >= $end_datetime) {
            $errors[] = "Bitiş tarihi başlangıç tarihinden sonra olmalıdır.";
        }
        if ($start_datetime < time()) {
            $errors[] = "Başlangıç tarihi geçmiş bir tarih olamaz.";
        }

        if (empty($errors)) {
            try {
                $db->beginTransaction();

                $stmt = $db->prepare("
                    INSERT INTO exams (
                        title, description, category_id, level_id, duration,
                        start_date, start_time, end_date, passing_score,
                        question_count, status, created_by, created_at
                    ) VALUES (
                        :title, :description, :category_id, :level_id, :duration,
                        :start_date, :start_time, :end_date, :passing_score,
                        :question_count, :status, :created_by, NOW()
                    )
                ");

                $stmt->execute([
                    ':title' => $title,
                    ':description' => $description,
                    ':category_id' => $category_id,
                    ':level_id' => $level_id,
                    ':duration' => $duration,
                    ':start_date' => $start_date,
                    ':start_time' => $start_time,
                    ':end_date' => $end_date,
                    ':passing_score' => $passing_score,
                    ':question_count' => $question_count,
                    ':status' => $status,
                    ':created_by' => $_SESSION['user_id']
                ]);

                $exam_id = $db->lastInsertId();
                $db->commit();

                $_SESSION['success'] = "Sınav başarıyla oluşturuldu.";
                header("Location: add_questions.php?exam_id=" . $exam_id);
                exit();

            } catch (PDOException $e) {
                $db->rollBack();
                $errors[] = "Veritabanı hatası: " . $e->getMessage();
            }
        }
    }

} catch (PDOException $e) {
    die("Veritabanı hatası: " . $e->getMessage());
}

// Alt kategorileri getiren AJAX endpoint'i
if (isset($_GET['action']) && $_GET['action'] == 'get_subcategories') {
    $parent_id = (int)$_GET['parent_id'];
    try {
        $stmt = $db->prepare("
            SELECT id, name 
            FROM categories 
            WHERE parent_id = ? AND status = 1 
            ORDER BY name ASC
        ");
        $stmt->execute([$parent_id]);
        $subcategories = $stmt->fetchAll(PDO::FETCH_ASSOC);
        header('Content-Type: application/json');
        echo json_encode($subcategories);
        exit;
    } catch (PDOException $e) {
        header('HTTP/1.1 500 Internal Server Error');
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yeni Sınav Oluştur</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        /* Önceki CSS stilleri aynı kalacak */
        .form-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            padding: 30px;
            margin: 20px 0;
        }
        .form-section {
            border-bottom: 1px solid #e3e6f0;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }
        .section-title {
            color: #4e73df;
            margin-bottom: 20px;
            font-size: 1.1rem;
            font-weight: 600;
        }
        .categories-container {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        .category-section {
            flex: 1;
            border: 1px solid #e3e6f0;
            border-radius: 10px;
            padding: 15px;
        }
        .category-section h4 {
            font-size: 1rem;
            color: #4e73df;
            margin-bottom: 15px;
        }
        .category-list {
            max-height: 300px;
            overflow-y: auto;
        }
        .category-item {
            padding: 10px;
            margin: 5px 0;
            border: 2px solid #e3e6f0;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .category-item:hover {
            border-color: #4e73df;
            background-color: #f8f9fc;
            transform: translateX(5px);
        }
        .category-item.selected {
            border-color: #4e73df;
            background-color: #e8eaf6;
        }
        .level-select {
            padding: 15px;
            border: 2px solid #e3e6f0;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .level-select:hover {
            border-color: #4e73df;
            background-color: #f8f9fc;
        }
        .level-select.selected {
            border-color: #4e73df;
            background-color: #e8eaf6;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="form-container">
                    <!-- Hata mesajları -->
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="POST" class="needs-validation" novalidate>
                        <!-- Temel Bilgiler -->
                        <div class="form-section">
                            <h3 class="section-title">Temel Bilgiler</h3>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="title" class="form-label">Sınav Başlığı</label>
                                    <input type="text" class="form-control" id="title" name="title" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="description" class="form-label">Sınav Açıklaması</label>
                                    <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Kategori Seçimi -->
                        <div class="form-section">
                            <h3 class="section-title">Kategori Seçimi</h3>
                            <div class="categories-container">
                                <div class="category-section">
                                    <h4>Ana Kategori</h4>
                                    <div class="category-list" id="mainCategories">
                                        <?php foreach ($mainCategories as $category): ?>
                                            <div class="category-item" data-id="<?php echo $category['id']; ?>">
                                                <?php echo htmlspecialchars($category['name']); ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div class="category-section">
                                    <h4>Alt Kategori</h4>
                                    <div class="category-list" id="subCategories">
                                        <div class="placeholder-text">Lütfen ana kategori seçiniz</div>
                                    </div>
                                </div>
                                <div class="category-section">
                                    <h4>Alt-Alt Kategori</h4>
                                    <div class="category-list" id="subSubCategories">
                                        <div class="placeholder-text">Lütfen alt kategori seçiniz</div>
                                    </div>
                                </div>
                            </div>
                            <input type="hidden" name="final_category_id" id="final_category_id" required>
                        </div>

                        <!-- Seviye Seçimi -->
                        <div class="form-section">
                            <h3 class="section-title">Seviye Seçimi</h3>
                            <div class="row">
                                <?php foreach ($levels as $level): ?>
                                    <div class="col-md-3 mb-3">
                                        <div class="level-select text-center" data-level-id="<?php echo $level['id']; ?>">
                                            <i class="bi bi-bar-chart-steps"></i>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($level['name']); ?></h6>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <input type="hidden" name="level_id" id="level_id" required>
                        </div>

                        <!-- Sınav Ayarları -->
                        <div class="form-section">
                            <h3 class="section-title">Sınav Ayarları</h3>
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <label for="duration" class="form-label">Süre (Dakika)</label>
                                    <input type="number" class="form-control" id="duration" name="duration" value="60" min="1" required>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="question_count" class="form-label">Soru Sayısı</label>
                                    <input type="number" class="form-control" id="question_count" name="question_count" value="20" min="1" required>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" id="enable_passing_score" name="enable_passing_score">
                                        <label class="form-check-label" for="enable_passing_score">
                                            Geçme Notu Belirle
                                        </label>
                                    </div>
                                    <input type="number" class="form-control" id="passing_score" name="passing_score" 
                                           value="70" min="0" max="100" disabled>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Durum</label>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="status" name="status" checked>
                                        <label class="form-check-label" for="status">Aktif</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tarih ve Zaman -->
                        <div class="form-section">
                            <h3 class="section-title">Tarih ve Zaman</h3>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="start_date" class="form-label">Başlangıç Tarihi</label>
                                    <input type="date" class="form-control" id="start_date" name="start_date" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="start_time" class="form-label">Başlangıç Saati</label>
                                    <input type="time" class="form-control" id="start_time" name="start_time" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="end_date" class="form-label">Bitiş Tarihi</label>
                                    <input type="date" class="form-control" id="end_date" name="end_date" required>
                                </div>
                            </div>
                        </div>

                        <!-- Form Submit -->
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg"></i> Sınavı Oluştur
                            </button>
                        </div>
                    </form>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Geçme notu aktivasyon kontrolü
            const enablePassingScoreCheckbox = document.getElementById('enable_passing_score');
            const passingScoreInput = document.getElementById('passing_score');

            enablePassingScoreCheckbox.addEventListener('change', function() {
                passingScoreInput.disabled = !this.checked;
                if (!this.checked) {
                    passingScoreInput.value = '';
                } else {
                    passingScoreInput.value = '70';
                }
            });

            // Kategori seçim işlemleri
            const mainCategories = document.getElementById('mainCategories');
            const subCategories = document.getElementById('subCategories');
            const subSubCategories = document.getElementById('subSubCategories');

            // Ana kategori seçimi
            mainCategories.addEventListener('click', function(e) {
                const item = e.target.closest('.category-item');
                if (!item) return;

                mainCategories.querySelectorAll('.category-item').forEach(el => el.classList.remove('selected'));
                item.classList.add('selected');

                fetchCategories('get_subcategories', item.dataset.id, subCategories);
                clearCategories(subSubCategories);
            });

            // Alt kategori seçimi
            subCategories.addEventListener('click', function(e) {
                const item = e.target.closest('.category-item');
                if (!item) return;

                subCategories.querySelectorAll('.category-item').forEach(el => el.classList.remove('selected'));
                item.classList.add('selected');

                fetchCategories('get_subcategories', item.dataset.id, subSubCategories);
            });

            // Alt-alt kategori seçimi
            subSubCategories.addEventListener('click', function(e) {
                const item = e.target.closest('.category-item');
                if (!item) return;

                subSubCategories.querySelectorAll('.category-item').forEach(el => el.classList.remove('selected'));
                item.classList.add('selected');
                document.getElementById('final_category_id').value = item.dataset.id;
            });

            // Seviye seçimi
            document.querySelectorAll('.level-select').forEach(item => {
                item.addEventListener('click', function() {
                    document.querySelectorAll('.level-select').forEach(el => el.classList.remove('selected'));
                    this.classList.add('selected');
                    document.getElementById('level_id').value = this.dataset.levelId;
                });
            });

            // Kategorileri getiren fonksiyon
            function fetchCategories(action, parentId, container) {
                fetch(`create_exam.php?action=${action}&parent_id=${parentId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.length === 0) {
                            container.innerHTML = '<div class="placeholder-text">Veri bulunamadı</div>';
                            return;
                        }

                        container.innerHTML = data.map(item => `
                            <div class="category-item" data-id="${item.id}">
                                ${item.name}
                            </div>
                        `).join('');
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        container.innerHTML = '<div class="placeholder-text">Hata oluştu</div>';
                    });
            }

            // Kategori listesini temizleyen fonksiyon
            function clearCategories(container) {
                container.innerHTML = '<div class="placeholder-text">Lütfen üst kategori seçiniz</div>';
            }

            // Form validasyonu
            const form = document.querySelector('.needs-validation');
            form.addEventListener('submit', function(event) {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            });

            // Tarih seçici
            flatpickr("#start_date", {
                minDate: "today",
                dateFormat: "Y-m-d"
            });

            flatpickr("#end_date", {
                minDate: "today",
                dateFormat: "Y-m-d"
            });
        });
    </script>
</body>
</html>