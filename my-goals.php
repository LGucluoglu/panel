<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

// Öğrenci kontrolü
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

// Hedef ekleme/güncelleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $target_exam = $_POST['target_exam'];
    $target_score = $_POST['target_score'];
    $target_date = $_POST['target_date'];
    $notes = $_POST['notes'];

    $stmt = $db->prepare("
        INSERT INTO user_goals (
            user_id, target_exam, target_score, 
            target_date, notes, status
        ) VALUES (?, ?, ?, ?, ?, 'active')
        ON DUPLICATE KEY UPDATE
            target_score = VALUES(target_score),
            target_date = VALUES(target_date),
            notes = VALUES(notes)
    ");

    $stmt->execute([
        $_SESSION['user_id'],
        $target_exam,
        $target_score,
        $target_date,
        $notes
    ]);

    header("Location: my-goals.php?success=1");
    exit();
}

// Mevcut hedefi getir
$stmt = $db->prepare("
    SELECT * FROM user_goals 
    WHERE user_id = ? AND status = 'active'
");
$stmt->execute([$_SESSION['user_id']]);
$current_goal = $stmt->fetch(PDO::FETCH_ASSOC);

// İlerleme durumunu hesapla
if ($current_goal) {
    $stmt = $db->prepare("
        SELECT AVG(score) as avg_score
        FROM student_exams
        WHERE user_id = ? AND status = 'completed'
        AND created_at >= ?
    ");
    $stmt->execute([$_SESSION['user_id'], $current_goal['created_at']]);
    $progress = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hedeflerim</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1>Hedeflerim</h1>
                </div>

                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        Hedefiniz başarıyla kaydedildi.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Mevcut Hedef Kartı -->
                <?php if ($current_goal): ?>
                    <div class="card mb-4">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-8">
                                    <h4 class="card-title mb-4">Mevcut Hedefim</h4>
                                    <div class="d-flex align-items-center mb-3">
                                        <i class="bi bi-trophy fs-1 me-3 text-warning"></i>
                                        <div>
                                            <h5><?php echo htmlspecialchars($current_goal['target_exam']); ?></h5>
                                            <p class="text-muted mb-0">
                                                Hedef: %<?php echo $current_goal['target_score']; ?> |
                                                Tarih: <?php echo date('d.m.Y', strtotime($current_goal['target_date'])); ?>
                                            </p>
                                        </div>
                                    </div>
                                    <?php if ($current_goal['notes']): ?>
                                        <div class="alert alert-info">
                                            <?php echo nl2br(htmlspecialchars($current_goal['notes'])); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-4">
                                    <div class="text-center">
                                        <h6 class="mb-3">İlerleme Durumu</h6>
                                        <div class="progress-circle">
                                            <div class="progress" style="height: 150px; width: 150px;">
                                                <?php
                                                $percentage = min(100, ($progress['avg_score'] / $current_goal['target_score']) * 100);
                                                $color = $percentage >= 100 ? 'success' : ($percentage >= 70 ? 'warning' : 'danger');
                                                ?>
                                                <div class="progress-bar bg-<?php echo $color; ?>"
                                                     role="progressbar"
                                                     style="width: <?php echo $percentage; ?>%"
                                                     aria-valuenow="<?php echo $percentage; ?>"
                                                     aria-valuemin="0"
                                                     aria-valuemax="100">
                                                    <?php echo number_format($percentage, 1); ?>%
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Hedef Belirleme Formu -->
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title mb-4">
                            <?php echo $current_goal ? 'Hedefimi Güncelle' : 'Yeni Hedef Belirle'; ?>
                        </h4>
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Hedef Sınav</label>
                                        <input type="text" class="form-control" name="target_exam" 
                                               value="<?php echo htmlspecialchars($current_goal['target_exam'] ?? ''); ?>" 
                                               required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Hedef Puan (%)</label>
                                        <input type="number" class="form-control" name="target_score" 
                                               value="<?php echo $current_goal['target_score'] ?? ''; ?>" 
                                               min="0" max="100" required>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Hedef Tarih</label>
                                <input type="date" class="form-control" name="target_date" 
                                       value="<?php echo $current_goal['target_date'] ?? ''; ?>" 
                                       required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Notlar</label>
                                <textarea class="form-control" name="notes" rows="3"><?php 
                                    echo htmlspecialchars($current_goal['notes'] ?? ''); 
                                ?></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <?php echo $current_goal ? 'Hedefi Güncelle' : 'Hedefi Kaydet'; ?>
                            </button>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>