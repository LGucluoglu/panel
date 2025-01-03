<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

// Kullanıcı kontrolü
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

// Kullanıcının aktif hedefini getir
$stmt = $db->prepare("
    SELECT * FROM user_goals 
    WHERE user_id = ? AND status = 'active'
");
$stmt->execute([$_SESSION['user_id']]);
$current_goal = $stmt->fetch(PDO::FETCH_ASSOC);

// Son sınav sonuçları
$stmt = $db->prepare("
    SELECT 
        se.*,
        e.title as exam_title
    FROM student_exams se
    JOIN exams e ON se.exam_id = e.id
    WHERE se.user_id = ?
    ORDER BY se.created_at DESC
    LIMIT 5
");
$stmt->execute([$_SESSION['user_id']]);
$recent_exams = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Bugünkü çalışma planı
$stmt = $db->prepare("
    SELECT 
        st.*,
        t.name as topic_name
    FROM study_topics st
    JOIN topics t ON st.topic_id = t.id
    JOIN study_plans sp ON st.plan_id = sp.id
    WHERE sp.user_id = ? 
    AND st.target_date = CURDATE()
    AND sp.status = 'active'
");
$stmt->execute([$_SESSION['user_id']]);
$todays_topics = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Kazanılan son rozetler
$stmt = $db->prepare("
    SELECT 
        a.name,
        a.icon,
        ua.earned_date
    FROM user_achievements ua
    JOIN achievements a ON ua.achievement_id = a.id
    WHERE ua.user_id = ?
    ORDER BY ua.earned_date DESC
    LIMIT 3
");
$stmt->execute([$_SESSION['user_id']]);
$recent_achievements = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Öğrenci Paneli</title>
    <link rel="stylesheet" href="css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1>Hoş Geldin, <?php echo htmlspecialchars($_SESSION['name']); ?></h1>
                </div>

                <!-- Hedef ve İlerleme -->
                <?php if ($current_goal): ?>
                    <div class="card mb-4">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-8">
                                    <h4 class="card-title">Mevcut Hedefim</h4>
                                    <p class="lead">
                                        <?php echo htmlspecialchars($current_goal['target_exam']); ?>
                                        - Hedef: %<?php echo $current_goal['target_score']; ?>
                                    </p>
                                    <p class="text-muted">
                                        Hedef Tarih: <?php echo date('d.m.Y', strtotime($current_goal['target_date'])); ?>
                                    </p>
                                </div>
                                <div class="col-md-4 text-end">
                                    <a href="my-goals.php" class="btn btn-primary">
                                        <i class="bi bi-pencil"></i> Hedefi Düzenle
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info mb-4">
                        <h4 class="alert-heading">Henüz bir hedef belirlemediniz!</h4>
                        <p>Başarıya giden yolda ilk adım, hedef belirlemektir.</p>
                        <hr>
                        <a href="my-goals.php" class="btn btn-info">
                            <i class="bi bi-bullseye"></i> Hedef Belirle
                        </a>
                    </div>
                <?php endif; ?>

                <div class="row mb-4">
                    <!-- Bugünkü Çalışma Planı -->
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header">
                                <h5 class="mb-0">Bugünkü Çalışma Planım</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($todays_topics)): ?>
                                    <p class="text-muted">Bugün için planlanmış çalışma yok.</p>
                                <?php else: ?>
                                    <ul class="list-group">
                                        <?php foreach ($todays_topics as $topic): ?>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                <?php echo htmlspecialchars($topic['topic_name']); ?>
                                                <span class="badge bg-<?php 
                                                    echo $topic['completion_status'] >= 100 ? 'success' : 
                                                        ($topic['completion_status'] > 0 ? 'warning' : 'secondary');
                                                ?>">
                                                    <?php echo $topic['completion_status']; ?>%
                                                </span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                            <div class="card-footer">
                                <a href="my-study-plan.php" class="btn btn-outline-primary btn-sm">
                                    Tüm Planı Görüntüle
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Son Rozetler -->
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header">
                                <h5 class="mb-0">Son Kazanılan Rozetler</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recent_achievements)): ?>
                                    <p class="text-muted">Henüz rozet kazanılmadı.</p>
                                <?php else: ?>
                                    <div class="row">
                                        <?php foreach ($recent_achievements as $achievement): ?>
                                            <div class="col-md-4 text-center">
                                                <i class="bi <?php echo $achievement['icon']; ?> display-4 text-warning"></i>
                                                <h6 class="mt-2"><?php echo htmlspecialchars($achievement['name']); ?></h6>
                                                <small class="text-muted">
                                                    <?php echo date('d.m.Y', strtotime($achievement['earned_date'])); ?>
                                                </small>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="card-footer">
                                <a href="my-achievements.php" class="btn btn-outline-primary btn-sm">
                                    Tüm Rozetleri Görüntüle
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Son Sınav Sonuçları -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Son Sınav Sonuçları</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_exams)): ?>
                            <p class="text-muted">Henüz sınav sonucu yok.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Sınav</th>
                                            <th>Puan</th>
                                            <th>Doğru</th>
                                            <th>Yanlış</th>
                                            <th>Tarih</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_exams as $exam): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($exam['exam_title']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        echo $exam['score'] >= 70 ? 'success' : 
                                                            ($exam['score'] >= 50 ? 'warning' : 'danger');
                                                    ?>">
                                                        %<?php echo $exam['score']; ?>
                                                    </span>
                                                </td>
                                                <td class="text-success">
                                                    <?php echo $exam['correct_answers']; ?>
                                                </td>
                                                <td class="text-danger">
                                                    <?php echo $exam['wrong_answers']; ?>
                                                </td>
                                                <td>
                                                    <?php echo date('d.m.Y H:i', strtotime($exam['created_at'])); ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer">
                        <a href="my-exams.php" class="btn btn-outline-primary btn-sm">
                            Tüm Sınavları Görüntüle
                        </a>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
