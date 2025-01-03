<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

// Yetki kontrolü
if (!isAdmin()) {
    header("Location: login.php");
    exit();
}

// Rozet ekleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_achievement') {
        $name = $_POST['name'];
        $description = $_POST['description'];
        $icon = $_POST['icon'];
        $criteria = json_encode([
            'type' => $_POST['criteria_type'],
            'value' => $_POST['criteria_value']
        ]);

        $stmt = $db->prepare("
            INSERT INTO achievements (
                name, description, icon, criteria
            ) VALUES (?, ?, ?, ?)
        ");
        
        $stmt->execute([$name, $description, $icon, $criteria]);

        header("Location: achievements.php?success=1");
        exit();
    }
}

// Rozetleri listele
$stmt = $db->prepare("
    SELECT 
        a.*,
        COUNT(ua.id) as earned_count
    FROM achievements a
    LEFT JOIN user_achievements ua ON a.id = ua.achievement_id
    GROUP BY a.id
    ORDER BY a.created_at DESC
");
$stmt->execute();
$achievements = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Başarı Rozetleri Yönetimi - Admin Panel</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1>Başarı Rozetleri Yönetimi</h1>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newAchievementModal">
                        <i class="bi bi-plus-lg"></i> Yeni Rozet Ekle
                    </button>
                </div>

                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        İşlem başarıyla tamamlandı.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Rozet Listesi -->
                <div class="row">
                    <?php foreach ($achievements as $achievement): ?>
                        <div class="col-md-4 mb-4">
                            <div class="card h-100">
                                <div class="card-body">
                                    <div class="d-flex align-items-center mb-3">
                                        <i class="bi <?php echo htmlspecialchars($achievement['icon']); ?> fs-1 me-3 text-primary"></i>
                                        <div>
                                            <h5 class="card-title mb-1">
                                                <?php echo htmlspecialchars($achievement['name']); ?>
                                            </h5>
                                            <small class="text-muted">
                                                <?php echo $achievement['earned_count']; ?> kişi kazandı
                                            </small>
                                        </div>
                                    </div>
                                    <p class="card-text">
                                        <?php echo htmlspecialchars($achievement['description']); ?>
                                    </p>
                                    <?php 
                                    $criteria = json_decode($achievement['criteria'], true);
                                    if ($criteria): 
                                    ?>
                                        <div class="alert alert-info mb-3">
                                            <strong>Kazanma Kriteri:</strong><br>
                                            <?php
                                            switch ($criteria['type']) {
                                                case 'exam_count':
                                                    echo "En az {$criteria['value']} sınav tamamlama";
                                                    break;
                                                case 'avg_score':
                                                    echo "En az %{$criteria['value']} ortalama başarı";
                                                    break;
                                                case 'study_time':
                                                    echo "En az {$criteria['value']} saat çalışma";
                                                    break;
                                            }
                                            ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="mt-3">
                                        <button class="btn btn-sm btn-info me-2" onclick="editAchievement(<?php echo $achievement['id']; ?>)">
                                            <i class="bi bi-pencil"></i> Düzenle
                                        </button>
                                        <button class="btn btn-sm btn-danger" onclick="deleteAchievement(<?php echo $achievement['id']; ?>)">
                                            <i class="bi bi-trash"></i> Sil
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Yeni Rozet Modalı -->
                <div class="modal fade" id="newAchievementModal" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Yeni Başarı Rozeti</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <form id="achievementForm" method="POST">
                                    <input type="hidden" name="action" value="add_achievement">
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Rozet Adı</label>
                                        <input type="text" class="form-control" name="name" required>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Açıklama</label>
                                        <textarea class="form-control" name="description" rows="3" required></textarea>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">İkon</label>
                                        <input type="text" class="form-control" name="icon" 
                                               placeholder="bi-trophy" required>
                                        <small class="text-muted">
                                            Bootstrap Icons sınıf adını girin
                                        </small>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Kazanma Kriteri</label>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <select class="form-select" name="criteria_type" required>
                                                    <option value="exam_count">Sınav Sayısı</option>
                                                    <option value="avg_score">Ortalama Puan</option>
                                                    <option value="study_time">Çalışma Süresi</option>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <input type="number" class="form-control" 
                                                       name="criteria_value" placeholder="Değer" required>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                                <button type="submit" form="achievementForm" class="btn btn-primary">Kaydet</button>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        function editAchievement(id) {
            // AJAX ile rozet bilgilerini getir
            fetch(`get-achievement.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    document.querySelector('input[name="name"]').value = data.name;
                    document.querySelector('textarea[name="description"]').value = data.description;
                    document.querySelector('input[name="icon"]').value = data.icon;
                    
                    const criteria = JSON.parse(data.criteria);
                    document.querySelector('select[name="criteria_type"]').value = criteria.type;
                    document.querySelector('input[name="criteria_value"]').value = criteria.value;
                    
                    new bootstrap.Modal(document.getElementById('newAchievementModal')).show();
                });
        }

        function deleteAchievement(id) {
            if (confirm('Bu rozeti silmek istediğinizden emin misiniz?')) {
                fetch(`delete-achievement.php?id=${id}`, { method: 'POST' })
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
