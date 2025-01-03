<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

// Yetki kontrolü
if (!isAdmin()) {
    header("Location: login.php");
    exit();
}

// Genel istatistikler
$stmt = $db->prepare("
    SELECT 
        COUNT(DISTINCT se.user_id) as total_students,
        COUNT(se.id) as total_exams,
        AVG(se.score) as avg_score,
        COUNT(CASE WHEN se.score >= 70 THEN 1 END) as successful_exams,
        SUM(se.correct_answers) as total_correct,
        SUM(se.wrong_answers) as total_wrong
    FROM student_exams se
    WHERE se.status = 'completed'
");
$stmt->execute();
$general_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Konu bazlı başarı oranları
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
    WHERE se.status = 'completed'
    GROUP BY t.id
    ORDER BY avg_score DESC
");
$stmt->execute();
$topic_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Aylık sınav istatistikleri
$stmt = $db->prepare("
    SELECT 
        DATE_FORMAT(se.created_at, '%Y-%m') as month,
        COUNT(*) as exam_count,
        AVG(score) as avg_score,
        COUNT(DISTINCT se.user_id) as student_count
    FROM student_exams se
    WHERE se.status = 'completed'
    GROUP BY month
    ORDER BY month DESC
    LIMIT 12
");
$stmt->execute();
$monthly_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// En başarılı öğrenciler
$stmt = $db->prepare("
    SELECT 
        u.name,
        COUNT(se.id) as exam_count,
        AVG(se.score) as avg_score,
        MAX(se.score) as max_score
    FROM users u
    JOIN student_exams se ON u.id = se.user_id
    WHERE se.status = 'completed'
    GROUP BY u.id
    HAVING exam_count >= 3
    ORDER BY avg_score DESC
    LIMIT 10
");
$stmt->execute();
$top_students = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Başarı Analizleri - Admin Panel</title>
    <link rel="stylesheet" href="css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1>Başarı Analizleri</h1>
                    <div class="btn-group">
                        <button class="btn btn-outline-secondary" onclick="exportData('excel')">
                            <i class="bi bi-file-excel"></i> Excel
                        </button>
                        <button class="btn btn-outline-secondary" onclick="exportData('pdf')">
                            <i class="bi bi-file-pdf"></i> PDF
                        </button>
                    </div>
                </div>

                <!-- Genel İstatistikler -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <h6 class="card-title">Toplam Öğrenci</h6>
                                <h2 class="card-text">
                                    <?php echo number_format($general_stats['total_students']); ?>
                                </h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <h6 class="card-title">Toplam Sınav</h6>
                                <h2 class="card-text">
                                    <?php echo number_format($general_stats['total_exams']); ?>
                                </h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <h6 class="card-title">Ortalama Başarı</h6>
                                <h2 class="card-text">
                                    %<?php echo number_format($general_stats['avg_score'], 1); ?>
                                </h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body">
                                <h6 class="card-title">Başarılı Sınav</h6>
                                <h2 class="card-text">
                                    <?php echo number_format($general_stats['successful_exams']); ?>
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
                                <h5 class="card-title">Konu Bazlı Başarı Oranları</h5>
                                <canvas id="topicChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Aylık İstatistikler -->
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Aylık Başarı Trendi</h5>
                                <canvas id="monthlyChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- En Başarılı Öğrenciler -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">En Başarılı Öğrenciler</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Öğrenci</th>
                                        <th>Sınav Sayısı</th>
                                        <th>Ortalama Başarı</th>
                                        <th>En Yüksek Puan</th>
                                        <th>Başarı Grafiği</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($top_students as $student): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($student['name']); ?></td>
                                            <td><?php echo number_format($student['exam_count']); ?></td>
                                            <td>%<?php echo number_format($student['avg_score'], 1); ?></td>
                                            <td>%<?php echo number_format($student['max_score'], 1); ?></td>
                                            <td>
                                                <div class="progress">
                                                    <div class="progress-bar bg-success" role="progressbar" 
                                                         style="width: <?php echo $student['avg_score']; ?>%">
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
                                        <th>Ortalama Başarı</th>
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
                                                    <div class="progress-bar" role="progressbar" 
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
                    label: 'Ortalama Başarı (%)',
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

        // Aylık başarı grafiği
        const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
        new Chart(monthlyCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($monthly_stats, 'month')); ?>,
                datasets: [{
                    label: 'Ortalama Başarı (%)',
                    data: <?php echo json_encode(array_column($monthly_stats, 'avg_score')); ?>,
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

        function exportData(type) {
            // AJAX ile veri dışa aktarma işlemi
            fetch(`export-analytics.php?type=${type}`)
                .then(response => response.blob())
                .then(blob => {
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = `analytics.${type}`;
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                });
        }
    </script>
</body>
</html>
