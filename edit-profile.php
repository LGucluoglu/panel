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

$success_message = '';
$errors = [];

// Form gönderildiğinde
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // ... (Form işleme kodları aynı kalacak) ...
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profili Düzenle</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fc;
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
        .edit-profile-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            overflow: hidden;
            margin: 20px 0;
        }
        .edit-profile-header {
            background: #4e73df;
            padding: 30px;
            text-align: center;
            color: white;
        }
        .edit-profile-header i {
            font-size: 3rem;
            margin-bottom: 10px;
        }
        .edit-profile-body {
            padding: 40px;
        }
        .form-control {
            border-radius: 10px;
            padding: 12px 20px;
            height: auto;
            background: #f8f9fc;
            border: 2px solid #eaecf4;
        }
        .form-control:focus {
            border-color: #4e73df;
            box-shadow: none;
        }
        .input-group-text {
            border-radius: 10px 0 0 10px;
            border: none;
            background: #f8f9fc;
            border: 2px solid #eaecf4;
            border-right: none;
        }
        .btn-update {
            background: #4e73df;
            color: white;
            padding: 12px 20px;
            border-radius: 10px;
            border: none;
            width: 100%;
            margin-top: 20px;
        }
        .btn-update:hover {
            background: #2e59d9;
            color: white;
        }
        .btn-cancel {
            background: #6c757d;
            color: white;
            padding: 12px 20px;
            border-radius: 10px;
            border: none;
            width: 100%;
            margin-top: 10px;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        .btn-cancel:hover {
            background: #5a6268;
            color: white;
        }
        .password-section {
            border-top: 1px solid #eaecf4;
            margin-top: 30px;
            padding-top: 30px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="position-sticky pt-3">
                    <div class="text-center mb-4">
                        <i class="bi bi-person-circle display-4"></i>
                        <h6 class="mt-2"><?php echo htmlspecialchars($user['name']); ?></h6>
                    </div>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="user-dashboard.php">
                                <i class="bi bi-house-door me-2"></i>
                                Ana Sayfa
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="my-exams.php">
                                <i class="bi bi-journal-text me-2"></i>
                                Sınavlarım
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="my-results.php">
                                <i class="bi bi-graph-up me-2"></i>
                                Sonuçlarım
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="profile.php">
                                <i class="bi bi-person me-2"></i>
                                Profilim
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="logout.php">
                                <i class="bi bi-box-arrow-right me-2"></i>
                                Çıkış Yap
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Ana İçerik -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="edit-profile-container">
                    <div class="edit-profile-header">
                        <i class="bi bi-person-circle"></i>
                        <h3>Profili Düzenle</h3>
                    </div>
                    <div class="edit-profile-body">
                        <?php if (!empty($success_message)): ?>
                            <div class="alert alert-success alert-dismissible fade show">
                                <?php echo $success_message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger alert-dismissible fade show">
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?php echo $error; ?></li>
                                    <?php endforeach; ?>
                                </ul>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="" id="editProfileForm">
                            <div class="mb-3">
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-person"></i>
                                    </span>
                                    <input type="text" class="form-control" id="name" name="name" 
                                           value="<?php echo htmlspecialchars($user['name']); ?>" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-envelope"></i>
                                    </span>
                                    <input type="email" class="form-control" name="email" 
                                           value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-phone"></i>
                                    </span>
                                    <input type="tel" class="form-control" id="phone" name="phone" 
                                           value="<?php echo htmlspecialchars($user['phone']); ?>" 
                                           maxlength="10" required>
                                </div>
                                <div id="phoneWarning" class="invalid-feedback" style="display: none;">
                                    Telefon numarası 10 haneli olmalı ve 5 ile başlamalıdır.
                                </div>
                            </div>

                            <div class="password-section">
                                <h5 class="mb-4">Şifre Değiştir (İsteğe Bağlı)</h5>
                                
                                <div class="mb-3">
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="bi bi-lock"></i>
                                        </span>
                                        <input type="password" class="form-control" name="current_password" 
                                               placeholder="Mevcut Şifre">
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="bi bi-lock"></i>
                                        </span>
                                        <input type="password" class="form-control" name="new_password" 
                                               placeholder="Yeni Şifre">
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="bi bi-lock"></i>
                                        </span>
                                        <input type="password" class="form-control" name="confirm_password" 
                                               placeholder="Yeni Şifre Tekrar">
                                    </div>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-update">Güncelle</button>
                            <a href="profile.php" class="btn btn-cancel">İptal</a>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
    document.getElementById('phone').addEventListener('input', function(e) {
        let phone = e.target.value;
        let isValid = /^5[0-9]{9}$/.test(phone);
        let warning = document.getElementById('phoneWarning');
        
        if (phone.length > 0) {
            if (!isValid) {
                e.target.classList.add('is-invalid');
                e.target.classList.remove('is-valid');
                warning.style.display = 'block';
            } else {
                e.target.classList.add('is-valid');
                e.target.classList.remove('is-invalid');
                warning.style.display = 'none';
            }
        } else {
            e.target.classList.remove('is-valid', 'is-invalid');
            warning.style.display = 'none';
        }
    });

    document.getElementById('editProfileForm').addEventListener('submit', function(e) {
        let phone = document.getElementById('phone').value;
        if (!/^5[0-9]{9}$/.test(phone)) {
            e.preventDefault();
            alert('Lütfen geçerli bir telefon numarası giriniz.');
        }
    });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>