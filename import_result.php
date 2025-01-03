<?php
session_start();

// Admin kontrolü
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// İçe aktarma sonuçlarını kontrol et
if (!isset($_SESSION['import_result'])) {
    header("Location: question_bank.php");
    exit();
}

$result = $_SESSION['import_result'];
unset($_SESSION['import_result']); // Sonuçları temizle
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>İçe Aktarma Sonuçları</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        .result-container {
            max-width: 800px;
            margin: 50px auto;
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }

        .result-summary {
            background: #f8f9fc;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            text-align: center;
        }

        .summary-item {
            display: inline-block;
            margin: 0 20px;
        }

        .summary-value {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .message-list {
            max-height: 400px;
            overflow-y: auto;
        }

        .message-item {
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 5px;
        }

        .message-success {
            background: #1cc88a20;
            color: #1cc88a;
        }

        .message-error {
            background: #e74a3b20;
            color: #e74a3b;
        }
    </style>
</head>
<body class="bg-light">
    <div class="result-container">
        <h2 class="mb-4">İçe Aktarma Sonuçları</h2>

        <div class="result-summary">
            <div class="summary-item">
                <div class="summary-value"><?php echo $result['total']; ?></div>
                <div class="summary-label">Toplam Satır</div>
            </div>
            <div class="summary-item">
                <div class="summary-value text-success"><?php echo $result['success']; ?></div>
                <div class="summary-label">Başarılı</div>
            </div>
            <div class="summary-item">
                <div class="summary-value text-danger"><?php echo $result['error']; ?></div>
                <div class="summary-label">Başarısız</div>
            </div>
        </div>

        <?php if (!empty($result['messages']['success'])): ?>
            <h5 class="mb-3">Başarılı İşlemler</h5>
            <div class="message-list">
                <?php foreach ($result['messages']['success'] as $message): ?>
                    <div class="message-item message-success">
                        <i class="bi bi-check-circle"></i> <?php echo $message; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($result['messages']['errors'])): ?>
            <h5 class="mb-3 mt-4">Hatalar</h5>
            <div class="message-list">
                <?php foreach ($result['messages']['errors'] as $message): ?>
                    <div class="message-item message-error">
                        <i class="bi bi-exclamation-circle"></i> <?php echo $message; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="mt-4 text-center">
            <a href="question_bank.php" class="btn btn-primary">
                <i class="bi bi-arrow-left"></i> Soru Bankasına Dön
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>