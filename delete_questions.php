<?php
session_start();
require_once 'config.php';

// Admin kontrolü
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// ID kontrolü
if (!isset($_GET['id'])) {
    $_SESSION['error'] = "Geçersiz soru ID'si.";
    header("Location: question_bank.php");
    exit();
}

$question_id = $_GET['id'];

try {
    // Önce sorunun varlığını kontrol et
    $stmt = $db->prepare("SELECT id FROM questions WHERE id = ?");
    $stmt->execute([$question_id]);
    if (!$stmt->fetch()) {
        throw new Exception("Soru bulunamadı.");
    }

    // Sınavlarda kullanım durumunu kontrol et
    $stmt = $db->prepare("
        SELECT e.id, e.title, 
               COUNT(DISTINCT er.id) as result_count
        FROM exam_questions eq
        JOIN exams e ON eq.exam_id = e.id
        LEFT JOIN exam_results er ON e.id = er.exam_id
        WHERE eq.question_id = ?
        GROUP BY e.id
    ");
    $stmt->execute([$question_id]);
    $exam_usage = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Eğer soru sınavlarda kullanılmışsa ve sonuçlar varsa, silmeye izin verme
    if (!empty($exam_usage)) {
        $usage_details = [];
        $total_results = 0;
        
        foreach ($exam_usage as $usage) {
            $usage_details[] = sprintf(
                "'%s' sınavında (%d sonuç)", 
                $usage['title'], 
                $usage['result_count']
            );
            $total_results += $usage['result_count'];
        }

        if ($total_results > 0) {
            // Arşivleme seçeneği sun
            if (isset($_POST['archive'])) {
                // Soruyu arşivle (status = 0)
                $stmt = $db->prepare("
                    UPDATE questions 
                    SET status = 0, 
                        archived_by = ?, 
                        archived_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$_SESSION['user_id'], $question_id]);

                $_SESSION['success'] = "Soru başarıyla arşivlendi.";
                header("Location: question_bank.php");
                exit();
            }

            // Arşivleme onay formu göster
            ?>
            <!DOCTYPE html>
            <html lang="tr">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Soru Arşivleme</title>
                <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
                <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
                <style>
                    .archive-container {
                        max-width: 600px;
                        margin: 50px auto;
                        background: white;
                        padding: 30px;
                        border-radius: 15px;
                        box-shadow: 0 0 20px rgba(0,0,0,0.1);
                    }
                    .warning-icon {
                        font-size: 48px;
                        color: #f6c23e;
                        margin-bottom: 20px;
                    }
                    .usage-list {
                        background: #f8f9fc;
                        padding: 15px;
                        border-radius: 10px;
                        margin: 15px 0;
                    }
                    .usage-item {
                        padding: 8px 0;
                        border-bottom: 1px solid #e3e6f0;
                    }
                    .usage-item:last-child {
                        border-bottom: none;
                    }
                </style>
            </head>
            <body class="bg-light">
                <div class="archive-container">
                    <div class="text-center mb-4">
                        <i class="bi bi-exclamation-triangle warning-icon"></i>
                        <h4>Soru Silinemez</h4>
                    </div>

                    <div class="alert alert-warning">
                        Bu soru aktif olarak kullanımda olduğu için silinemiyor. 
                        Bunun yerine arşivlemeyi tercih edebilirsiniz.
                    </div>

                    <div class="usage-list">
                        <h6 class="mb-3">Kullanım Detayları:</h6>
                        <?php foreach ($usage_details as $detail): ?>
                            <div class="usage-item">
                                <i class="bi bi-journal-text me-2"></i>
                                <?php echo htmlspecialchars($detail); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="mt-4">
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="archive" value="1">
                            <button type="submit" class="btn btn-warning">
                                <i class="bi bi-archive"></i> Arşivle
                            </button>
                        </form>
                        <a href="question_bank.php" class="btn btn-secondary ms-2">
                            <i class="bi bi-arrow-left"></i> Geri Dön
                        </a>
                    </div>
                </div>
            </body>
            </html>
            <?php
            exit();
        }
    }

    // Soru silinebilir durumdaysa, silme işlemini gerçekleştir
    $db->beginTransaction();

    // Önce sınav-soru ilişkilerini sil
    $stmt = $db->prepare("DELETE FROM exam_questions WHERE question_id = ?");
    $stmt->execute([$question_id]);

    // Soru seçeneklerini sil
    $stmt = $db->prepare("DELETE FROM question_options WHERE question_id = ?");
    $stmt->execute([$question_id]);

    // Son olarak soruyu sil
    $stmt = $db->prepare("DELETE FROM questions WHERE id = ?");
    $stmt->execute([$question_id]);

    $db->commit();
    
    $_SESSION['success'] = "Soru başarıyla silindi.";

} catch (Exception $e) {
    if (isset($db)) {
        $db->rollBack();
    }
    $_SESSION['error'] = "Soru silinirken bir hata oluştu: " . $e->getMessage();
}

header("Location: question_bank.php");
exit();
?>