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
    <title>Sınav Yazdırma - <?php echo htmlspecialchars($exam['title']); ?></title>
    <style>
        @media print {
            @page {
                size: A4;
                margin: 1cm;
            }
            
            body {
                margin: 0;
                padding: 0;
                font-family: Arial, sans-serif;
                font-size: 12pt;
                line-height: 1.4;
            }

            .no-print {
                display: none !important;
            }

            .page-break {
                page-break-before: always;
            }
        }

        body {
            font-family: Arial, sans-serif;
            line-height: 1.4;
            margin: 0;
            padding: 20px;
            background: #f0f0f0;
        }

        .print-container {
            max-width: 210mm;
            margin: 0 auto;
            background: white;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }

        .exam-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #000;
        }

        .exam-info {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-bottom: 20px;
            font-size: 11pt;
        }

        .exam-info-item {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            border-bottom: 1px dotted #ccc;
        }

        .student-info {
            margin: 20px 0;
            padding: 15px;
            border: 1px solid #000;
        }

        .student-info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }

        .student-field {
            border-bottom: 1px solid #000;
            padding: 5px 0;
        }

        .question {
            margin-bottom: 25px;
            page-break-inside: avoid;
        }

        .question-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-weight: bold;
        }

        .options {
            margin-left: 20px;
        }

        .option {
            margin-bottom: 8px;
            display: flex;
            align-items: flex-start;
        }

        .option-letter {
            min-width: 25px;
            font-weight: bold;
        }

        .answer-key {
            page-break-before: always;
        }

        .answer-key table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .answer-key th, .answer-key td {
            border: 1px solid #000;
            padding: 8px;
            text-align: center;
        }

        .answer-key th {
            background: #f0f0f0;
        }

        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 10px 20px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        }

        .print-button:hover {
            background: #0056b3;
        }
    </style>
</head>
<body>
    <button onclick="window.print()" class="print-button no-print">Yazdır</button>

    <div class="print-container">
        <!-- Sınav Başlığı -->
        <div class="exam-header">
            <h1><?php echo htmlspecialchars($exam['title']); ?></h1>
            <div class="exam-info">
                <div class="exam-info-item">
                    <span>Kategori:</span>
                    <span><?php echo htmlspecialchars($exam['category_name']); ?></span>
                </div>
                <div class="exam-info-item">
                    <span>Seviye:</span>
                    <span><?php echo htmlspecialchars($exam['level_name']); ?></span>
                </div>
                <div class="exam-info-item">
                    <span>Süre:</span>
                    <span><?php echo $exam['duration']; ?> Dakika</span>
                </div>
                <div class="exam-info-item">
                    <span>Toplam Puan:</span>
                    <span><?php echo $total_points; ?></span>
                </div>
            </div>
        </div>

        <!-- Öğrenci Bilgileri -->
        <div class="student-info">
            <div class="student-info-grid">
                <div class="student-field">
                    Adı Soyadı: _________________________________
                </div>
                <div class="student-field">
                    Öğrenci No: _________________________________
                </div>
                <div class="student-field">
                    Tarih: _________________________________
                </div>
                <div class="student-field">
                    İmza: _________________________________
                </div>
            </div>
        </div>

        <!-- Sınav Soruları -->
        <?php foreach ($questions as $index => $question): ?>
            <div class="question">
                <div class="question-header">
                    <span>Soru <?php echo $index + 1; ?></span>
                    <span><?php echo $question['points']; ?> Puan</span>
                </div>
                <div class="question-text">
                    <?php echo $question['question_text']; ?>
                </div>
                <div class="options">
                    <?php 
                    $options = explode(',', $question['options']);
                    foreach ($options as $opt_index => $option): 
                    ?>
                        <div class="option">
                            <span class="option-letter"><?php echo chr(65 + $opt_index); ?>)</span>
                            <span><?php echo htmlspecialchars($option); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <!-- Cevap Anahtarı (Yeni Sayfa) -->
        <div class="answer-key">
            <h2>Cevap Anahtarı</h2>
            <table>
                <tr>
                    <th>Soru No</th>
                    <th>Doğru Cevap</th>
                    <th>Puan</th>
                </tr>
                <?php foreach ($questions as $index => $question): ?>
                    <tr>
                        <td><?php echo $index + 1; ?></td>
                        <td>
                            <?php 
                            $correct_answers = explode(',', $question['correct_answers']);
                            $correct_index = array_search('1', $correct_answers);
                            echo chr(65 + $correct_index);
                            ?>
                        </td>
                        <td><?php echo $question['points']; ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>

    <script>
        // Sayfa yüklendiğinde otomatik yazdırma diyaloğunu aç
        window.onload = function() {
            // URL'de print parametresi varsa otomatik yazdır
            if (window.location.search.includes('autoprint')) {
                window.print();
            }
        };
    </script>
</body>
</html>