<?php
session_start();
require_once 'config.php';
require 'vendor/autoload.php'; // PhpSpreadsheet kütüphanesi için

use PhpOffice\PhpSpreadsheet\IOFactory;

// Admin kontrolü
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Hata ve başarı mesajları için dizi
$messages = [
    'errors' => [],
    'success' => []
];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['excelFile'])) {
    $file = $_FILES['excelFile'];
    
    // Dosya kontrolü
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['error'] = "Dosya yüklenirken bir hata oluştu.";
        header("Location: question_bank.php");
        exit();
    }

    // Dosya uzantısı kontrolü
    $allowed_extensions = ['xlsx', 'xls'];
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($file_extension, $allowed_extensions)) {
        $_SESSION['error'] = "Sadece Excel dosyaları (.xlsx, .xls) yüklenebilir.";
        header("Location: question_bank.php");
        exit();
    }

    try {
        // Excel dosyasını oku
        $spreadsheet = IOFactory::load($file['tmp_name']);
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();
        
        // Başlık satırını kontrol et
        $headers = array_shift($rows); // İlk satırı başlık olarak al
        $required_headers = ['question_text', 'option_a', 'option_b', 'option_c', 'option_d', 'correct_answer', 'category', 'level', 'difficulty', 'points', 'tags', 'explanation'];
        
        foreach ($required_headers as $header) {
            if (!in_array($header, $headers)) {
                throw new Exception("Gerekli sütun başlığı eksik: $header");
            }
        }

        // Veritabanı işlemi başlat
        $db->beginTransaction();
        
        // Başarılı ve başarısız satır sayıları
        $success_count = 0;
        $error_count = 0;

        // Her satır için işlem yap
        foreach ($rows as $row_index => $row) {
            try {
                $row = array_combine($headers, $row);
                
                // Zorunlu alanları kontrol et
                if (empty($row['question_text']) || empty($row['option_a']) || empty($row['option_b']) || 
                    empty($row['option_c']) || empty($row['option_d']) || empty($row['correct_answer'])) {
                    throw new Exception("Zorunlu alanlar boş bırakılamaz.");
                }

                // Kategori kontrolü ve ID'sini al
                $stmt = $db->prepare("SELECT id FROM categories WHERE name = ?");
                $stmt->execute([$row['category']]);
                $category_id = $stmt->fetchColumn();
                
                if (!$category_id) {
                    throw new Exception("Kategori bulunamadı: " . $row['category']);
                }

                // Seviye kontrolü ve ID'sini al
                $stmt = $db->prepare("SELECT id FROM levels WHERE name = ?");
                $stmt->execute([$row['level']]);
                $level_id = $stmt->fetchColumn();
                
                if (!$level_id) {
                    throw new Exception("Seviye bulunamadı: " . $row['level']);
                }

                // Zorluk seviyesi kontrolü
                $difficulty = strtolower($row['difficulty']);
                if (!in_array($difficulty, ['easy', 'medium', 'hard'])) {
                    throw new Exception("Geçersiz zorluk seviyesi. (easy, medium, hard olmalı)");
                }

                // Doğru cevap kontrolü
                $correct_answer = strtoupper($row['correct_answer']);
                if (!in_array($correct_answer, ['A', 'B', 'C', 'D'])) {
                    throw new Exception("Geçersiz doğru cevap. (A, B, C, D olmalı)");
                }

                // Puan kontrolü
                $points = intval($row['points']);
                if ($points < 1) {
                    $points = 10; // Varsayılan puan
                }

                // Soruyu ekle
                $stmt = $db->prepare("
                    INSERT INTO questions (
                        question_text, category_id, level_id, difficulty,
                        points, tags, explanation, created_by, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                
                $stmt->execute([
                    $row['question_text'],
                    $category_id,
                    $level_id,
                    $difficulty,
                    $points,
                    $row['tags'],
                    $row['explanation'],
                    $_SESSION['user_id']
                ]);

                $question_id = $db->lastInsertId();

                // Şıkları ekle
                $options = [
                    'A' => $row['option_a'],
                    'B' => $row['option_b'],
                    'C' => $row['option_c'],
                    'D' => $row['option_d']
                ];

                $stmt = $db->prepare("
                    INSERT INTO question_options (
                        question_id, option_text, is_correct
                    ) VALUES (?, ?, ?)
                ");

                foreach ($options as $letter => $option_text) {
                    $is_correct = ($letter === $correct_answer) ? 1 : 0;
                    $stmt->execute([$question_id, $option_text, $is_correct]);
                }

                $success_count++;
                $messages['success'][] = "Satır " . ($row_index + 2) . ": Soru başarıyla eklendi.";

            } catch (Exception $e) {
                $error_count++;
                $messages['errors'][] = "Satır " . ($row_index + 2) . ": " . $e->getMessage();
                continue;
            }
        }

        $db->commit();

        // Sonuç mesajını oluştur
        $_SESSION['import_result'] = [
            'total' => count($rows),
            'success' => $success_count,
            'error' => $error_count,
            'messages' => $messages
        ];

    } catch (Exception $e) {
        $db->rollBack();
        $_SESSION['error'] = "Dosya işlenirken bir hata oluştu: " . $e->getMessage();
    }

    header("Location: import_result.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Soru İçe Aktarma</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        .import-container {
            max-width: 800px;
            margin: 50px auto;
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }

        .instruction-card {
            background: #f8f9fc;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .instruction-step {
            margin-bottom: 15px;
            padding-left: 30px;
            position: relative;
        }

        .step-number {
            position: absolute;
            left: 0;
            width: 24px;
            height: 24px;
            background: #4e73df;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
        }
    </style>
</head>
<body class="bg-light">
    <div class="import-container">
        <h2 class="mb-4">Excel'den Soru İçe Aktarma</h2>

        <div class="instruction-card">
            <h5 class="mb-3">İçe Aktarma Talimatları</h5>
            
            <div class="instruction-step">
                <div class="step-number">1</div>
                <p>Şablon dosyasını indirin ve örnek formatı inceleyin.</p>
            </div>
            
            <div class="instruction-step">
                <div class="step-number">2</div>
                <p>Tüm zorunlu alanları doldurun (soru metni, şıklar, doğru cevap, kategori, seviye).</p>
            </div>
            
            <div class="instruction-step">
                <div class="step-number">3</div>
                <p>Doğru cevap için A, B, C veya D harflerini kullanın.</p>
            </div>
            
            <div class="instruction-step">
                <div class="step-number">4</div>
                <p>Zorluk seviyesi için easy, medium veya hard kullanın.</p>
            </div>
            
            <div class="instruction-step">
                <div class="step-number">5</div>
                <p>Etiketleri virgülle ayırarak yazın (ör: matematik,toplama,sayılar).</p>
            </div>
        </div>

        <form action="" method="POST" enctype="multipart/form-data">
            <div class="mb-3">
                <label for="excelFile" class="form-label">Excel Dosyası</label>
                <input type="file" class="form-control" id="excelFile" name="excelFile" 
                       accept=".xlsx,.xls" required>
                <div class="form-text">Sadece .xlsx ve .xls dosyaları kabul edilir.</div>
            </div>

            <div class="d-flex justify-content-between align-items-center">
                <a href="templates/questions_template.xlsx" class="btn btn-outline-primary">
                    <i class="bi bi-download"></i> Şablon İndir
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-upload"></i> İçe Aktar
                </button>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>