<?php
session_start();
require_once 'config.php';

// Admin kontrolü
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Tarih filtresi
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Genel İstatistikler
$stats = [];

// Toplam sınav sayısı
$stmt = $db->prepare("
    SELECT COUNT(*) as total,
           SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active
    FROM exams
    WHERE created_at BETWEEN ? AND ?
");
$stmt->execute([$start_date, $end_date]);
$exam_stats = $stmt->fetch(PDO::FETCH_ASSOC);
$stats['total_exams'] = $exam_stats['total'];
$stats['active_exams'] = $exam_stats['active'];

// Toplam katılım sayısı
$stmt = $db->prepare("
    SELECT COUNT(*) as total,
           COUNT(DISTINCT user_id) as unique_users,
           AVG(score) as avg_score
    FROM exam_results
    WHERE completed_at BETWEEN ? AND ?
");
$stmt->execute([$start_date, $end_date]);
$participation_stats = $stmt->fetch(PDO::FETCH_ASSOC);
$stats['total_participations'] = $participation_stats['total'];
$stats['unique_participants'] = $participation_stats['unique_users'];
$stats['average_score'] = round($participation_stats['avg_score'], 2);

// Kategori Bazlı İstatistikler
$stmt = $db->prepare("
    SELECT 
        c.name as category_name,
        COUNT(DISTINCT e.id) as exam_count,
        COUNT(DISTINCT er.id) as participation_count,
        AVG(er.score) as avg_score,
        MIN(er.score) as min_score,
        MAX(er.score) as max_score
    FROM categories c
    LEFT JOIN exam_categories ec ON c.id = ec.category_id
    LEFT JOIN exams e ON ec.exam_id = e.id
    LEFT JOIN exam_results er ON e.id = er.exam_id
    WHERE (er.completed_at BETWEEN ? AND ? OR er.completed_at IS NULL)
    GROUP BY c.id
    ORDER BY exam_count DESC
");
$stmt->execute([$start_date, $end_date]);
$category_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Seviye Bazlı İstatistikler
$stmt = $db->prepare("
    SELECT 
        l.name as level_name,
        COUNT(DISTINCT e.id) as exam_count,
        COUNT(DISTINCT er.id) as participation_count,
        AVG(er.score) as avg_score
    FROM levels l
    LEFT JOIN exam_categories ec ON l.id = ec.level_id
    LEFT JOIN exams e ON ec.exam_id = e.id
    LEFT JOIN exam_results er ON e.id = er.exam_id
    WHERE (er.completed_at BETWEEN ? AND ? OR er.completed_at IS NULL)
    GROUP BY l.id
    ORDER BY exam_count DESC
");
$stmt->execute([$start_date, $end_date]);
$level_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// En Başarılı Sınavlar
$stmt = $db->prepare("
    SELECT 
        e.title,
        COUNT(er.id) as participation_count,
        AVG(er.score) as avg_score,
        MAX(er.score) as max_score
    FROM exams e
    JOIN exam_results er ON e.id = er.exam_id
    WHERE er.completed_at BETWEEN ? AND ?
    GROUP BY e.id
    ORDER BY avg_score DESC
    LIMIT 5
");
$stmt->execute([$start_date, $end_date]);
$top_exams = $stmt->fetchAll(PDO::FETCH_ASSOC);

// En Aktif Kullanıcılar
$stmt = $db->prepare("
    SELECT 
        u.name,
        COUNT(er.id) as exam_count,
        AVG(er.score) as avg_score,
        MAX(er.score) as max_score
    FROM users u
    JOIN exam_results er ON u.id = er.user_id
    WHERE er.completed_at BETWEEN ? AND ?
    GROUP BY u.id
    ORDER BY exam_count DESC
    LIMIT 5
");
$stmt->execute([$start_date, $end_date]);
$top_users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Günlük Katılım Grafiği için Veriler
$stmt = $db->prepare("
    SELECT 
        DATE(completed_at) as date,
        COUNT(*) as count
    FROM exam_results
    WHERE completed_at BETWEEN ? AND ?
    GROUP BY DATE(completed_at)
    ORDER BY date
");
$stmt->execute([$start_date, $end_date]);
$daily_participation = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Başarı Dağılımı
$stmt = $db->prepare("
    SELECT 
        CASE 
            WHEN score >= 90 THEN '90-100'
            WHEN score >= 80 THEN '80-89'
            WHEN score >= 70 THEN '70-79'
            WHEN score >= 60 THEN '60-69'
            ELSE '0-59'
        END as range,
        COUNT(*) as count
    FROM exam_results
    WHERE completed_at BETWEEN ? AND ?
    GROUP BY 
        CASE 
            WHEN score >= 90 THEN '90-100'
            WHEN score >= 80 THEN '80-89'
            WHEN score >= 70 THEN '70-79'
            WHEN score >= 60 THEN '60-69'
            ELSE '0-59'
        END
    ORDER BY range DESC
");
$stmt->execute([$start_date, $end_date]);
$score_distribution = $stmt->fetchAll(PDO::FETCH_ASSOC);

// JSON verilerini hazırla
$chart_data = [
    'daily_participation' => $daily_participation,
    'score_distribution' => $score_distribution,
    'category_stats' => array_map(function($item) {
        return [
            'name' => $item['category_name'],
            'exam_count' => $item['exam_count'],
            'avg_score' => round($item['avg_score'], 2)
        ];
    }, $category_stats)
];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sınav Raporları</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.css">
    <style>
        .stats-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
            transition: transform 0.2s;
        }

        .stats-card:hover {
            transform: translateY(-5px);
        }

        .stats-icon {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 15px;
        }

        .stats-primary { background: #4e73df20; color: #4e73df; }
        .stats-success { background: #1cc88a20; color: #1cc88a; }
        .stats-info { background: #36b9cc20; color: #36b9cc; }
        .stats-warning { background: #f6c23e20; color: #f6c23e; }

        .stats-value {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .stats-label {
            color: #858796;
            font-size: 0.875rem;
        }

        .chart-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }

        .table-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }

        .progress-thin {
            height: 5px;
        }

        .export-buttons {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
        }

        .export-buttons .btn {
            margin-left: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include 'sidebar.php'; ?>

            <!-- Ana İçerik -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <!-- Başlık ve Filtreler -->
                <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Sınav Raporları</h1>
                    <div class="d-flex align-items-center">
                        <div id="reportrange" class="btn btn-light">
                            <i class="bi bi-calendar3"></i>
                            <span></span>
                        </div>
                    </div>
                </div>

                <!-- Genel İstatistikler -->
                <div class="row">
                    <div class="col-xl-3 col-md-6">
                        <div class="stats-card">
                            <div class="stats-icon stats-primary">
                                <i class="bi bi-journal-text"></i>
                            </div>
                            <div class="stats-value"><?php echo $stats['total_exams']; ?></div>
                            <div class="stats-label">Toplam Sınav</div>
                            <div class="progress progress-thin mt-2">
                                <div class="progress-bar" style="width: <?php echo ($stats['active_exams']/$stats['total_exams'])*100; ?>%"></div>
                            </div>
                            <small class="text-muted"><?php echo $stats['active_exams']; ?> aktif sınav</small>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="stats-card">
                            <div class="stats-icon stats-success">
                                <i class="bi bi-people"></i>
                            </div>
                            <div class="stats-value"><?php echo $stats['total_participations']; ?></div>
                            <div class="stats-label">Toplam Katılım</div>
                            <div class="progress progress-thin mt-2">
                                <div class="progress-bar bg-success" style="width: <?php echo ($stats['unique_participants']/$stats['total_participations'])*100; ?>%"></div>
                            </div>
                            <small class="text-muted"><?php echo $stats['unique_participants']; ?> tekil katılımcı</small>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="stats-card">
                            <div class="stats-icon stats-info">
                                <i class="bi bi-graph-up"></i>
                            </div>
                            <div class="stats-value"><?php echo $stats['average_score']; ?></div>
                            <div class="stats-label">Ortalama Başarı</div>
                            <div class="progress progress-thin mt-2">
                                <div class="progress-bar bg-info" style="width: <?php echo $stats['average_score']; ?>%"></div>
                            </div>
                            <small class="text-muted">100 üzerinden</small>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="stats-card">
                            <div class="stats-icon stats-warning">
                                <i class="bi bi-clock-history"></i>
                            </div>
                            <div class="stats-value"><?php echo round($stats['total_participations']/$stats['total_exams'], 1); ?></div>
                            <div class="stats-label">Sınav Başına Katılım</div>
                            <small class="text-muted">Ortalama katılım oranı</small>
                        </div>
                    </div>
                </div>

                <!-- Grafikler -->
                <div class="row mt-4">
                    <div class="col-xl-8">
                        <div class="chart-container">
                            <h5>Günlük Katılım Grafiği</h5>
                            <canvas id="participationChart"></canvas>
                        </div>
                    </div>
                    <div class="col-xl-4">
                        <div class="chart-container">
                            <h5>Başarı Dağılımı</h5>
                            <canvas id="scoreDistributionChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Kategori ve Seviye İstatistikleri -->
                <div class="row mt-4">
                    <div class="col-md-6">
                        <div class="table-container">
                            <h5>Kategori Bazlı İstatistikler</h5>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Kategori</th>
                                            <th>Sınav</th>
                                            <th>Katılım</th>
                                            <th>Ort. Başarı</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($category_stats as $cat): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($cat['category_name']); ?></td>
                                                <td><?php echo $cat['exam_count']; ?></td>
                                                <td><?php echo $cat['participation_count']; ?></td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="progress flex-grow-1 me-2" style="height: 6px;">
                                                            <div class="progress-bar" style="width: <?php echo $cat['avg_score']; ?>%"></div>
                                                        </div>
                                                        <?php echo round($cat['avg_score'], 1); ?>%
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="table-container">
                            <h5>En Başarılı Sınavlar</h5>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Sınav</th>
                                            <th>Katılım</th>
                                            <th>Ort. Başarı</th>
                                            <th>En Yüksek</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($top_exams as $exam): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($exam['title']); ?></td>
                                                <td><?php echo $exam['participation_count']; ?></td>
                                                <td><?php echo round($exam['avg_score'], 1); ?>%</td>
                                                <td><?php echo round($exam['max_score'], 1); ?>%</td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Export Butonları -->
                <div class="export-buttons">
                    <button class="btn btn-primary" onclick="exportToPDF()">
                        <i class="bi bi-file-pdf"></i> PDF
                    </button>
                    <button class="btn btn-success" onclick="exportToExcel()">
                        <i class="bi bi-file-excel"></i> Excel
                    </button>
                </div>
            </main>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/moment/moment.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.17.5/xlsx.full.min.js"></script>

    <script>
        // Chart.js grafikleri için veri
        const chartData = <?php echo json_encode($chart_data); ?>;

        // Günlük Katılım Grafiği
        const participationCtx = document.getElementById('participationChart').getContext('2d');
        new Chart(participationCtx, {
            type: 'line',
            data: {
                labels: chartData.daily_participation.map(item => item.date),
                datasets: [{
                    label: 'Günlük Katılım',
                    data: chartData.daily_participation.map(item => item.count),
                    borderColor: '#4e73df',
                    tension: 0.1,
                    fill: true,
                    backgroundColor: '#4e73df20'
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Başarı Dağılımı Grafiği
        const distributionCtx = document.getElementById('scoreDistributionChart').getContext('2d');
        new Chart(distributionCtx, {
            type: 'doughnut',
            data: {
                labels: chartData.score_distribution.map(item => item.range),
                datasets: [{
                    data: chartData.score_distribution.map(item => item.count),
                    backgroundColor: [
                        '#4e73df',
                        '#1cc88a',
                        '#36b9cc',
                        '#f6c23e',
                        '#e74a3b'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Tarih Aralığı Seçici
        $(function() {
            const start = moment('<?php echo $start_date; ?>');
            const end = moment('<?php echo $end_date; ?>');

            function cb(start, end) {
                $('#reportrange span').html(start.format('DD.MM.YYYY') + ' - ' + end.format('DD.MM.YYYY'));
            }

            $('#reportrange').daterangepicker({
                startDate: start,
                endDate: end,
                ranges: {
                   'Son 7 Gün': [moment().subtract(6, 'days'), moment()],
                   'Son 30 Gün': [moment().subtract(29, 'days'), moment()],
                   'Bu Ay': [moment().startOf('month'), moment().endOf('month')],
                   'Geçen Ay': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
                },
                locale: {
                    format: 'DD.MM.YYYY',
                    applyLabel: 'Uygula',
                    cancelLabel: 'İptal',
                    customRangeLabel: 'Özel Aralık'
                }
            }, cb);

            cb(start, end);

            $('#reportrange').on('apply.daterangepicker', function(ev, picker) {
                window.location.href = `reports.php?start_date=${picker.startDate.format('YYYY-MM-DD')}&end_date=${picker.endDate.format('YYYY-MM-DD')}`;
            });
        });

        // PDF Export
        function exportToPDF() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();
            
            // PDF içeriğini oluştur
            doc.text('Sınav Raporları', 20, 20);
            // ... PDF içeriğini ekle
            
            doc.save('sinav-raporu.pdf');
        }

        // Excel Export
        function exportToExcel() {
            const wb = XLSX.utils.book_new();
            
            // Excel içeriğini oluştur
            const ws = XLSX.utils.json_to_sheet(chartData.category_stats);
            XLSX.utils.book_append_sheet(wb, ws, "Kategori İstatistikleri");
            
            XLSX.writeFile(wb, "sinav-raporu.xlsx");
        }
    </script>
</body>
</html>