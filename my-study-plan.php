<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

// Öğrenci kontrolü
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

// İlerleme güncelleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['topic_id'])) {
    $stmt = $db->prepare("
        UPDATE study_topics 
        SET completion_status = ? 
        WHERE topic_id = ? AND plan_id IN (
            SELECT id FROM study_plans 
            WHERE user_id = ? AND status = 'active'
        )
    ");
    $stmt->execute([
        $_POST['status'],
        $_POST['topic_id'],
        $_SESSION['user_id']
    ]);

    header("Location: my-study-plan.php?success=1");
    exit();
}

// Aktif çalışma planını getir
$stmt = $db->prepare("
    SELECT 
        sp.*,
        ug.target_exam,
        ug.target_date as goal_date
    FROM study_plans sp
    LEFT JOIN user_goals ug ON sp.goal_id = ug.id
    WHERE sp.user_id = ? AND sp.status = 'active'
");
$stmt->execute([$_SESSION['user_id']]);
$current_plan = $stmt->fetch(PDO::FETCH_ASSOC);

if ($current_plan) {
    // Plan konularını getir
    $stmt = $db->prepare("
        SELECT 
            st.*,
            t.name as topic_name,
            t.description as topic_description
        FROM study_topics st
        JOIN topics t ON st.topic_id = t.id
        WHERE st.plan_id = ?
        ORDER BY st.target_date ASC
    ");
    $stmt->execute([$current_plan['id']]);
    $topics = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Konuları haftalara göre grupla
    $weekly_plan = [];
    foreach ($topics as $topic) {
        $week = date('W', strtotime($topic['target_date']));
        $weekly_plan[$week][] = $topic;
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Çalışma Planım</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1>Çalışma Planım</h1>
                </div>

                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        İlerleme durumu güncellendi.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (!$current_plan): ?>
                    <div class="alert alert-info">
                        <h4 class="alert-heading">Henüz bir çalışma planınız yok!</h4>
                        <p>Çalışma planı oluşturmak için önce hedef belirlemelisiniz.</p>
                        <hr>
                        <a href="my-goals.php" class="btn btn-info">
                            <i class="bi bi-bullseye"></i> Hedef Belirle
                        </a>
                    </div>
                <?php else: ?>
                    <!-- Plan Özeti -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h5 class="card-title"><?php echo htmlspecialchars($current_plan['title']); ?></h5>
                                    <p class="text-muted mb-0">
                                        Hedef: <?php echo htmlspecialchars($current_plan['target_exam']); ?><br>
                                        Süre: <?php echo date('d.m.Y', strtotime($current_plan['start_date'])); ?> - 
                                              <?php echo date('d.m.Y', strtotime($current_plan['end_date'])); ?>
                                    </p>
                                </div>
                                <div class="col-md-6 text-end">
                                    <div class="d-inline-block text-center me-3">
                                        <h6 class="mb-1">Kalan Gün</h6>
                                        <span class="badge bg-primary fs-5">
                                            <?php 
                                            $remaining = ceil((strtotime($current_plan['end_date']) - time()) / (60 * 60 * 24));
                                            echo max(0, $remaining);
                                            ?>
                                        </span>
                                    </div>
                                    <div class="d-inline-block text-center">
                                        <h6 class="mb-1">Genel İlerleme</h6>
                                        <?php
                                        $total_progress = 0;
                                        $topic_count = count($topics);
                                        foreach ($topics as $topic) {
                                            $total_progress += $topic['completion_status'];
                                        }
                                        $avg_progress = $topic_count > 0 ? $total_progress / $topic_count : 0;
                                        ?>
                                        <div class="progress" style="width: 100px; height: 25px;">
                                            <div class="progress-bar bg-success" 
                                                 style="width: <?php echo $avg_progress; ?>%">
                                                %<?php echo number_format($avg_progress, 0); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Haftalık Plan -->
                    <?php foreach ($weekly_plan as $week => $week_topics): ?>
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <?php 
                                    $week_start = date('d.m.Y', strtotime("2024W{$week}"));
                                    $week_end = date('d.m.Y', strtotime("2024W{$week} +6 days"));
                                    echo "{$week_start} - {$week_end}";
                                    ?>
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Konu</th>
                                                <th>Açıklama</th>
                                                <th>Tarih</th>
                                                <th>İlerleme</th>
                                                <th>İşlemler</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($week_topics as $topic): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($topic['topic_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($topic['topic_description']); ?></td>
                                                    <td><?php echo date('d.m.Y', strtotime($topic['target_date'])); ?></td>
                                                    <td>
                                                        <div class="progress">
                                                            <div class="progress-bar bg-<?php 
                                                                echo $topic['completion_status'] >= 100 ? 'success' : 
                                                                    ($topic['completion_status'] >= 50 ? 'warning' : 'danger');
                                                            ?>" role="progressbar" 
                                                                style="width: <?php echo $topic['completion_status']; ?>%">
                                                                <?php echo $topic['completion_status']; ?>%
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <button class="btn btn-sm btn-primary" 
                                                                onclick="updateProgress(<?php echo $topic['topic_id']; ?>)">
                                                            <i class="bi bi-pencil"></i> İlerleme
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- İlerleme Güncelleme Modalı -->
    <div class="modal fade" id="progressModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">İlerleme Durumu</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="progressForm" method="POST">
                        <input type="hidden" name="topic_id" id="topicId">
                        <div class="mb-3">
                            <label class="form-label">Tamamlanma Yüzdesi</label>
                            <input type="range" class="form-range" name="status" 
                                   min="0" max="100" step="10" id="progressRange">
                            <div class="text-center" id="progressValue">50%</div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" form="progressForm" class="btn btn-primary">Kaydet</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function updateProgress(topicId) {
            document.getElementById('topicId').value = topicId;
            new bootstrap.Modal(document.getElementById('progressModal')).show();
        }

        const progressRange = document.getElementById('progressRange');
        const progressValue = document.getElementById('progressValue');
        
        progressRange.addEventListener('input', function() {
            progressValue.textContent = this.value + '%';
        });
    </script>
</body>
</html>
