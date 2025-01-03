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
    // Aktif sınavları getir
    $stmt = $db->prepare("
        SELECT e.*, se.status as student_status
        FROM exams e
        LEFT JOIN student_exams se ON e.id = se.exam_id AND se.user_id = ?
        WHERE e.status = 'active' 
        AND e.end_date >= NOW()
        AND (se.status IS NULL OR se.status != 'completed')
        ORDER BY e.start_date ASC
    ");
    $stmt->execute([$user_id]);
    $active_exams = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Yaklaşan sınavları getir
    $stmt = $db->prepare("
        SELECT e.*
        FROM exams e
        WHERE e.status = 'active' 
        AND e.start_date > NOW()
        ORDER BY e.start_date ASC
    ");
    $stmt->execute();
    $upcoming_exams = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    die("Bir hata oluştu: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sınavlarım - Öğrenci Paneli</title>
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
        .exam-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.05);
            padding: 20px;
            margin-bottom: 20px;
            transition: transform 0.2s;
        }
        .exam-card:hover {
            transform: translateY(-5px);
        }
        .time-info {
            color: #858796;
            font-size: 0.9rem;
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
                        <h1 class="h3">Sınavlarım</h1>
                    </div>
                </div>

                <!-- Aktif Sınavlar -->
                <div class="content-container">
                    <h4 class="section-title">Aktif Sınavlar</h4>
                    <?php if ($active_exams): ?>
                        <div class="row">
                            <?php foreach ($active_exams as $exam): ?>
                                <div class="col-md-6">
                                    <div class="exam-card">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h5 class="mb-2"><?php echo htmlspecialchars($exam['title']); ?></h5>
                                                <div class="mb-2">
                                                    <span class="badge bg-primary">
                                                        <?php echo htmlspecialchars($exam['subject']); ?>
                                                    </span>
                                                </div>
                                            </div>
                                            <?php if ($exam['student_status'] == 'completed'): ?>
                                                <span class="badge bg-success">Tamamlandı</span>
                                            <?php else: ?>
                                                <a href="take_exam.php?id=<?php echo $exam['id']; ?>" class="btn btn-primary btn-sm">
                                                    Sınava Başla
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                        <div class="mt-3">
                                            <div class="time-info">
                                                <i class="bi bi-clock me-2"></i>
                                                Süre: <?php echo $exam['duration']; ?> dakika
                                            </div>
                                            <div class="time-info">
                                                <i class="bi bi-calendar me-2"></i>
                                                Bitiş: <?php echo date('d.m.Y H:i', strtotime($exam['end_date'])); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">Aktif sınav bulunmamaktadır.</div>
                    <?php endif; ?>
                </div>

                <!-- Yaklaşan Sınavlar -->
                <div class="content-container">
                    <h4 class="section-title">Yaklaşan Sınavlar</h4>
                    <?php if ($upcoming_exams): ?>
                        <div class="row">
                            <?php foreach ($upcoming_exams as $exam): ?>
                                <div class="col-md-6">
                                    <div class="exam-card">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h5 class="mb-2"><?php echo htmlspecialchars($exam['title']); ?></h5>
                                                <div class="mb-2">
                                                    <span class="badge bg-primary">
                                                        <?php echo htmlspecialchars($exam['subject']); ?>
                                                    </span>
                                                </div>
                                            </div>
                                            <span class="badge bg-warning text-dark">Yaklaşan</span>
                                        </div>
                                        <div class="mt-3">
                                            <div class="time-info">
                                                <i class="bi bi-clock me-2"></i>
                                                Süre: <?php echo $exam['duration']; ?> dakika
                                            </div>
                                            <div class="time-info">
                                                <i class="bi bi-calendar me-2"></i>
                                                Başlangıç: <?php echo date('d.m.Y H:i', strtotime($exam['start_date'])); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">Yaklaşan sınav bulunmamaktadır.</div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>