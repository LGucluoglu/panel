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

// Genel istatistikleri çek
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_exams,
        AVG(score) as average_score,
        SUM(correct_answers) as total_correct,
        SUM(wrong_answers) as total_wrong,
        SUM(empty_answers) as total_empty
    FROM student_exams
    WHERE user_id = ? AND status = 'completed'
");
$stmt->execute([$_SESSION['user_id']]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Ders bazlı istatistikleri çek
$stmt = $db->prepare("
    SELECT 
        e.subject,
        COUNT(*) as exam_count,
        AVG(se.score) as avg_score,
        SUM(se.correct_answers) as total_correct,
        SUM(se.wrong_answers) as total_wrong,
        SUM(se.empty_answers) as total_empty
    FROM student_exams se
    JOIN exams e ON e.id = se.exam_id
    WHERE se.user_id = ? AND se.status = 'completed'
    GROUP BY e.subject
    ORDER BY avg_score DESC
");
$stmt->execute([$_SESSION['user_id']]);
$subject_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Son sınav sonuçlarını çek
$stmt = $db->prepare("
    SELECT 
        e.title,
        e.subject,
        se.score,
        se.correct_answers,
        se.wrong_answers,
        se.empty_answers,
        se.end_time,
        e.id as exam_id
    FROM student_exams se
    JOIN exams e ON e.id = se.exam_id
    WHERE se.user_id = ? AND se.status = 'completed'
    ORDER BY se.end_time DESC
    LIMIT 10
");
$stmt->execute([$_SESSION['user_id']]);
$recent_results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Başarı grafiği için son 6 sınavın verilerini çek
$stmt = $db->prepare("
    SELECT 
        e.title,
        se.score,
        se.end_time
    FROM student_exams se
    JOIN exams e ON e.id = se.exam_id
    WHERE se.user_id = ? AND se.status = 'completed'
    ORDER BY se.end_time DESC
    LIMIT 6
");
$stmt->execute([$_SESSION['user_id']]);
$graph_data = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sınav Sonuçlarım</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            background-color: #f8f9fa;
        }
        .stats-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        .section-title {
            color: #4e73df;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e3e6f0;
        }
        .stat-card {
            background: #f8f9fc;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid #e3e6f0;
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: #4e73df;
        }
        .stat-label {
            color: #858796;
            font-size: 0.9rem;
        }
        .subject-card {
            background: linear-gradient(45deg, #4e73df, #224abe);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .progress {
            height: 10px;
            border-radius: 5px;
            margin-top: 10px;
        }
        .result-row {
            background: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
            border: 1px solid #e3e6f0;
            transition: transform 0.2s;
        }
        .result-row:hover {
            transform: translateX(5px);
        }
        .score-badge {
            font-size: 1.2rem;
            font-weight: bold;
            padding: 5px 15px;
            border-radius: 20px;
        }
        .score-high {
            background: #1cc88a20;
            color: #1cc88a;
        }
        .score-medium {
            background: #f6c23e20;
            color: #f6c23e;
        }
        .score-low {
            background: #e74a3b20;
            color: #e74a3b;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="position-sticky pt-3">
                    <div class="text-center mb-4">
                        <i class="bi bi-person-circle display-4"></i>
                        <h6 class="mt-2"><?php echo $_SESSION['username']; ?></h6>
                    </div>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="student-dashboard.php">
                                <i class="bi bi-house-door me-2"></i>
                                Ana Sayfa
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="exams.php">
                                <i class="bi bi-journal-text me-2"></i>
                                Sınavlarım
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="results.php">
                                <i class="bi bi-graph-up me-2"></i>
                                Sonuçlarım
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="profile.php">
                                <i class="bi bi-person me-2"></i>
                                Profilim
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="logout.php">
                                <i class="bi bi-box-arrow-right me-2"></i>
                                Çıkış Yap
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Ana İçerik -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3">
                    <h1 class="h2">Sınav Sonuçlarım</h1>
                </div>

                <!-- Genel İstatistikler -->
                <div class="stats-container">
                    <h4 class="section-title">Genel İstatistikler</h4>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="stat-card text-center">
                                <div class="stat-value">
                                    <?php echo number_format($stats['total_exams']); ?>
                                </div>
                                <div class="stat-label">Toplam Sınav</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card text-center">
                                <div class="stat-value">
                                    <?php echo number_format($stats['average_score'], 1); ?>%
                                </div>
                                <div class="stat-label">Ortalama Başarı</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card text-center">
                                <div class="stat-value text-success">
                                    <?php echo number_format($stats['total_correct']); ?>
                                </div>
                                <div class="stat-label">Toplam Doğru</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card text-center">
                                <div class="stat-value text-danger">
                                    <?php echo number_format($stats['total_wrong']); ?>
                                </div>
                                <div class="stat-label">Toplam Yanlış</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Başarı Grafiği -->
                <div class="stats-container">
                    <h4 class="section-title">Başarı Grafiği</h4>
                    <canvas id="performanceChart"></canvas>
                </div>

                <!-- Ders Bazlı İstatistikler -->
                <div class="stats-container">
                    <h4 class="section-title">Ders Bazlı İstatistikler</h4>
                    <div class="row">
                        <?php foreach ($subject_stats as $subject): ?>
                            <div class="col-md-4">
                                <div class="subject-card">
                                    <h5><?php echo htmlspecialchars($subject['subject']); ?></h5>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span>Ortalama:</span>
                                        <span class="h4 mb-0">
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
                                    <div class="mt-3">
                                        <small>
                                            <?php echo $subject['exam_count']; ?> sınav | 
                                            <?php echo $subject['total_correct']; ?> doğru |
                                            <?php echo $subject['total_wrong']; ?> yanlış
                                        </small>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Son Sınav Sonuçları -->
                <div class="stats-container">
                    <h4 class="section-title">Son Sınav Sonuçları</h4>
                    <?php foreach ($recent_results as $result): ?>
                        <div class="result-row">
                            <div class="row align-items-center">
                                <div class="col-md-4">
                                    <h5 class="mb-1"><?php echo htmlspecialchars($result['title']); ?></h5>
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars($result['subject']); ?> |
                                        <?php echo date('d.m.Y H:i', strtotime($result['end_time'])); ?>
                                    </small>
                                </div>
                                <div class="col-md-4">
                                    <div class="d-flex justify-content-around">
                                        <div class="text-center">
                                            <div class="text-success"><?php echo $result['correct_answers']; ?></div>
                                            <small>Doğru</small>
                                        </div>
                                        <div class="text-center">
                                            <div class="text-danger"><?php echo $result['wrong_answers']; ?></div>
                                            <small>Yanlış</small>
                                        </div>
                                        <div class="text-center">
                                            <div class="text-warning"><?php echo $result['empty_answers']; ?></div>
                                            <small>Boş</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-2 text-center">
                                    <span class="score-badge <?php 
                                        echo $result['score'] >= 80 ? 'score-high' : 
                                             ($result['score'] >= 60 ? 'score-medium' : 'score-low'); 
                                    ?>">
                                        <?php echo number_format($result['score'], 1); ?>%
                                    </span>
                                </div>
                                <div class="col-md-2 text-end">
                                    <a href="exam_result.php?exam_id=<?php echo $result['exam_id']; ?>" 
                                       class="btn btn-sm btn-primary">
                                        <i class="bi bi-eye"></i> Detay
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Başarı grafiği
        const ctx = document.getElementById('performanceChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_map(function($item) {
                    return date('d.m.Y', strtotime($item['end_time']));
                }, $graph_data)); ?>,
                datasets: [{
                    label: 'Başarı Yüzdesi',
                    data: <?php echo json_encode(array_map(function($item) {
                        return $item['score'];
                    }, $graph_data)); ?>,
                    borderColor: '#4e73df',
                    backgroundColor: 'rgba(78, 115, 223, 0.1)',
                    tension: 0.3,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100
                    }
                }
            }
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>