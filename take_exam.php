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

// Sınav bilgilerini çek
$stmt = $db->prepare("
    SELECT e.*, se.id as student_exam_id, se.status as exam_status, 
           se.start_time as student_start_time
    FROM exams e
    JOIN student_exams se ON e.id = se.exam_id
    WHERE e.id = ? AND se.user_id = ? AND se.status = 'in_progress'
");
$stmt->execute([$exam_id, $_SESSION['user_id']]);
$exam = $stmt->fetch(PDO::FETCH_ASSOC);

// Sınav kontrolü
if (!$exam) {
    header("Location: exams.php");
    exit();
}

// Sınav sorularını çek
$stmt = $db->prepare("
    SELECT q.*, sa.given_answer
    FROM questions q
    LEFT JOIN student_answers sa ON q.id = sa.question_id 
    AND sa.student_exam_id = ?
    WHERE q.exam_id = ?
    ORDER BY q.id ASC
");
$stmt->execute([$exam['student_exam_id'], $exam_id]);
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Sınav bitirme işlemi
if (isset($_POST['finish_exam'])) {
    $answers = $_POST['answers'] ?? [];
    $correct_count = 0;
    $wrong_count = 0;
    $empty_count = 0;

    // Mevcut cevapları sil
    $stmt = $db->prepare("DELETE FROM student_answers WHERE student_exam_id = ?");
    $stmt->execute([$exam['student_exam_id']]);

    // Yeni cevapları kaydet ve sonuçları hesapla
    foreach ($questions as $question) {
        $given_answer = $answers[$question['id']] ?? null;
        
        if ($given_answer) {
            $is_correct = ($given_answer == $question['correct_answer']) ? 1 : 0;
            if ($is_correct) {
                $correct_count++;
            } else {
                $wrong_count++;
            }
        } else {
            $empty_count++;
        }

        $stmt = $db->prepare("
            INSERT INTO student_answers (student_exam_id, question_id, given_answer, is_correct)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $exam['student_exam_id'],
            $question['id'],
            $given_answer,
            $given_answer ? ($given_answer == $question['correct_answer'] ? 1 : 0) : null
        ]);
    }

    // Sınav sonucunu hesapla
    $total_questions = count($questions);
    $score = ($correct_count / $total_questions) * 100;

    // Sınav kaydını güncelle
    $stmt = $db->prepare("
        UPDATE student_exams 
        SET status = 'completed',
            end_time = NOW(),
            score = ?,
            correct_answers = ?,
            wrong_answers = ?,
            empty_answers = ?
        WHERE id = ?
    ");
    $stmt->execute([
        $score,
        $correct_count,
        $wrong_count,
        $empty_count,
        $exam['student_exam_id']
    ]);

    // Sonuç sayfasına yönlendir
    header("Location: exam_result.php?exam_id=" . $exam_id);
    exit();
}

// Kalan süreyi hesapla
$start_time = new DateTime($exam['student_start_time']);
$duration_minutes = $exam['duration'];
$end_time = clone $start_time;
$end_time->add(new DateInterval('PT' . $duration_minutes . 'M'));
$now = new DateTime();
$remaining_seconds = max(0, $end_time->getTimestamp() - $now->getTimestamp());
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($exam['title']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .exam-container {
            max-width: 900px;
            margin: 20px auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .exam-header {
            background: #4e73df;
            color: white;
            padding: 20px;
            border-radius: 15px 15px 0 0;
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        .exam-body {
            padding: 30px;
        }
        .question-card {
            background: #f8f9fc;
            border: 1px solid #e3e6f0;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .question-text {
            font-size: 1.1rem;
            margin-bottom: 20px;
        }
        .option-label {
            display: block;
            padding: 10px 15px;
            margin: 5px 0;
            background: white;
            border: 1px solid #e3e6f0;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .option-label:hover {
            background: #eaecf4;
        }
        input[type="radio"]:checked + .option-label {
            background: #4e73df;
            color: white;
            border-color: #4e73df;
        }
        .timer {
            font-size: 1.2rem;
            font-weight: bold;
            padding: 10px 20px;
            background: rgba(255,255,255,0.2);
            border-radius: 10px;
        }
        .question-nav {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        .question-nav-btn {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #e3e6f0;
            border-radius: 5px;
            background: white;
            cursor: pointer;
        }
        .question-nav-btn.answered {
            background: #4e73df;
            color: white;
        }
        .btn-finish {
            background: #1cc88a;
            color: white;
            border: none;
            padding: 10px 30px;
            border-radius: 5px;
            font-weight: bold;
        }
        .btn-finish:hover {
            background: #169b6b;
        }
    </style>
</head>
<body>
    <div class="exam-container">
        <div class="exam-header">
            <div class="d-flex justify-content-between align-items-center">
                <h3><?php echo htmlspecialchars($exam['title']); ?></h3>
                <div class="timer" id="timer">
                    Kalan Süre: <span id="remaining-time"></span>
                </div>
            </div>
        </div>
        
        <div class="exam-body">
            <form id="examForm" method="POST" onsubmit="return confirm('Sınavı bitirmek istediğinizden emin misiniz?');">
                <!-- Soru Navigasyonu -->
                <div class="question-nav" id="questionNav">
                    <?php foreach ($questions as $index => $question): ?>
                        <div class="question-nav-btn" 
                             data-question="<?php echo $index + 1; ?>"
                             onclick="scrollToQuestion(<?php echo $index + 1; ?>)">
                            <?php echo $index + 1; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Sorular -->
                <?php foreach ($questions as $index => $question): ?>
                    <div class="question-card" id="question<?php echo $index + 1; ?>">
                        <div class="question-text">
                            <strong>Soru <?php echo $index + 1; ?>:</strong>
                            <?php echo htmlspecialchars($question['question_text']); ?>
                        </div>
                        
                        <div class="options">
                            <?php
                            $options = [
                                'a' => $question['option_a'],
                                'b' => $question['option_b'],
                                'c' => $question['option_c'],
                                'd' => $question['option_d']
                            ];
                            foreach ($options as $key => $value):
                            ?>
                                <div class="option">
                                    <input type="radio" 
                                           id="q<?php echo $question['id']; ?>_<?php echo $key; ?>"
                                           name="answers[<?php echo $question['id']; ?>]"
                                           value="<?php echo $key; ?>"
                                           <?php echo ($question['given_answer'] == $key) ? 'checked' : ''; ?>
                                           class="d-none">
                                    <label class="option-label" 
                                           for="q<?php echo $question['id']; ?>_<?php echo $key; ?>">
                                        <?php echo strtoupper($key); ?>) <?php echo htmlspecialchars($value); ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div class="text-center mt-4">
                    <button type="submit" name="finish_exam" class="btn btn-finish">
                        <i class="bi bi-check-circle"></i> Sınavı Bitir
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Kalan süre kontrolü
        let remainingSeconds = <?php echo $remaining_seconds; ?>;
        const timerElement = document.getElementById('remaining-time');
        
        function updateTimer() {
            const minutes = Math.floor(remainingSeconds / 60);
            const seconds = remainingSeconds % 60;
            timerElement.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
            
            if (remainingSeconds <= 0) {
                document.getElementById('examForm').submit();
            } else {
                remainingSeconds--;
            }
        }

        setInterval(updateTimer, 1000);
        updateTimer();

        // Soru navigasyonu
        function scrollToQuestion(questionNumber) {
            const element = document.getElementById('question' + questionNumber);
            element.scrollIntoView({ behavior: 'smooth' });
        }

        // Cevaplanan soruları işaretle
        document.querySelectorAll('input[type="radio"]').forEach(radio => {
            radio.addEventListener('change', function() {
                updateQuestionNav();
            });
        });

        function updateQuestionNav() {
            const questions = document.querySelectorAll('.question-card');
            questions.forEach((question, index) => {
                const questionNumber = index + 1;
                const answered = question.querySelector('input[type="radio"]:checked');
                const navBtn = document.querySelector(`.question-nav-btn[data-question="${questionNumber}"]`);
                
                if (answered) {
                    navBtn.classList.add('answered');
                } else {
                    navBtn.classList.remove('answered');
                }
            });
        }

        // Sayfa yüklendiğinde cevaplanan soruları işaretle
        updateQuestionNav();
    </script>
</body>
</html>