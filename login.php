<?php
session_start();
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        
        if ($user['role'] == 'admin') {
            header("Location: dashboard.php");
        } else {
            header("Location: user-dashboard.php");  // Değişiklik burada
        }
        exit();
    } else {
        $error = "Geçersiz kullanıcı adı veya şifre!";
    }
}

// ... (login.php'nin geri kalanı aynı kalacak)
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giriş Yap</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(120deg, #4e73df 0%, #224abe 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .login-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            box-shadow: 0 0 30px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            max-width: 400px;
            width: 100%;
        }
        .login-header {
            background: #4e73df;
            padding: 30px;
            text-align: center;
            color: white;
        }
        .login-header i {
            font-size: 3rem;
            margin-bottom: 10px;
        }
        .login-body {
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
        .btn-login {
            padding: 12px 20px;
            border-radius: 10px;
            font-weight: 600;
            letter-spacing: 0.5px;
            background: #4e73df;
            border: none;
            width: 100%;
            margin-top: 20px;
        }
        .btn-login:hover {
            background: #2e59d9;
        }
        .register-link {
            text-align: center;
            margin-top: 20px;
            color: #666;
        }
        .register-link a {
            color: #4e73df;
            text-decoration: none;
            font-weight: 600;
        }
        .register-link a:hover {
            text-decoration: underline;
        }
        .alert {
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .form-floating {
            margin-bottom: 15px;
        }
        .form-floating label {
            padding-left: 20px;
        }
        .login-header h3 {
            font-weight: 600;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="login-container">
                    <div class="login-header">
                        <i class="bi bi-person-circle"></i>
                        <h3>Giriş Yap</h3>
                        <p class="mb-0">Öğrenci Bilgi Sistemine Hoş Geldiniz</p>
                    </div>
                    <div class="login-body">
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?php echo $error; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <?php if (isset($_SESSION['success_message'])): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <?php 
                                echo $_SESSION['success_message'];
                                unset($_SESSION['success_message']);
                                ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="">
                            <div class="mb-3">
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-person"></i>
                                    </span>
                                    <input type="text" class="form-control" name="username" placeholder="Kullanıcı Adı" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-lock"></i>
                                    </span>
                                    <input type="password" class="form-control" name="password" placeholder="Şifre" required>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-login btn-primary">
                                <i class="bi bi-box-arrow-in-right me-2"></i>
                                Giriş Yap
                            </button>
                        </form>
                        <div class="register-link">
                            Hesabınız yok mu? <a href="register.php">Kayıt Olun</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>