<?php
session_start();
require_once 'config.php';

// Öğrenci girişi kontrolü
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

// Sınav ID kontrolü
if (!isset($_GET['exam_id'])) {
    header("Location: exams.php");
    exit();
}

$exam_id = $_GET['exam_id'];

// Sınav ve sonuç bilgilerini çek
$stmt = $db->prepare("
    SELECT e.*, se.*, 
           se.start_time as exam_start_time,
           se.end_time as exam_end_time
    FROM exams e
    JOIN student_exams se ON e.id = se.exam_id
    WHERE e.id = ? AND se.user_id = ? AND se.status = 'completed'
");
$stmt->execute([$exam_id, $_SESSION['user_id']]);
$exam = $stmt->fetch(PDO::FETCH_ASSOC);

// Sınav kontrolü
if (!$exam) {
    header("Location: exams.php");
    exit();
}

// Soru ve cevapları çek
$stmt = $db->prepare("
    SELECT q.*, sa.given_answer, sa.is_correct
    FROM questions q
    LEFT JOIN student_answers sa ON q.id = sa.question_id 
    AND sa.student_exam_id = ?
    WHERE q.exam_id = ?
    ORDER BY q.id ASC
");
$stmt->execute([$exam['id'], $exam_id]);
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Sınav istatistiklerini hesapla
$total_time = strtotime($exam['exam_end_time']) - strtotime($exam['exam_start_time']);
$hours = floor($total_time / 3600);
$minutes = floor(($total_time % 3600) / 60);
$seconds = $total_time % 60;
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sınav Sonucu - <?php echo htmlspecialchars($exam['title']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .result-container {
            max-width: 1000px;
            margin: 20px auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .result-header {
            background: #4e73df;
            color: white;
            padding: 30px;
            border-radius: 15px 15px 0 0;
            text-align: center;
        }
        .result-body {
            padding: 30px;
        }
        .stats-card {
            background: #f8f9fc;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid #e3e6f0;
        }
        .score-circle {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: #4e73df;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            font-weight: bold;
            margin: 0 auto 20px;
        }
        .stat-item {
            text-align: center;
            padding: 15px;
            background: white;
            border-radius: 8px;
            margin-bottom: 10px;
        }
        .stat-label {
            font-size: 0.9rem;
            color: #858796;
            margin-bottom: 5px;
        }
        .stat-value {
            font-size: 1.2rem;
            font-weight: bold;
            color: #4e73df;
        }
        .question-review {
            background: #f8f9fc;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            border: 1px solid #e3e6f0;
        }
        .question-text {
            font-size: 1.1rem;
            margin-bottom: 15px;
        }
        .option {
            padding: 10px 15px;
            margin: 5px 0;
            border-radius: 5px;
        }
        .option.correct {
            background: #1cc88a20;
            border: 1px solid #1cc88a;
        }
        .option.incorrect {
            background: #e74a3b20;
            border: 1px solid #e74a3b;
        }
        .option.selected {
            background: #4e73df20;
            border: 1px solid #4e73df;
        }
        .result-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        .badge-correct {
            background: #1cc88a;
            color: white;
        }
        .badge-incorrect {
            background: #e74a3b;
            color: white;
        }
        .badge-empty {
            background: #858796;
            color: white;
        }
    </style>
</head>
<body>
    <div class="result-container">
        <div class="result-header">
            <h3><?php echo htmlspecialchars($exam['title']); ?></h3>
            <p class="mb-0">Sınav Sonucu</p>
        </div>
        
        <div class="result-body">
            <!-- Genel Sonuç -->
            <div class="stats-card">
                <div class="score-circle">
                    <?php echo number_format($exam['score'], 1); ?>%
                </div>
                <div class="row">
                    <div class="col-md-3">
                        <div class="stat-item">
                            <div class="stat-label">Toplam Soru</div>
                            <div class="stat-value"><?php echo $exam['total_questions']; ?></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-item">
                            <div class="stat-label">Doğru</div>
                            <div class="stat-value text-success"><?php echo $exam['correct_answers']; ?></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-item">
                            <div class="stat-label">Yanlış</div>
                            <div class="stat-value text-danger"><?php echo $exam['wrong_answers']; ?></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-item">
                            <div class="stat-label">Boş</div>
                            <div class="stat-value text-warning"><?php echo $exam['empty_answers']; ?></div>
                        </div>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-md-6">
                        <div class="stat-item">
                            <div class="stat-label">Sınav Tarihi</div>
                            <div class="stat-value">
                                <?php echo date('d.m.Y H:i', strtotime($exam['exam_start_time'])); ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="stat-item">
                            <div class="stat-label">Geçirilen Süre</div>
                            <div class="stat-value">
                                <?php 
                                if ($hours > 0) echo $hours . ' saat ';
                                if ($minutes > 0) echo $minutes . ' dakika ';
                                echo $seconds . ' saniye';
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Soru ve Cevap İncelemesi -->
            <h4 class="mb-4">Soru ve Cevap İncelemesi</h4>
            <?php foreach ($questions as $index => $question): ?>
                <div class="question-review">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <h5 class="mb-0">Soru <?php echo $index + 1; ?></h5>
                        <?php if ($question['given_answer']): ?>
                            <?php if ($question['is_correct']): ?>
                                <span class="result-badge badge-correct">Doğru</span>
                            <?php else: ?>
                                <span class="result-badge badge-incorrect">Yanlış</span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="result-badge badge-empty">Boş</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="question-text">
                        <?php echo htmlspecialchars($question['question_text']); ?>
                    </div>

                    <?php
                    $options = [
                        'a' => $question['option_a'],
                        'b' => $question['option_b'],
                        'c' => $question['option_c'],
                        'd' => $question['option_d']
                    ];
                    foreach ($options as $key => $value):
                        $class = '';
                        if ($key == $question['correct_answer']) {
                            $class = 'correct';
                        } elseif ($key == $question['given_answer'] && !$question['is_correct']) {
                            $class = 'incorrect';
                        }
                    ?>
                        <div class="option <?php echo $class; ?>">
                            <?php echo strtoupper($key); ?>) <?php echo htmlspecialchars($value); ?>
                            <?php if ($key == $question['correct_answer']): ?>
                                <i class="bi bi-check-circle-fill text-success float-end"></i>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>

            <div class="text-center mt-4">
                <a href="exams.php" class="btn btn-primary">
                    <i class="bi bi-arrow-left"></i> Sınavlara Dön
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>