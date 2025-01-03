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
        $user_id = $_POST['user_id'];
        $goal_id = $_POST['goal_id'];
        $title = $_POST['title'];
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];

        // Ana planı ekle
        $stmt = $db->prepare("
            INSERT INTO study_plans (
                user_id, goal_id, title, 
                start_date, end_date, status
            ) VALUES (?, ?, ?, ?, ?, 'active')
        ");
        
        $stmt->execute([
            $user_id,
            $goal_id,
            $title,
            $start_date,
            $end_date
        ]);

        $plan_id = $db->lastInsertId();

        // Konuları ekle
        foreach ($_POST['topics'] as $topic) {
            $stmt = $db->prepare("
                INSERT INTO study_topics (
                    plan_id, topic_id, target_date, 
                    completion_status
                ) VALUES (?, ?, ?, 0)
            ");
            $stmt->execute([
                $plan_id,
                $topic['topic_id'],
                $topic['target_date']
            ]);
        }

        header("Location: study-plans.php?success=1");
        exit();
    }
}

// Öğrencileri getir
$stmt = $db->prepare("
    SELECT id, name, username 
    FROM users 
    WHERE role = 'student'
    ORDER BY name
");
$stmt->execute();
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Hedefleri getir
$stmt = $db->prepare("
    SELECT id, user_id, target_exam 
    FROM user_goals 
    WHERE status = 'active'
    ORDER BY created_at DESC
");
$stmt->execute();
$goals = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Konuları getir
$stmt = $db->prepare("
    SELECT id, name 
    FROM topics 
    WHERE status = 1 
    ORDER BY name
");
$stmt->execute();
$topics = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Planları listele
$stmt = $db->prepare("
    SELECT 
        sp.*,
        u.name as student_name,
        ug.target_exam,
        COUNT(st.id) as topic_count
    FROM study_plans sp
    JOIN users u ON sp.user_id = u.id
    LEFT JOIN user_goals ug ON sp.goal_id = ug.id
    LEFT JOIN study_topics st ON sp.id = st.plan_id
    GROUP BY sp.id
    ORDER BY sp.created_at DESC
");
$stmt->execute();
$plans = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Çalışma Planları Yönetimi - Admin Panel</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'sidebar.php'; ?>

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
                                        <th>Öğrenci</th>
                                        <th>Plan Adı</th>
                                        <th>Hedef Sınav</th>
                                        <th>Başlangıç</th>
                                        <th>Bitiş</th>
                                        <th>Konu Sayısı</th>
                                        <th>Durum</th>
                                        <th>İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($plans as $plan): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($plan['student_name']); ?></td>
                                            <td><?php echo htmlspecialchars($plan['title']); ?></td>
                                            <td><?php echo htmlspecialchars($plan['target_exam']); ?></td>
                                            <td><?php echo date('d.m.Y', strtotime($plan['start_date'])); ?></td>
                                            <td><?php echo date('d.m.Y', strtotime($plan['end_date'])); ?></td>
                                            <td><?php echo $plan['topic_count']; ?></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $plan['status'] === 'active' ? 'success' : 
                                                        ($plan['status'] === 'completed' ? 'info' : 'secondary');
                                                ?>">
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
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Öğrenci</label>
                                                <select class="form-select" name="user_id" required>
                                                    <option value="">Öğrenci Seçin</option>
                                                    <?php foreach ($students as $student): ?>
                                                        <option value="<?php echo $student['id']; ?>">
                                                            <?php echo htmlspecialchars($student['name']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Hedef</label>
                                                <select class="form-select" name="goal_id" required>
                                                    <option value="">Hedef Seçin</option>
                                                    <?php foreach ($goals as $goal): ?>
                                                        <option value="<?php echo $goal['id']; ?>">
                                                            <?php echo htmlspecialchars($goal['target_exam']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Plan Adı</label>
                                        <input type="text" class="form-control" name="title" required>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Başlangıç Tarihi</label>
                                                <input type="date" class="form-control" name="start_date" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Bitiş Tarihi</label>
                                                <input type="date" class="form-control" name="end_date" required>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Konular</label>
                                        <div id="topicsList">
                                            <div class="topic-item mb-2">
                                                <div class="row">
                                                    <div class="col-md-8">
                                                        <select class="form-select" name="topics[0][topic_id]" required>
                                                            <option value="">Konu Seçin</option>
                                                            <?php foreach ($topics as $topic): ?>
                                                                <option value="<?php echo $topic['id']; ?>">
                                                                    <?php echo htmlspecialchars($topic['name']); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <input type="date" class="form-control" 
                                                               name="topics[0][target_date]" required>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <button type="button" class="btn btn-outline-primary btn-sm mt-2" 
                                                onclick="addTopicField()">
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
                        <div class="col-md-8">
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
                            <input type="date" class="form-control" 
                                   name="topics[${topicCounter}][target_date]" required>
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

        // Öğrenci seçildiğinde hedefleri filtrele
        document.querySelector('select[name="user_id"]').addEventListener('change', function() {
            const userId = this.value;
            const goalSelect = document.querySelector('select[name="goal_id"]');
            
            // Hedefleri filtrele
            Array.from(goalSelect.options).forEach(option => {
                if (option.dataset.userId === userId || option.value === '') {
                    option.style.display = '';
                } else {
                    option.style.display = 'none';
                }
            });
        });

        function editPlan(id) {
            // AJAX ile plan bilgilerini getir
            fetch(`get-plan.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    // Modal içeriğini doldur
                    document.querySelector('input[name="title"]').value = data.title;
                    document.querySelector('select[name="user_id"]').value = data.user_id;
                    document.querySelector('select[name="goal_id"]').value = data.goal_id;
                    document.querySelector('input[name="start_date"]').value = data.start_date;
                    document.querySelector('input[name="end_date"]').value = data.end_date;
                    
                    // Konuları doldur
                    const topicsList = document.getElementById('topicsList');
                    topicsList.innerHTML = '';
                    data.topics.forEach((topic, index) => {
                        const html = `
                            <div class="topic-item mb-2">
                                <div class="row">
                                    <div class="col-md-8">
                                        <select class="form-select" name="topics[${index}][topic_id]" required>
                                            <option value="">Konu Seçin</option>
                                            <?php foreach ($topics as $topic): ?>
                                                <option value="<?php echo $topic['id']; ?>">
                                                    <?php echo htmlspecialchars($topic['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <input type="date" class="form-control" 
                                               name="topics[${index}][target_date]" 
                                               value="${topic.target_date}" required>
                                    </div>
                                    <div class="col-md-1">
                                        <button type="button" class="btn btn-danger btn-sm" onclick="removeTopic(this)">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        `;
                        topicsList.insertAdjacentHTML('beforeend', html);
                        
                        // Konu seçimini ayarla
                        const lastSelect = topicsList.lastElementChild.querySelector('select');
                        lastSelect.value = topic.topic_id;
                    });
                    
                    // Modalı aç
                    new bootstrap.Modal(document.getElementById('newPlanModal')).show();
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
