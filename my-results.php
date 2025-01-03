<?php
session_start();
require_once 'config.php';

// Öğrenci kontrolü
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

try {
    // Tüm sınav sonuçlarını getir
    $stmt = $db->prepare("
        SELECT 
            er.*,
            e.title as exam_title,
            e.subject,
            e.duration
        FROM student_exams er
        JOIN exams e ON er.exam_id = e.id
        WHERE er.user_id = ? AND er.status = 'completed'
        ORDER BY er.end_time DESC
    ");
    $stmt->execute([$user_id]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // İstatistikler
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_exams,
            COUNT(CASE WHEN score >= 60 THEN 1 END) as passed_exams,
            ROUND(AVG(score), 1) as average_score,
            SUM(correct_answers) as total_correct,
            SUM(wrong_answers) as total_wrong
        FROM student_exams
        WHERE user_id = ? AND status = 'completed'
    ");
    $stmt->execute([$user_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    die("Bir hata oluştu: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sınav Sonuçlarım - Öğrenci Paneli</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fc;
        }
        .content-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        .section-title {
            color: #4e73df;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e3e6f0;
        }
        .stat-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .stat-label {
            color: #858796;
            font-size: 0.875rem;
        }
        .result-item {
            border-bottom: 1px solid #e3e6f0;
            padding: 15px 0;
        }
        .result-item:last-child {
            border-bottom: none;
        }
        .score-badge {
            font-size: 1.1rem;
            padding: 8px 15px;
            border-radius: 20px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'user-sidebar.php'; ?>

            <!-- Ana İçerik -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <!-- Başlık -->
                <div class="content-container mt-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <h1 class="h3">Sınav Sonuçlarım</h1>
                    </div>
                </div>

                <!-- İstatistikler -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="stat-value"><?php echo $stats['total_exams']; ?></div>
                                    <div class="stat-label">Toplam Sınav</div>
                                </div>
                                <i class="bi bi-journal-text text-primary" style="font-size: 2rem;"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="stat-value"><?php echo $stats['passed_exams']; ?></div>
                                    <div class="stat-label">Başarılı Sınav</div>
                                </div>
                                <i class="bi bi-check-circle text-success" style="font-size: 2rem;"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="stat-value"><?php echo $stats['average_score']; ?>%</div>
                                    <div class="stat-label">Ortalama Başarı</div>
                                </div>
                                <i class="bi bi-graph-up text-info" style="font-size: 2rem;"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="stat-value"><?php echo $stats['total_correct']; ?></div>
                                    <div class="stat-label">Toplam Doğru</div>
                                </div>
                                <i class="bi bi-patch-check text-success" style="font-size: 2rem;"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sonuç Listesi -->
                <div class="content-container">
                    <h4 class="section-title">Tüm Sonuçlar</h4>
                    <?php if ($results): ?>
                        <?php foreach ($results as $result): ?>
                            <div class="result-item">
                                <div class="row align-items-center">
                                    <div class="col-md-6">
                                        <h5 class="mb-1"><?php echo htmlspecialchars($result['exam_title']); ?></h5>
                                        <div class="mb-2">
                                            <span class="badge bg-primary">
                                                <?php echo htmlspecialchars($result['subject']); ?>
                                            </span>
                                        </div>
                                        <small class="text-muted">
                                            <i class="bi bi-calendar me-1"></i>
                                            <?php echo date('d.m.Y H:i', strtotime($result['end_time'])); ?>
                                        </small>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="text-muted">
                                            <small>Doğru: <?php echo $result['correct_answers']; ?> / 
                                                   Yanlış: <?php echo $result['wrong_answers']; ?></small>
                                        </div>
                                    </div>
                                    <div class="col-md-3 text-end">
                                        <span class="score-badge <?php 
                                            echo $result['score'] >= 80 ? 'bg-success' : 
                                                 ($result['score'] >= 60 ? 'bg-warning' : 'bg-danger'); 
                                        ?> text-white">
                                            <?php echo $result['score']; ?>%
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="alert alert-info">Henüz sınav sonucunuz bulunmamaktadır.</div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>