<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

// Yetki kontrolü
if (!isAdmin()) {
    header("Location: login.php");
    exit();
}

// Plan ekleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_plan') {
        $title = $_POST['title'];
        $description = $_POST['description'];
        $target_exam = $_POST['target_exam'];
        $duration_weeks = $_POST['duration_weeks'];

        $stmt = $db->prepare("
            INSERT INTO study_plans (
                title, description, target_exam, 
                duration_weeks, created_by, status
            ) VALUES (?, ?, ?, ?, ?, 'active')
        ");
        
        $stmt->execute([
            $title, $description, $target_exam, 
            $duration_weeks, $_SESSION['user_id']
        ]);

        $plan_id = $db->lastInsertId();

        // Konuları ekle
        foreach ($_POST['topics'] as $topic) {
            $stmt = $db->prepare("
                INSERT INTO study_topics (
                    plan_id, topic_id, week_number, 
                    hours_per_week
                ) VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $plan_id, 
                $topic['topic_id'], 
                $topic['week'], 
                $topic['hours']
            ]);
        }

        header("Location: study-plans.php?success=1");
        exit();
    }
}

// Planları listele
$stmt = $db->prepare("
    SELECT 
        sp.*, 
        COUNT(st.id) as topic_count,
        u.name as created_by_name
    FROM study_plans sp
    LEFT JOIN study_topics st ON sp.id = st.plan_id
    LEFT JOIN users u ON sp.created_by = u.id
    GROUP BY sp.id
    ORDER BY sp.created_at DESC
");
$stmt->execute();
$plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Konuları getir
$stmt = $db->prepare("
    SELECT id, name, category_id 
    FROM topics 
    WHERE status = 1 
    ORDER BY name
");
$stmt->execute();
$topics = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Çalışma Planları Yönetimi - Admin Panel</title>
    <link rel="stylesheet" href="css/style.css">
    <!-- Diğer CSS ve JS dosyaları -->
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'admin-sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1>Çalışma Planları Yönetimi</h1>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newPlanModal">
                        <i class="bi bi-plus-lg"></i> Yeni Plan Ekle
                    </button>
                </div>

                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        İşlem başarıyla tamamlandı.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Plan Listesi -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Plan Adı</th>
                                        <th>Hedef Sınav</th>
                                        <th>Süre (Hafta)</th>
                                        <th>Konu Sayısı</th>
                                        <th>Oluşturan</th>
                                        <th>Durum</th>
                                        <th>İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($plans as $plan): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($plan['title']); ?></td>
                                            <td><?php echo htmlspecialchars($plan['target_exam']); ?></td>
                                            <td><?php echo $plan['duration_weeks']; ?></td>
                                            <td><?php echo $plan['topic_count']; ?></td>
                                            <td><?php echo htmlspecialchars($plan['created_by_name']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $plan['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                                    <?php echo $plan['status']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-info" onclick="editPlan(<?php echo $plan['id']; ?>)">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button class="btn btn-sm btn-danger" onclick="deletePlan(<?php echo $plan['id']; ?>)">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Yeni Plan Modalı -->
                <div class="modal fade" id="newPlanModal" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Yeni Çalışma Planı</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <form id="planForm" method="POST">
                                    <input type="hidden" name="action" value="add_plan">
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Plan Adı</label>
                                        <input type="text" class="form-control" name="title" required>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Açıklama</label>
                                        <textarea class="form-control" name="description" rows="3"></textarea>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Hedef Sınav</label>
                                                <input type="text" class="form-control" name="target_exam" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Süre (Hafta)</label>
                                                <input type="number" class="form-control" name="duration_weeks" required>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Konular</label>
                                        <div id="topicsList">
                                            <div class="topic-item mb-2">
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <select class="form-select" name="topics[0][topic_id]" required>
                                                            <option value="">Konu Seçin</option>
                                                            <?php foreach ($topics as $topic): ?>
                                                                <option value="<?php echo $topic['id']; ?>">
                                                                    <?php echo htmlspecialchars($topic['name']); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <input type="number" class="form-control" 
                                                               name="topics[0][week]" placeholder="Hafta" required>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <input type="number" class="form-control" 
                                                               name="topics[0][hours]" placeholder="Saat/Hafta" required>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <button type="button" class="btn btn-outline-primary btn-sm mt-2" onclick="addTopicField()">
                                            <i class="bi bi-plus"></i> Konu Ekle
                                        </button>
                                    </div>
                                </form>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                                <button type="submit" form="planForm" class="btn btn-primary">Kaydet</button>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        let topicCounter = 1;

        function addTopicField() {
            const html = `
                <div class="topic-item mb-2">
                    <div class="row">
                        <div class="col-md-6">
                            <select class="form-select" name="topics[${topicCounter}][topic_id]" required>
                                <option value="">Konu Seçin</option>
                                <?php foreach ($topics as $topic): ?>
                                    <option value="<?php echo $topic['id']; ?>">
                                        <?php echo htmlspecialchars($topic['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <input type="number" class="form-control" 
                                   name="topics[${topicCounter}][week]" placeholder="Hafta" required>
                        </div>
                        <div class="col-md-2">
                            <input type="number" class="form-control" 
                                   name="topics[${topicCounter}][hours]" placeholder="Saat/Hafta" required>
                        </div>
                        <div class="col-md-1">
                            <button type="button" class="btn btn-danger btn-sm" onclick="removeTopic(this)">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            document.getElementById('topicsList').insertAdjacentHTML('beforeend', html);
            topicCounter++;
        }

        function removeTopic(button) {
            button.closest('.topic-item').remove();
        }

        function editPlan(id) {
            // AJAX ile plan bilgilerini getir ve modalı aç
            fetch(`get-plan.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    // Modal içeriğini doldur
                    // ...
                });
        }

        function deletePlan(id) {
            if (confirm('Bu planı silmek istediğinizden emin misiniz?')) {
                fetch(`delete-plan.php?id=${id}`, { method: 'POST' })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        } else {
                            alert('Bir hata oluştu.');
                        }
                    });
            }
        }
    </script>
</body>
</html>