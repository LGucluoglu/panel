<?php
session_start();
require_once 'config.php';

// Admin kontrolü
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Sınav ID kontrolü
if (!isset($_GET['id'])) {
    header("Location: exams_management.php");
    exit();
}

$exam_id = $_GET['id'];

// Sınav bilgilerini çek
$stmt = $db->prepare("
    SELECT e.*, c.name as category_name, l.name as level_name 
    FROM exams e
    LEFT JOIN exam_categories ec ON e.id = ec.exam_id
    LEFT JOIN categories c ON ec.category_id = c.id
    LEFT JOIN levels l ON ec.level_id = l.id
    WHERE e.id = ?
");
$stmt->execute([$exam_id]);
$exam = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$exam) {
    header("Location: exams_management.php");
    exit();
}

// Soruları çek
$stmt = $db->prepare("
    SELECT q.*, eq.question_order,
           GROUP_CONCAT(qo.id ORDER BY qo.id) as option_ids,
           GROUP_CONCAT(qo.option_text ORDER BY qo.id) as options,
           GROUP_CONCAT(qo.is_correct ORDER BY qo.id) as correct_answers
    FROM exam_questions eq
    JOIN questions q ON eq.question_id = q.id
    JOIN question_options qo ON q.id = qo.question_id
    WHERE eq.exam_id = ?
    GROUP BY q.id
    ORDER BY eq.question_order
");
$stmt->execute([$exam_id]);
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Toplam puanı hesapla
$total_points = array_sum(array_column($questions, 'points'));
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sınav Önizleme - <?php echo htmlspecialchars($exam['title']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        .preview-container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            padding: 30px;
        }

        .exam-header {
            background: #f8f9fc;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
        }

        .exam-info {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-top: 15px;
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .info-item i {
            color: #4e73df;
        }

        .question-card {
            background: white;
            border: 1px solid #e3e6f0;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .question-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e3e6f0;
        }

        .question-number {
            background: #4e73df;
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }

        .points-badge {
            background: #1cc88a;
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
        }

        .option-container {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
            padding: 10px;
            border: 1px solid #e3e6f0;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .option-container:hover {
            background: #f8f9fc;
        }

        .option-letter {
            width: 25px;
            height: 25px;
            border-radius: 50%;
            background: #4e73df;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 10px;
        }

        .timer-container {
            position: sticky;
            top: 20px;
            z-index: 1000;
            background: white;
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            margin-bottom: 20px;
        }

        .timer {
            font-size: 1.5rem;
            font-weight: bold;
            color: #4e73df;
        }

        .progress-container {
            margin-top: 10px;
        }

        .exam-footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e3e6f0;
            text-align: center;
        }

        @media (max-width: 768px) {
            .preview-container {
                margin: 10px;
                padding: 15px;
            }
        }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid py-4">
        <div class="preview-container">
            <!-- Üst Bilgi Çubuğu -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="h4 mb-0">Sınav Önizleme</h2>
                <div>
                    <a href="add_questions.php?exam_id=<?php echo $exam_id; ?>" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Geri Dön
                    </a>
                    <a href="print_exam.php?id=<?php echo $exam_id; ?>" class="btn btn-primary ms-2" target="_blank">
                        <i class="bi bi-printer"></i> Yazdır
                    </a>
                </div>
            </div>

            <!-- Sınav Başlığı ve Bilgileri -->
            <div class="exam-header">
                <h1 class="h3 text-center mb-3"><?php echo htmlspecialchars($exam['title']); ?></h1>
                <div class="exam-info">
                    <div class="info-item">
                        <i class="bi bi-folder"></i>
                        <span><?php echo htmlspecialchars($exam['category_name']); ?></span>
                    </div>
                    <div class="info-item">
                        <i class="bi bi-bar-chart-steps"></i>
                        <span><?php echo htmlspecialchars($exam['level_name']); ?></span>
                    </div>
                    <div class="info-item">
                        <i class="bi bi-clock"></i>
                        <span><?php echo $exam['duration']; ?> Dakika</span>
                    </div>
                    <div class="info-item">
                        <i class="bi bi-trophy"></i>
                        <span>Geçme Notu: %<?php echo $exam['passing_score']; ?></span>
                    </div>
                    <div class="info-item">
                        <i class="bi bi-star"></i>
                        <span>Toplam Puan: <?php echo $total_points; ?></span>
                    </div>
                </div>
            </div>

            <!-- Süre ve İlerleme -->
            <div class="timer-container">
                <div class="timer" id="examTimer">
                    <?php echo $exam['duration']; ?>:00
                </div>
                <small class="text-muted">Kalan Süre</small>
                <div class="progress-container">
                    <div class="progress" style="height: 5px;">
                        <div class="progress-bar" role="progressbar" style="width: 100%"></div>
                    </div>
                </div>
            </div>

            <!-- Sorular -->
            <form id="examForm">
                <?php foreach ($questions as $index => $question): ?>
                    <div class="question-card">
                        <div class="question-header">
                            <div class="d-flex align-items-center">
                                <div class="question-number"><?php echo $index + 1; ?></div>
                                <div class="points-badge ms-2"><?php echo $question['points']; ?> Puan</div>
                            </div>
                        </div>

                        <div class="question-text mb-3">
                            <?php echo $question['question_text']; ?>
                        </div>

                        <?php 
                        $options = explode(',', $question['options']);
                        $option_ids = explode(',', $question['option_ids']);
                        foreach ($options as $opt_index => $option): 
                        ?>
                            <div class="option-container">
                                <input type="radio" 
                                       name="question_<?php echo $question['id']; ?>" 
                                       value="<?php echo $option_ids[$opt_index]; ?>"
                                       class="d-none"
                                       id="option_<?php echo $option_ids[$opt_index]; ?>">
                                <label for="option_<?php echo $option_ids[$opt_index]; ?>" 
                                       class="mb-0 d-flex align-items-center w-100">
                                    <div class="option-letter"><?php echo chr(65 + $opt_index); ?></div>
                                    <div><?php echo htmlspecialchars($option); ?></div>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>

                <!-- Sınav Sonu -->
                <div class="exam-footer">
                    <button type="button" class="btn btn-primary btn-lg" onclick="finishExam()">
                        <i class="bi bi-check-circle"></i> Sınavı Bitir
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Süre sayacı
        function startTimer(duration) {
            let timer = duration * 60;
            const timerDisplay = document.getElementById('examTimer');
            const progressBar = document.querySelector('.progress-bar');
            const interval = setInterval(function() {
                const minutes = parseInt(timer / 60, 10);
                const seconds = parseInt(timer % 60, 10);

                timerDisplay.textContent = minutes.toString().padStart(2, '0') + ':' + 
                                         seconds.toString().padStart(2, '0');

                const progress = (timer / (duration * 60)) * 100;
                progressBar.style.width = progress + '%';

                if (--timer < 0) {
                    clearInterval(interval);
                    finishExam();
                }
            }, 1000);
        }

        // Sınavı bitir
        function finishExam() {
            if (confirm('Sınavı bitirmek istediğinizden emin misiniz?')) {
                alert('Bu bir önizlemedir. Gerçek sınavda cevaplarınız kaydedilecektir.');
            }
        }

        // Sayfa yüklendiğinde süre sayacını başlat
        document.addEventListener('DOMContentLoaded', function() {
            startTimer(<?php echo $exam['duration']; ?>);
        });

        // Seçenek tıklama
        document.querySelectorAll('.option-container').forEach(container => {
            container.addEventListener('click', function() {
                const radio = this.querySelector('input[type="radio"]');
                radio.checked = true;
            });
        });
    </script>
</body>
</html>