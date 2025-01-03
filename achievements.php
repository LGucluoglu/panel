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
        $criteria = json_encode($_POST['criteria']);

        $stmt = $db->prepare("
            INSERT INTO achievements (
                name, description, icon, criteria, 
                status, created_at
            ) VALUES (?, ?, ?, ?, 'active', NOW())
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
    <!-- Diğer CSS ve JS dosyaları -->
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'admin-sidebar.php'; ?>

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
                                        <i class="bi <?php echo htmlspecialchars($achievement['icon']); ?> fs-1 me-3"></i>
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
                                        <label class="form-label">Kazanma Kriterleri</label>
                                        <div id="criteriaList">
                                            <div class="criteria-item mb-2">
                                                <div class="input-group">
                                                    <select class="form-select" name="criteria[type][]">
                                                        <option value="exam_count">Sınav Sayısı</option>
                                                        <option value="avg_score">Ortalama Puan</option>
                                                        <option value="study_time">Çalışma Süresi</option>
                                                    </select>
                                                    <input type="number" class="form-control" 
                                                           name="criteria[value][]" placeholder="Değer">
                                                    <button type="button" class="btn btn-outline-danger" 
                                                            onclick="removeCriteria(this)">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        <button type="button" class="btn btn-outline-primary btn-sm mt-2" 
                                                onclick="addCriteriaField()">
                                            <i class="bi bi-plus"></i> Kriter Ekle
                                        </button>
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
        function addCriteriaField() {
            const html = `
                <div class="criteria-item mb-2">
                    <div class="input-group">
                        <select class="form-select" name="criteria[type][]">
                            <option value="exam_count">Sınav Sayısı</option>
                            <option value="avg_score">Ortalama Puan</option>
                            <option value="study_time">Çalışma Süresi</option>
                        </select>
                        <input type="number" class="form-control" 
                               name="criteria[value][]" placeholder="Değer">
                        <button type="button" class="btn btn-outline-danger" 
                                onclick="removeCriteria(this)">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
            `;
            
            document.getElementById('criteriaList').insertAdjacentHTML('beforeend', html);
        }

        function removeCriteria(button) {
            button.closest('.criteria-item').remove();
        }

        function editAchievement(id) {
            // AJAX ile rozet bilgilerini getir ve modalı aç
            fetch(`get-achievement.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    // Modal içeriğini doldur
                    // ...
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