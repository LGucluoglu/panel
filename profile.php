<?php
session_start();
require_once 'config.php';

// Öğrenci girişi kontrolü
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

// Kullanıcı bilgilerini veritabanından çek
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Kullanıcı bulunamazsa çıkış yap
if (!$user) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// Profil tamamlanmamışsa yönlendir
if (!$user['profile_completed']) {
    header("Location: complete-profile.php");
    exit();
}

// Başarı mesajını al ve temizle
$success_message = '';
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profilim</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fc;
        }
        .profile-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            overflow: hidden;
            margin: 20px 0;
        }
        .profile-header {
            background: #4e73df;
            padding: 30px;
            text-align: center;
            color: white;
        }
        .profile-header i {
            font-size: 3rem;
            margin-bottom: 10px;
        }
        .profile-body {
            padding: 40px;
        }
        .profile-section {
            margin-bottom: 30px;
            background: #f8f9fc;
            border-radius: 10px;
            padding: 20px;
        }
        .section-title {
            color: #4e73df;
            font-weight: bold;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e3e6f0;
        }
        .info-item {
            margin-bottom: 15px;
        }
        .info-label {
            font-weight: 600;
            color: #5a5c69;
            margin-bottom: 5px;
        }
        .info-value {
            color: #3a3b45;
            padding: 8px 15px;
            background: white;
            border-radius: 5px;
            border: 1px solid #e3e6f0;
        }
        .btn-edit {
            background: #4e73df;
            color: white;
            padding: 10px 20px;
            border-radius: 10px;
            text-decoration: none;
            display: inline-block;
        }
        .btn-edit:hover {
            background: #2e59d9;
            color: white;
        }
        .sidebar {
            background-color: #f8f9fc;
            border-right: 1px solid #e3e6f0;
            min-height: 100vh;
        }
        .nav-link {
            color: #5a5c69;
            padding: 0.75rem 1rem;
            margin-bottom: 0.25rem;
        }
        .nav-link:hover {
            color: #4e73df;
            background-color: #eaecf4;
        }
        .nav-link.active {
            color: #4e73df;
            background-color: #eaecf4;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'user-sidebar.php'; ?>

            <!-- Ana İçerik -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show mt-3">
                        <?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="profile-container">
                    <div class="profile-header">
                        <i class="bi bi-person-circle"></i>
                        <h3>Profilim</h3>
                    </div>
                    <div class="profile-body">
                        <!-- Kişisel Bilgiler -->
                        <div class="profile-section">
                            <h4 class="section-title">Kişisel Bilgiler</h4>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="info-item">
                                        <div class="info-label">Ad Soyad</div>
                                        <div class="info-value"><?php echo htmlspecialchars($user['name']); ?></div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-item">
                                        <div class="info-label">TC Kimlik No</div>
                                        <div class="info-value"><?php echo htmlspecialchars($user['tc_no']); ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- İletişim Bilgileri -->
                        <div class="profile-section">
                            <h4 class="section-title">İletişim Bilgileri</h4>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="info-item">
                                        <div class="info-label">E-posta</div>
                                        <div class="info-value"><?php echo htmlspecialchars($user['email']); ?></div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-item">
                                        <div class="info-label">Telefon</div>
                                        <div class="info-value"><?php echo htmlspecialchars($user['phone']); ?></div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-item">
                                        <div class="info-label">Telefon Sahibi</div>
                                        <div class="info-value"><?php echo htmlspecialchars($user['phone_owner']); ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Adres Bilgileri -->
                        <div class="profile-section">
                            <h4 class="section-title">Adres Bilgileri</h4>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="info-item">
                                        <div class="info-label">İl</div>
                                        <div class="info-value"><?php echo htmlspecialchars($user['city']); ?></div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-item">
                                        <div class="info-label">İlçe</div>
                                        <div class="info-value"><?php echo htmlspecialchars($user['district']); ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Eğitim Bilgileri -->
                        <div class="profile-section">
                            <h4 class="section-title">Eğitim Bilgileri</h4>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="info-item">
                                        <div class="info-label">Eğitim Durumu</div>
                                        <div class="info-value"><?php echo htmlspecialchars($user['education_level']); ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="text-center">
                            <a href="edit-profile.php" class="btn btn-edit">
                                <i class="bi bi-pencil-square"></i> Profili Düzenle
                            </a>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>