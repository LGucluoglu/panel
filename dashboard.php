<?php
session_start();
require_once 'config.php';

// Admin kontrolü
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

try {
    // Genel istatistikler
    $stats = [
        'total_users' => $db->query("SELECT COUNT(*) FROM users")->fetchColumn(),
        'total_exams' => $db->query("SELECT COUNT(*) FROM exams")->fetchColumn(),
        'active_exams' => $db->query("SELECT COUNT(*) FROM exams WHERE status = 'active'")->fetchColumn(),
        'main_categories' => $db->query("SELECT COUNT(*) FROM categories WHERE parent_id IS NULL")->fetchColumn()
    ];

    // Son kayıt olan kullanıcılar
    $stmt = $db->query("
        SELECT 
            username, 
            email, 
            created_at, 
            role,
            (SELECT COUNT(*) FROM exam_results WHERE user_id = users.id) as exam_count
        FROM users 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $recent_users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Tüm ana kategoriler ve sınav sayıları
    $stmt = $db->query("
        SELECT 
            c.id,
            c.name as category_name,
            COUNT(DISTINCT ec.exam_id) as exam_count,
            SUM(CASE WHEN e.status = 'active' THEN 1 ELSE 0 END) as active_exam_count,
            COUNT(DISTINCT q.id) as question_count
        FROM categories c
        LEFT JOIN exam_categories ec ON c.id = ec.category_id
        LEFT JOIN exams e ON ec.exam_id = e.id
        LEFT JOIN questions q ON q.category_id = c.id
        WHERE c.parent_id IS NULL
        GROUP BY c.id, c.name
        ORDER BY c.name ASC
    ");
    $category_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Son eklenen sınavlar
    $stmt = $db->query("
        SELECT 
            e.title,
            e.start_date,
            e.status,
            COUNT(DISTINCT er.user_id) as participant_count
        FROM exams e
        LEFT JOIN exam_results er ON e.id = er.exam_id
        GROUP BY e.id
        ORDER BY e.created_at DESC
        LIMIT 5
    ");
    $recent_exams = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Veritabanı hatası: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary: #4361ee;
            --secondary: #3f37c9;
            --success: #2ec4b6;
            --info: #4895ef;
            --warning: #ff9f1c;
            --danger: #e71d36;
            --light: #f8f9fa;
            --dark: #212529;
        }

        body {
            background-color: #f0f2f5;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
        }

        .dashboard-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.04);
            transition: all 0.3s ease;
            border: none;
            overflow: hidden;
        }

        .dashboard-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        .welcome-card {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 2rem;
        }

        .stat-card {
            padding: 1.5rem;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }

        .stat-value {
            font-size: 1.75rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
            color: var(--dark);
        }

        .stat-label {
            color: #6c757d;
            font-size: 0.875rem;
        }

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin: 1.5rem 0;
        }

        .quick-action {
            background: white;
            padding: 1.25rem;
            border-radius: 12px;
            text-align: center;
            color: var(--primary);
            text-decoration: none;
            transition: all 0.3s ease;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .quick-action:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-4px);
        }

        .category-card {
            background: white;
            border: 1px solid rgba(0,0,0,0.05);
            border-radius: 12px;
            padding: 1rem;
            height: 100%;
            transition: all 0.3s ease;
        }

        .category-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }

        .category-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }

        .category-stat {
            padding: 0.5rem;
            background: var(--light);
            border-radius: 8px;
            text-align: center;
        }

        .category-stat .fw-bold {
            color: var(--primary);
        }

        .user-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .user-item {
            padding: 1rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            transition: background-color 0.2s;
        }

        .user-item:last-child {
            border-bottom: none;
        }

        .user-item:hover {
            background-color: var(--light);
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            color: var(--dark);
        }

        .text-truncate {
            max-width: 150px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        @media (max-width: 768px) {
            .stat-card {
                margin-bottom: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <!-- Hoş Geldin Kartı -->
                <div class="dashboard-card welcome-card mb-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="mb-1">Hoş Geldiniz, <?php echo htmlspecialchars($_SESSION['username']); ?></h4>
                            <p class="mb-0 opacity-75">Bugün neler yapmak istersiniz?</p>
                        </div>
                        <div class="text-end">
                            <div class="fs-6 opacity-75"><?php echo date('d F Y'); ?></div>
                            <div class="fs-7 opacity-50"><?php echo date('l'); ?></div>
                        </div>
                    </div>
                </div>

                <!-- Hızlı Erişim -->
                <div class="quick-actions">
                    <a href="create_exam.php" class="quick-action">
                        <i class="bi bi-plus-circle"></i>
                        <div>Yeni Sınav</div>
                    </a>
                    <a href="create_category.php" class="quick-action">
                        <i class="bi bi-folder-plus"></i>
                        <div>Yeni Kategori</div>
                    </a>
                    <a href="question_bank.php" class="quick-action">
                        <i class="bi bi-collection"></i>
                        <div>Soru Bankası</div>
                    </a>
                    <a href="reports.php" class="quick-action">
                        <i class="bi bi-graph-up"></i>
                        <div>Raporlar</div>
                    </a>
                </div>

                <!-- İstatistikler -->
                <div class="row g-4 mb-4">
                    <div class="col-md-3">
                        <div class="dashboard-card stat-card">
                            <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                                <i class="bi bi-people"></i>
                            </div>
                            <div class="stat-value"><?php echo number_format($stats['total_users']); ?></div>
                            <div class="stat-label">Toplam Kullanıcı</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="dashboard-card stat-card">
                            <div class="stat-icon bg-success bg-opacity-10 text-success">
                                <i class="bi bi-journal-text"></i>
                            </div>
                            <div class="stat-value"><?php echo number_format($stats['total_exams']); ?></div>
                            <div class="stat-label">Toplam Sınav</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="dashboard-card stat-card">
                            <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                                <i class="bi bi-calendar-check"></i>
                            </div>
                            <div class="stat-value"><?php echo number_format($stats['active_exams']); ?></div>
                            <div class="stat-label">Aktif Sınav</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="dashboard-card stat-card">
                            <div class="stat-icon bg-info bg-opacity-10 text-info">
                                <i class="bi bi-diagram-3"></i>
                            </div>
                            <div class="stat-value"><?php echo number_format($stats['main_categories']); ?></div>
                            <div class="stat-label">Ana Kategori</div>
                        </div>
                    </div>
                </div>

                <div class="row g-4">
                    <!-- Kategori İstatistikleri -->
                    <div class="col-12">
                        <div class="dashboard-card p-4">
                            <h5 class="section-title">Kategori Bazlı Sınavlar</h5>
                            <div class="row g-3">
                                <?php foreach ($category_stats as $cat): ?>
                                <div class="col-md-4">
                                    <div class="category-card">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <h6 class="mb-0 text-truncate" title="<?php echo htmlspecialchars($cat['category_name']); ?>">
                                                <?php echo htmlspecialchars($cat['category_name']); ?>
                                            </h6>
                                            <span class="badge bg-primary">
                                                <?php echo $cat['exam_count']; ?> Sınav
                                            </span>
                                        </div>
                                        <div class="category-stats">
                                            <div class="category-stat">
                                                <div class="fw-bold"><?php echo $cat['active_exam_count']; ?></div>
                                                <small class="text-muted">Aktif</small>
                                            </div>
                                            <div class="category-stat">
                                                <div class="fw-bold"><?php echo $cat['question_count']; ?></div>
                                                <small class="text-muted">Soru</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Son Kullanıcılar -->
                    <div class="col-md-12">
                        <div class="dashboard-card p-4">
                            <h5 class="section-title">Son Kayıt Olan Kullanıcılar</h5>
                            <ul class="user-list">
                                <?php foreach ($recent_users as $user): ?>
                                <li class="user-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="fw-medium"><?php echo htmlspecialchars($user['username']); ?></div>
                                            <small class="text-muted"><?php echo htmlspecialchars($user['email']); ?></small>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge <?php echo $user['role'] === 'admin' ? 'bg-danger' : 'bg-success'; ?>">
                                                <?php echo $user['role'] === 'admin' ? 'Yönetici' : 'Öğrenci'; ?>
                                            </span>
                                            <div class="small text-muted mt-1">
                                                <?php echo $user['exam_count']; ?> Sınav
                                            </div>
                                        </div>
                                    </div>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>