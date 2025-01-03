<?php
session_start();
require_once 'config.php';

// Öğrenci girişi kontrolü
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

// Kullanıcı bilgilerini veritabanından çek
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Kullanıcı bulunamazsa çıkış yap
if (!$user) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// Profil tamamlanmamışsa yönlendir
if (!$user['profile_completed']) {
    header("Location: complete-profile.php");
    exit();
}

// Yaklaşan sınavları çek
$stmt = $db->prepare("
    SELECT e.*, se.status as student_status
    FROM exams e
    LEFT JOIN student_exams se ON e.id = se.exam_id AND se.user_id = ?
    WHERE e.status = 'active' 
    AND e.end_date >= CURDATE()
    AND (se.status IS NULL OR se.status != 'completed')
    ORDER BY e.start_date ASC, e.start_time ASC
    LIMIT 3
");
$stmt->execute([$_SESSION['user_id']]);
$upcoming_exams = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Son tamamlanan sınavları çek
$stmt = $db->prepare("
    SELECT e.title, e.subject, se.score, se.end_time, e.id
    FROM student_exams se
    JOIN exams e ON e.id = se.exam_id
    WHERE se.user_id = ? AND se.status = 'completed'
    ORDER BY se.end_time DESC
    LIMIT 3
");
$stmt->execute([$_SESSION['user_id']]);
$recent_exams = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Genel istatistikleri çek
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_exams,
        AVG(score) as average_score,
        SUM(correct_answers) as total_correct,
        SUM(wrong_answers) as total_wrong
    FROM student_exams
    WHERE user_id = ? AND status = 'completed'
");
$stmt->execute([$_SESSION['user_id']]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Ders bazlı başarı oranlarını çek
$stmt = $db->prepare("
    SELECT 
        e.subject,
        AVG(se.score) as avg_score
    FROM student_exams se
    JOIN exams e ON e.id = se.exam_id
    WHERE se.user_id = ? AND se.status = 'completed'
    GROUP BY e.subject
    ORDER BY avg_score DESC
    LIMIT 4
");
$stmt->execute([$_SESSION['user_id']]);
$subject_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Öğrenci Paneli</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --success-color: #2ec4b6;
            --warning-color: #ff9f1c;
            --danger-color: #e71d36;
            --light-color: #f8f9fa;
            --dark-color: #212529;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #f0f2f5;
        }

        .dashboard-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.05);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: none;
            transition: all 0.3s ease;
        }

        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        .welcome-card {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 2.5rem;
            border-radius: 16px;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .welcome-card::after {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 300px;
            height: 100%;
            background: linear-gradient(45deg, transparent, rgba(255,255,255,0.1));
            transform: skewX(-30deg);
        }

        .stat-card {
            background: white;
            color: var(--dark-color);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid rgba(0,0,0,0.05);
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary-color);
            line-height: 1.2;
        }

        .exam-item {
            background: white;
            border-radius: 12px;
            padding: 1.25rem;
            margin-bottom: 1rem;
            border: 1px solid rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }

        .exam-item:hover {
            transform: translateX(5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid rgba(0,0,0,0.05);
        }

        .quick-action {
            text-decoration: none;
            color: var(--dark-color);
            transition: all 0.3s ease;
        }

        .quick-action .dashboard-card {
            background: linear-gradient(135deg, white, #f8f9fa);
            border: 1px solid rgba(0,0,0,0.05);
        }

        .quick-action:hover .dashboard-card {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
        }

        .score-badge {
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-weight: 600;
            font-size: 0.875rem;
        }

        .progress {
            height: 8px;
            border-radius: 4px;
            background-color: rgba(0,0,0,0.05);
        }

        .progress-bar {
            background-color: var(--primary-color);
            border-radius: 4px;
        }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            border-radius: 8px;
            padding: 0.5rem 1.25rem;
            font-weight: 500;
        }

        .btn-primary:hover {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }

        .alert {
            border-radius: 12px;
            border: none;
            padding: 1rem 1.25rem;
        }

        @media (max-width: 768px) {
            .welcome-card {
                padding: 1.5rem;
            }

            .stat-value {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'user-sidebar.php'; ?>

            <!-- Ana İçerik -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <!-- Hoş Geldin Kartı -->
                <div class="welcome-card">
                    <h2 class="mb-2">Hoş Geldin, <?php echo htmlspecialchars($user['name']); ?>!</h2>
                    <p class="mb-0 opacity-75">Bugün neler yapmak istersin?</p>
                </div>

                <!-- Hızlı Erişim -->
                <div class="row g-4 mb-4">
                    <div class="col-md-3">
                        <a href="my-exams.php" class="quick-action">
                            <div class="dashboard-card text-center">
                                <i class="bi bi-pencil-square display-4 mb-3"></i>
                                <h5>Sınava Gir</h5>
                                <p class="text-muted mb-0">Aktif sınavları görüntüle</p>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="my-results.php" class="quick-action">
                            <div class="dashboard-card text-center">
                                <i class="bi bi-graph-up display-4 mb-3"></i>
                                <h5>Sonuçlarım</h5>
                                <p class="text-muted mb-0">Sınav sonuçlarını gör</p>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="profile.php" class="quick-action">
                            <div class="dashboard-card text-center">
                                <i class="bi bi-person display-4 mb-3"></i>
                                <h5>Profilim</h5>
                                <p class="text-muted mb-0">Bilgilerini düzenle</p>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="#" class="quick-action">
                            <div class="dashboard-card text-center">
                                <i class="bi bi-question-circle display-4 mb-3"></i>
                                <h5>Yardım</h5>
                                <p class="text-muted mb-0">Destek al</p>
                            </div>
                        </a>
                    </div>
                </div>

                <div class="row">
                    <!-- Sol Sütun -->
                    <div class="col-md-8">
                        <!-- Yaklaşan Sınavlar -->
                        <div class="dashboard-card">
                            <h4 class="section-title">
                                <i class="bi bi-calendar-event me-2"></i>
                                Yaklaşan Sınavlar
                            </h4>
                            <?php if (empty($upcoming_exams)): ?>
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle me-2"></i>
                                    Şu anda katılabileceğiniz aktif sınav bulunmamaktadır.
                                </div>
                            <?php else: ?>
                                <?php foreach ($upcoming_exams as $exam): ?>
                                    <div class="exam-item">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h5 class="mb-1"><?php echo htmlspecialchars($exam['title']); ?></h5>
                                                <div class="d-flex align-items-center text-muted">
                                                    <span class="badge bg-primary me-2">
                                                        <?php echo htmlspecialchars($exam['subject']); ?>
                                                    </span>
                                                    <i class="bi bi-clock me-1"></i>
                                                    <?php echo date('d.m.Y H:i', strtotime($exam['start_date'] . ' ' . $exam['start_time'])); ?>
                                                </div>
                                            </div>
                                            <a href="my-exams.php" class="btn btn-primary">
                                                <i class="bi bi-arrow-right-circle me-1"></i>
                                                Sınava Git
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <!-- Son Sınav Sonuçları -->
                        <div class="dashboard-card">
                            <h4 class="section-title">
                                <i class="bi bi-journal-check me-2"></i>
                                Son Sınav Sonuçları
                            </h4>
                            <?php if (empty($recent_exams)): ?>
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle me-2"></i>
                                    Henüz tamamlanmış sınavınız bulunmamaktadır.
                                </div>
                            <?php else: ?>
                                <?php foreach ($recent_exams as $exam): ?>
                                    <div class="exam-item">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h5 class="mb-1"><?php echo htmlspecialchars($exam['title']); ?></h5>
                                                <div class="d-flex align-items-center text-muted">
                                                    <span class="badge bg-primary me-2">
                                                        <?php echo htmlspecialchars($exam['subject']); ?>
                                                    </span>
                                                    <i class="bi bi-calendar me-1"></i>
                                                    <?php echo date('d.m.Y', strtotime($exam['end_time'])); ?>
                                                </div>
                                            </div>
                                            <span class="score-badge <?php 
                                                echo $exam['score'] >= 80 ? 'bg-success' : 
                                                     ($exam['score'] >= 60 ? 'bg-warning' : 'bg-danger'); 
                                            ?>">
                                                <?php echo number_format($exam['score'], 1); ?>%
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                <div class="text-end mt-3">
                                    <a href="my-results.php" class="btn btn-primary">
                                        <i class="bi bi-arrow-right me-1"></i>
                                        Tüm Sonuçları Gör
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Sağ Sütun -->
                    <div class="col-md-4">
                        <!-- Genel İstatistikler -->
                        <div class="stat-card">
                            <h5 class="section-title">
                                <i class="bi bi-graph-up me-2"></i>
                                Genel Başarı
                            </h5>
                            <div class="stat-value mb-2">
                                <?php echo number_format($stats['average_score'] ?? 0, 1); ?>%
                            </div>
                            <div class="text-muted">
                                <i class="bi bi-journal-text me-1"></i>
                                Toplam <?php echo $stats['total_exams'] ?? 0; ?> sınav
                            </div>
                        </div>

                        <!-- Ders Bazlı Başarı -->
                        <div class="dashboard-card">
                            <h4 class="section-title">
                                <i class="bi bi-bar-chart me-2"></i>
                                Ders Bazlı Başarı
                            </h4>
                            <?php foreach ($subject_stats as $subject): ?>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="fw-500"><?php echo htmlspecialchars($subject['subject']); ?></span>
                                        <span class="badge bg-primary">
                                            <?php echo number_format($subject['avg_score'], 1); ?>%
                                        </span>
                                    </div>
                                    <div class="progress">
                                        <div class="progress-bar" role="progressbar" 
                                             style="width: <?php echo $subject['avg_score']; ?>%"
                                             aria-valuenow="<?php echo $subject['avg_score']; ?>" 
                                             aria-valuemin="0" aria-valuemax="100">
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>