<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

// Öğrenci kontrolü
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

// Aktif çalışma planını getir
$stmt = $db->prepare("
    SELECT 
        sp.*,
        st.topic_id,
        st.target_date,
        st.completion_status,
        t.name as topic_name
    FROM study_plans sp
    JOIN study_topics st ON sp.id = st.plan_id
    JOIN topics t ON st.topic_id = t.id
    WHERE sp.user_id = ? AND sp.status = 'active'
    ORDER BY st.target_date ASC
");
$stmt->execute([$_SESSION['user_id']]);
$study_topics = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Konuları grupla
$weekly_plan = [];
foreach ($study_topics as $topic) {
    $week = date('W', strtotime($topic['target_date']));
    $weekly_plan[$week][] = $topic;
}

// İlerleme durumunu güncelle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['topic_id'])) {
    $stmt = $db->prepare("
        UPDATE study_topics 
        SET completion_status = ? 
        WHERE topic_id = ? AND plan_id = ?
    ");
    $stmt->execute([
        $_POST['status'],
        $_POST['topic_id'],
        $study_topics[0]['id']
    ]);

    header("Location: my-study-plan.php?success=1");
    exit();
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

                <?php if (empty($study_topics)): ?>
                    <div class="alert alert-info">
                        <h4 class="alert-heading">Henüz bir çalışma planınız yok!</h4>
                        <p>Çalışma planı oluşturmak için önce hedef belirlemelisiniz.</p>
                        <hr>
                        <a href="my-goals.php" class="btn btn-primary">
                            Hedef Belirle
                        </a>
                    </div>
                <?php else: ?>
                    <!-- Haftalık Plan -->
                    <?php foreach ($weekly_plan as $week => $topics): ?>
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <?php echo date('d.m.Y', strtotime("2024W{$week}")); ?> 
                                    Haftası
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Konu</th>
                                                <th>Tarih</th>
                                                <th>Durum</th>
                                                <th>İşlemler</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($topics as $topic): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($topic['topic_name']); ?></td>
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
                                                            İlerleme Güncelle
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