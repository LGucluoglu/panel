<?php
session_start();
require_once 'config.php';

// Admin kontrolü
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

try {
    // Sınavları çek
    $stmt = $db->prepare("
        SELECT 
            e.*,
            (SELECT COUNT(*) FROM student_exams WHERE exam_id = e.id) as total_participants,
            (SELECT COUNT(*) FROM student_exams WHERE exam_id = e.id AND status = 'completed') as completed_count
        FROM exams e
        ORDER BY e.created_at DESC
    ");
    $stmt->execute();
    $exams = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Sınav silme işlemi
    if (isset($_POST['delete_exam'])) {
        $exam_id = (int)$_POST['exam_id'];
        
        $db->beginTransaction();
        try {
            // Önce student_exams tablosundan sil
            $stmt = $db->prepare("DELETE FROM student_exams WHERE exam_id = ?");
            $stmt->execute([$exam_id]);
            
            // Sonra exams tablosundan sil
            $stmt = $db->prepare("DELETE FROM exams WHERE id = ?");
            $stmt->execute([$exam_id]);
            
            $db->commit();
            $_SESSION['success'] = "Sınav başarıyla silindi.";
        } catch (Exception $e) {
            $db->rollBack();
            $_SESSION['error'] = "Sınav silinirken bir hata oluştu.";
        }
        
        header("Location: exams.php");
        exit();
    }

} catch (PDOException $e) {
    die("Veritabanı hatası: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sınav Listesi - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        .exam-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            transition: transform 0.2s;
        }
        .exam-card:hover {
            transform: translateY(-5px);
        }
        .status-active { color: #1cc88a; }
        .status-pending { color: #f6c23e; }
        .status-completed { color: #4e73df; }
        .status-cancelled { color: #e74a3b; }
        
        /* Sidebar stilleri */
        .sidebar {
            box-shadow: inset -1px 0 0 rgba(0, 0, 0, .1);
        }
        .sidebar .nav-link {
            font-weight: 500;
            color: #333;
        }
        .sidebar .nav-link.active {
            color: #0d6efd;
        }
        .sidebar .nav-link:hover {
            color: #0d6efd;
        }
        main {
            padding-top: 20px;
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
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Sınav Listesi</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="create_exam.php" class="btn btn-primary">
                            <i class="bi bi-plus-lg"></i> Yeni Sınav
                        </a>
                    </div>
                </div>

                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php 
                        echo $_SESSION['success'];
                        unset($_SESSION['success']);
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php 
                        echo $_SESSION['error'];
                        unset($_SESSION['error']);
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (empty($exams)): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        Henüz sınav oluşturulmamış.
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($exams as $exam): ?>
                            <div class="col-12">
                                <div class="exam-card p-4">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h4><?php echo htmlspecialchars($exam['title']); ?></h4>
                                            <p class="text-muted mb-0">
                                                <i class="bi bi-calendar me-2"></i>
                                                <?php echo date('d.m.Y H:i', strtotime($exam['start_date'] . ' ' . $exam['start_time'])); ?>
                                                <span class="mx-2">|</span>
                                                <i class="bi bi-clock me-2"></i>
                                                <?php echo $exam['duration']; ?> dakika
                                            </p>
                                        </div>
                                        <div class="d-flex gap-2">
                                            <a href="edit_exam.php?id=<?php echo $exam['id']; ?>" 
                                               class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-pencil"></i> Düzenle
                                            </a>
                                            <form method="POST" class="d-inline" 
                                                  onsubmit="return confirm('Bu sınavı silmek istediğinizden emin misiniz?');">
                                                <input type="hidden" name="exam_id" value="<?php echo $exam['id']; ?>">
                                                <button type="submit" name="delete_exam" class="btn btn-sm btn-outline-danger">
                                                    <i class="bi bi-trash"></i> Sil
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                    <hr>
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <small class="text-muted d-block">Katılımcı Sayısı</small>
                                            <h5 class="mb-0"><?php echo $exam['total_participants']; ?></h5>
                                        </div>
                                        <div class="col-md-4">
                                            <small class="text-muted d-block">Tamamlayan</small>
                                            <h5 class="mb-0"><?php echo $exam['completed_count']; ?></h5>
                                        </div>
                                        <div class="col-md-4">
                                            <small class="text-muted d-block">Durum</small>
                                            <h5 class="mb-0 status-<?php echo $exam['status']; ?>">
                                                <?php 
                                                $status_labels = [
                                                    'active' => 'Aktif',
                                                    'pending' => 'Beklemede',
                                                    'completed' => 'Tamamlandı',
                                                    'cancelled' => 'İptal Edildi'
                                                ];
                                                echo $status_labels[$exam['status']] ?? $exam['status'];
                                                ?>
                                            </h5>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sidebar için aktif menü işaretleme
        document.addEventListener('DOMContentLoaded', function() {
            const currentPage = 'exams.php';
            const examsSubmenu = document.querySelector('#examsSubmenu');
            if (examsSubmenu) {
                examsSubmenu.classList.add('show');
            }
        });
    </script>
</body>
</html>