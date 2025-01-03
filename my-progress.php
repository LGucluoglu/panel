<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

// Öğrenci kontrolü
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

// Genel istatistikler
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_exams,
        AVG(score) as avg_score,
        SUM(correct_answers) as total_correct,
        SUM(wrong_answers) as total_wrong,
        SUM(empty_answers) as total_empty
    FROM student_exams
    WHERE user_id = ? AND status = 'completed'
");
$stmt->execute([$_SESSION['user_id']]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Konu bazlı başarı
$stmt = $db->prepare("
    SELECT 
        t.name as topic_name,
        COUNT(se.id) as exam_count,
        AVG(se.score) as avg_score,
        MIN(se.score) as min_score,
        MAX(se.score) as max_score
    FROM student_exams se
    JOIN exams e ON se.exam_id = e.id
    JOIN topics t ON e.topic_id = t.id
    WHERE se.user_id = ? AND se.status = 'completed'
    GROUP BY t.id
    ORDER BY avg_score DESC
");
$stmt->execute([$_SESSION['user_id']]);
$topic_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Aylık ilerleme
$stmt = $db->prepare("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        COUNT(*) as exam_count,
        AVG(score) as avg_score
    FROM student_exams
    WHERE user_id = ? AND status = 'completed'
    GROUP BY month
    ORDER BY month DESC
    LIMIT 12
");
$stmt->execute([$_SESSION['user_id']]);
$monthly_progress = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Çalışma süresi istatistikleri
$stmt = $db->prepare("
    SELECT 
        SUM(duration) as total_duration,
        AVG(duration) as avg_duration
    FROM study_progress
    WHERE user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$study_stats = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>İlerleme Durumum</title>
    <link rel="stylesheet" href="css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1>İlerleme Durumum</h1>
                    <div class="btn-group">
                        <button class="btn btn-outline-secondary" onclick="exportProgress('pdf')">
                            <i class="bi bi-file-pdf"></i> PDF İndir
                        </button>
                    </div>
                </div>

                <!-- Genel İstatistikler -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <h6 class="card-title">Toplam Sınav</h6>
                                <h2 class="card-text">
                                    <?php echo number_format($stats['total_exams']); ?>
                                </h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <h6 class="card-title">Ortalama Başarı</h6>
                                <h2 class="card-text">
                                    %<?php echo number_format($stats['avg_score'], 1); ?>
                                </h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <h6 class="card-title">Toplam Doğru</h6>
                                <h2 class="card-text">
                                    <?php echo number_format($stats['total_correct']); ?>
                                </h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body">
                                <h6 class="card-title">Toplam Yanlış</h6>
                                <h2 class="card-text">
                                    <?php echo number_format($stats['total_wrong']); ?>
                                </h2>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Grafikler -->
                <div class="row">
                    <!-- Konu Bazlı Başarı -->
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Konu Bazlı Başarı</h5>
                                <canvas id="topicChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Aylık İlerleme -->
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Aylık İlerleme</h5>
                                <canvas id="monthlyChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Detaylı Konu İstatistikleri -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Konu Bazlı Detaylı İstatistikler</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Konu</th>
                                        <th>Sınav Sayısı</th>
                                        <th>Ortalama</th>
                                        <th>En Düşük</th>
                                        <th>En Yüksek</th>
                                        <th>Başarı Grafiği</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($topic_stats as $stat): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($stat['topic_name']); ?></td>
                                            <td><?php echo number_format($stat['exam_count']); ?></td>
                                            <td>%<?php echo number_format($stat['avg_score'], 1); ?></td>
                                            <td>%<?php echo number_format($stat['min_score'], 1); ?></td>
                                            <td>%<?php echo number_format($stat['max_score'], 1); ?></td>
                                            <td>
                                                <div class="progress">
                                                    <div class="progress-bar bg-<?php 
                                                        echo $stat['avg_score'] >= 70 ? 'success' : 
                                                            ($stat['avg_score'] >= 50 ? 'warning' : 'danger');
                                                    ?>" role="progressbar" 
                                                         style="width: <?php echo $stat['avg_score']; ?>%">
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Çalışma İstatistikleri -->
                <?php if ($study_stats['total_duration']): ?>
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Çalışma İstatistikleri</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="text-center">
                                        <h6>Toplam Çalışma Süresi</h6>
                                        <h3><?php echo number_format($study_stats['total_duration']); ?> saat</h3>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="text-center">
                                        <h6>Günlük Ortalama</h6>
                                        <h3><?php echo number_format($study_stats['avg_duration'], 1); ?> saat</h3>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="text-center">
                                        <h6>Verimlilik</h6>
                                        <h3>%<?php 
                                            echo number_format(
                                                ($stats['avg_score'] * $study_stats['total_duration']) / 100, 
                                                1
                                            ); 
                                        ?></h3>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script>
        // Konu bazlı başarı grafiği
        const topicCtx = document.getElementById('topicChart').getContext('2d');
        new Chart(topicCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($topic_stats, 'topic_name')); ?>,
                datasets: [{
                    label: 'Başarı Oranı (%)',
                    data: <?php echo json_encode(array_column($topic_stats, 'avg_score')); ?>,
                    backgroundColor: 'rgba(54, 162, 235, 0.5)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100
                    }
                }
            }
        });

        // Aylık ilerleme grafiği
        const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
        new Chart(monthlyCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($monthly_progress, 'month')); ?>,
                datasets: [{
                    label: 'Ortalama Başarı (%)',
                    data: <?php echo json_encode(array_column($monthly_progress, 'avg_score')); ?>,
                    fill: false,
                    borderColor: 'rgba(75, 192, 192, 1)',
                    tension: 0.1
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100
                    }
                }
            }
        });

        function exportProgress(type) {
            window.location.href = `export-progress.php?type=${type}`;
        }
    </script>
</body>
</html>
