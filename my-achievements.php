<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

// Öğrenci kontrolü
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

// Kazanılan rozetleri getir
$stmt = $db->prepare("
    SELECT 
        a.*,
        ua.earned_date
    FROM achievements a
    LEFT JOIN user_achievements ua ON a.id = ua.achievement_id 
        AND ua.user_id = ?
    ORDER BY 
        CASE WHEN ua.id IS NOT NULL THEN 0 ELSE 1 END,
        ua.earned_date DESC
");
$stmt->execute([$_SESSION['user_id']]);
$achievements = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Başarı Rozetlerim</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1>Başarı Rozetlerim</h1>
                </div>

                <!-- Rozetler -->
                <div class="row">
                    <?php foreach ($achievements as $achievement): ?>
                        <div class="col-md-4 mb-4">
                            <div class="card h-100 <?php echo $achievement['earned_date'] ? '' : 'opacity-50'; ?>">
                                <div class="card-body text-center">
                                    <i class="bi <?php echo $achievement['icon']; ?> display-1 mb-3 
                                        <?php echo $achievement['earned_date'] ? 'text-warning' : 'text-muted'; ?>">
                                    </i>
                                    <h5 class="card-title">
                                        <?php echo htmlspecialchars($achievement['name']); ?>
                                    </h5>
                                    <p class="card-text">
                                        <?php echo htmlspecialchars($achievement['description']); ?>
                                    </p>
                                    <?php if ($achievement['earned_date']): ?>
                                        <div class="alert alert-success">
                                            <i class="bi bi-check-circle me-2"></i>
                                            <?php echo date('d.m.Y', strtotime($achievement['earned_date'])); ?> 
                                            tarihinde kazanıldı
                                        </div>
                                    <?php else: ?>
                                        <div class="alert alert-secondary">
                                            <i class="bi bi-lock me-2"></i>
                                            Henüz kazanılmadı
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </main>
        </div>
    </div>
</body>
</html>