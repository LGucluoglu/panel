<?php
session_start();
require_once 'config.php';

// Admin kontrolü
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Soru ID kontrolü
if (!isset($_GET['id'])) {
    header("Location: question_bank.php");
    exit();
}

$question_id = $_GET['id'];

// Soru bilgilerini çek
$stmt = $db->prepare("
    SELECT q.*, 
           GROUP_CONCAT(qo.id) as option_ids,
           GROUP_CONCAT(qo.option_text ORDER BY qo.id) as options,
           GROUP_CONCAT(qo.is_correct ORDER BY qo.id) as correct_answers
    FROM questions q
    LEFT JOIN question_options qo ON q.id = qo.question_id
    WHERE q.id = ?
    GROUP BY q.id
");
$stmt->execute([$question_id]);
$question = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$question) {
    header("Location: question_bank.php");
    exit();
}

// Kategorileri çek
$stmt = $db->query("
    SELECT c.*, p.name as parent_name 
    FROM categories c
    LEFT JOIN categories p ON c.parent_id = p.id
    WHERE c.status = 1
    ORDER BY c.parent_id IS NULL DESC, c.name ASC
");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Seviyeleri çek
$stmt = $db->query("SELECT * FROM levels WHERE status = 1 ORDER BY name ASC");
$levels = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Mevcut etiketleri çek
$stmt = $db->query("
    SELECT DISTINCT SUBSTRING_INDEX(SUBSTRING_INDEX(tags, ',', n.n), ',', -1) tag
    FROM questions
    CROSS JOIN (
        SELECT a.N + b.N * 10 + 1 n
        FROM (SELECT 0 AS N UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) a
        CROSS JOIN (SELECT 0 AS N UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) b
        ORDER BY n
    ) n
    WHERE n.n <= 1 + (LENGTH(tags) - LENGTH(REPLACE(tags, ',', '')))
    AND tags != ''
    GROUP BY tag
    ORDER BY tag
");
$existing_tags = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Soru kullanım bilgilerini çek
$stmt = $db->prepare("
    SELECT COUNT(DISTINCT eq.exam_id) as exam_count,
           COUNT(DISTINCT er.id) as usage_count,
           MAX(er.completed_at) as last_used
    FROM exam_questions eq
    LEFT JOIN exam_results er ON eq.exam_id = er.exam_id
    WHERE eq.question_id = ?
");
$stmt->execute([$question_id]);
$usage_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Soru güncelleme işlemi
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $question_text = trim($_POST['question_text']);
    $category_id = $_POST['category_id'];
    $level_id = $_POST['level_id'];
    $difficulty = $_POST['difficulty'];
    $points = $_POST['points'];
    $options = array_map('trim', $_POST['options']);
    $correct_answer = $_POST['correct_answer'];
    $explanation = trim($_POST['explanation']);
    $tags = isset($_POST['tags']) ? implode(',', $_POST['tags']) : '';

    $errors = [];

    // Validasyonlar
    if (empty($question_text)) {
        $errors[] = "Soru metni boş bırakılamaz.";
    }

    if (empty($category_id)) {
        $errors[] = "Kategori seçilmelidir.";
    }

    if (empty($level_id)) {
        $errors[] = "Seviye seçilmelidir.";
    }

    if (!in_array($difficulty, ['easy', 'medium', 'hard'])) {
        $errors[] = "Geçerli bir zorluk seviyesi seçilmelidir.";
    }

    if ($points < 1) {
        $errors[] = "Puan değeri en az 1 olmalıdır.";
    }

    if (count(array_filter($options)) < 4) {
        $errors[] = "Tüm şıklar doldurulmalıdır.";
    }

    if (!isset($options[$correct_answer])) {
        $errors[] = "Doğru cevap seçilmelidir.";
    }

    // Hata yoksa güncelle
    if (empty($errors)) {
        try {
            $db->beginTransaction();

            // Soruyu güncelle
            $stmt = $db->prepare("
                UPDATE questions 
                SET question_text = ?, category_id = ?, level_id = ?, 
                    difficulty = ?, points = ?, explanation = ?, tags = ?,
                    updated_by = ?, updated_at = NOW()
                WHERE id = ?
            ");

            $stmt->execute([
                $question_text,
                $category_id,
                $level_id,
                $difficulty,
                $points,
                $explanation,
                $tags,
                $_SESSION['user_id'],
                $question_id
            ]);

            // Mevcut şıkları sil
            $stmt = $db->prepare("DELETE FROM question_options WHERE question_id = ?");
            $stmt->execute([$question_id]);

            // Yeni şıkları ekle
            $stmt = $db->prepare("
                INSERT INTO question_options (
                    question_id, option_text, is_correct
                ) VALUES (?, ?, ?)
            ");

            foreach ($options as $index => $option_text) {
                $is_correct = ($index == $correct_answer) ? 1 : 0;
                $stmt->execute([$question_id, $option_text, $is_correct]);
            }

            $db->commit();
            $_SESSION['success'] = "Soru başarıyla güncellendi.";
            header("Location: question_bank.php");
            exit();

        } catch (PDOException $e) {
            $db->rollBack();
            $errors[] = "Veritabanı hatası: " . $e->getMessage();
        }
    }
}

// Mevcut şıkları ve doğru cevabı hazırla
$option_texts = explode(',', $question['options']);
$correct_answers = explode(',', $question['correct_answers']);
$correct_answer_index = array_search('1', $correct_answers);

// Mevcut etiketleri dizi haline getir
$current_tags = !empty($question['tags']) ? explode(',', $question['tags']) : [];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Soru Düzenle</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-bs4.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <style>
        .form-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            padding: 30px;
            margin: 20px 0;
        }

        .option-container {
            position: relative;
            margin-bottom: 15px;
        }

        .option-letter {
            position: absolute;
            left: -40px;
            top: 50%;
            transform: translateY(-50%);
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #4e73df;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }

        .stats-card {
            background: #f8f9fc;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .stats-item {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }

        .stats-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #4e73df20;
            color: #4e73df;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
        }

        .stats-value {
            font-size: 1.1rem;
            font-weight: bold;
            margin-bottom: 2px;
        }

        .stats-label {
            font-size: 0.9rem;
            color: #858796;
        }

        .history-timeline {
            position: relative;
            padding-left: 30px;
        }

        .history-item {
            position: relative;
            padding-bottom: 20px;
        }

        .history-item::before {
            content: '';
            position: absolute;
            left: -30px;
            top: 0;
            width: 2px;
            height: 100%;
            background: #e3e6f0;
        }

        .history-item::after {
            content: '';
            position: absolute;
            left: -34px;
            top: 5px;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #4e73df;
        }

        .difficulty-badge {
            padding: 5px 15px;
            border-radius: 15px;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .difficulty-easy { background: #1cc88a20; color: #1cc88a; }
        .difficulty-medium { background: #f6c23e20; color: #f6c23e; }
        .difficulty-hard { background: #e74a3b20; color: #e74a3b; }

        .warning-box {
            background: #f6c23e20;
            border: 1px solid #f6c23e;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include 'sidebar.php'; ?>

            <!-- Ana İçerik -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="form-container">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2 class="h3">Soru Düzenle</h2>
                        <a href="question_bank.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Geri Dön
                        </a>
                    </div>

                    <!-- Kullanım İstatistikleri -->
                    <div class="stats-card">
                        <h5 class="mb-3">Soru Kullanım Bilgileri</h5>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="stats-item">
                                    <div class="stats-icon">
                                        <i class="bi bi-journal-text"></i>
                                    </div>
                                    <div>
                                        <div class="stats-value"><?php echo $usage_stats['exam_count']; ?></div>
                                        <div class="stats-label">Sınavda Kullanıldı</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="stats-item">
                                    <div class="stats-icon">
                                        <i class="bi bi-people"></i>
                                    </div>
                                    <div>
                                        <div class="stats-value"><?php echo $usage_stats['usage_count']; ?></div>
                                        <div class="stats-label">Kez Cevaplandı</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="stats-item">
                                    <div class="stats-icon">
                                        <i class="bi bi-clock-history"></i>
                                    </div>
                                    <div>
                                        <div class="stats-value">
                                            <?php echo $usage_stats['last_used'] ? date('d.m.Y', strtotime($usage_stats['last_used'])) : '-'; ?>
                                        </div>
                                        <div class="stats-label">Son Kullanım</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if ($usage_stats['exam_count'] > 0): ?>
                        <div class="warning-box">
                            <i class="bi bi-exclamation-triangle"></i>
                            <strong>Dikkat:</strong> Bu soru <?php echo $usage_stats['exam_count']; ?> sınavda kullanılmıştır. 
                            Yapacağınız değişiklikler geçmiş sınav sonuçlarını etkilemeyecektir.
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo $error; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="POST" class="needs-validation" novalidate>
                        <!-- Soru Metni -->
                        <div class="mb-4">
                            <label for="question_text" class="form-label">Soru Metni</label>
                            <textarea class="form-control summernote" id="question_text" name="question_text" 
                                      required><?php echo $question['question_text']; ?></textarea>
                            <div class="invalid-feedback">Soru metni gereklidir.</div>
                        </div>

                        <!-- Kategori ve Seviye -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label for="category_id" class="form-label">Kategori</label>
                                <select class="form-select select2" id="category_id" name="category_id" required>
                                    <option value="">Seçiniz</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>"
                                                <?php echo $question['category_id'] == $category['id'] ? 'selected' : ''; ?>>
                                            <?php echo $category['parent_name'] ? htmlspecialchars($category['parent_name']) . ' > ' : ''; ?>
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="level_id" class="form-label">Seviye</label>
                                <select class="form-select select2" id="level_id" name="level_id" required>
                                    <option value="">Seçiniz</option>
                                    <?php foreach ($levels as $level): ?>
                                        <option value="<?php echo $level['id']; ?>"
                                                <?php echo $question['level_id'] == $level['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($level['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Zorluk ve Puan -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label class="form-label">Zorluk Seviyesi</label>
                                <div class="btn-group w-100" role="group">
                                    <input type="radio" class="btn-check" name="difficulty" id="difficulty_easy" 
                                           value="easy" <?php echo $question['difficulty'] == 'easy' ? 'checked' : ''; ?>>
                                    <label class="btn btn-outline-success" for="difficulty_easy">Kolay</label>

                                    <input type="radio" class="btn-check" name="difficulty" id="difficulty_medium" 
                                           value="medium" <?php echo $question['difficulty'] == 'medium' ? 'checked' : ''; ?>>
                                    <label class="btn btn-outline-warning" for="difficulty_medium">Orta</label>

                                    <input type="radio" class="btn-check" name="difficulty" id="difficulty_hard" 
                                           value="hard" <?php echo $question['difficulty'] == 'hard' ? 'checked' : ''; ?>>
                                    <label class="btn btn-outline-danger" for="difficulty_hard">Zor</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="points" class="form-label">Puan Değeri</label>
                                <input type="number" class="form-control" id="points" name="points" 
                                       value="<?php echo $question['points']; ?>"
                                       min="1" required>
                            </div>
                        </div>

                        <!-- Şıklar -->
                        <div class="mb-4">
                            <label class="form-label">Şıklar</label>
                            <div class="ms-5">
                                <?php 
                                $letters = ['A', 'B', 'C', 'D'];
                                foreach ($letters as $index => $letter): 
                                ?>
                                    <div class="option-container">
                                        <div class="option-letter"><?php echo $letter; ?></div>
                                        <div class="input-group">
                                            <input type="text" class="form-control" 
                                                   name="options[<?php echo $index; ?>]" 
                                                   value="<?php echo htmlspecialchars($option_texts[$index]); ?>"
                                                   required>
                                            <div class="input-group-text">
                                                <input class="form-check-input mt-0" type="radio" 
                                                       name="correct_answer" value="<?php echo $index; ?>"
                                                       <?php echo $correct_answer_index == $index ? 'checked' : ''; ?>>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Açıklama -->
                        <div class="mb-4">
                            <label for="explanation" class="form-label">Açıklama (İsteğe Bağlı)</label>
                            <textarea class="form-control" id="explanation" name="explanation" 
                                      rows="3"><?php echo htmlspecialchars($question['explanation']); ?></textarea>
                        </div>

                        <!-- Etiketler -->
                        <div class="mb-4">
                            <label for="tags" class="form-label">Etiketler</label>
                            <select class="form-control select2-tags" id="tags" name="tags[]" multiple>
                                <?php foreach ($existing_tags as $tag): ?>
                                    <option value="<?php echo htmlspecialchars($tag); ?>"
                                            <?php echo in_array($tag, $current_tags) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($tag); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Virgülle ayırarak yeni etiketler ekleyebilirsiniz.</div>
                        </div>

                        <div class="d-flex justify-content-between">
                            <button type="button" class="btn btn-danger" onclick="confirmDelete()">
                                <i class="bi bi-trash"></i> Soruyu Sil
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg"></i> Değişiklikleri Kaydet
                            </button>
                        </div>
                    </form>
                </div>
            </main>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-bs4.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            // Summernote editörünü başlat
            $('.summernote').summernote({
                height: 200,
                toolbar: [
                    ['style', ['bold', 'italic', 'underline', 'clear']],
                    ['font', ['strikethrough', 'superscript', 'subscript']],
                    ['para', ['ul', 'ol']],
                    ['insert', ['picture', 'link']],
                    ['view', ['fullscreen', 'codeview']]
                ]
            });

            // Select2'yi başlat
            $('.select2').select2({
                theme: 'bootstrap-5'
            });

            // Etiketler için Select2
            $('.select2-tags').select2({
                theme: 'bootstrap-5',
                tags: true,
                tokenSeparators: [',']
            });
        });

        // Form validasyonu
        (function () {
            'use strict'
            var forms = document.querySelectorAll('.needs-validation')
            Array.prototype.slice.call(forms).forEach(function (form) {
                form.addEventListener('submit', function (event) {
                    if (!form.checkValidity()) {
                        event.preventDefault()
                        event.stopPropagation()
                    }
                    form.classList.add('was-validated')
                }, false)
            })
        })()

        // Soru silme onayı
        function confirmDelete() {
            if (confirm('Bu soruyu silmek istediğinizden emin misiniz? Bu işlem geri alınamaz.')) {
                window.location.href = 'delete_question.php?id=<?php echo $question_id; ?>';
            }
        }
    </script>
</body>
</html>