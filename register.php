<?php
session_start();
require_once 'config.php';

// Türkçe karakterleri dönüştüren fonksiyon
function createUsername($name) {
    $turkish = array("ı", "ğ", "ü", "ş", "ö", "ç", "İ", "Ğ", "Ü", "Ş", "Ö", "Ç", " ");
    $english = array("i", "g", "u", "s", "o", "c", "i", "g", "u", "s", "o", "c", "");
    $username = str_replace($turkish, $english, mb_strtolower($name, 'UTF-8'));
    $username = preg_replace('/[^a-z0-9]/', '', $username);
    return $username;
}

// AJAX isteği kontrolü
if(isset($_POST['check_username'])) {
    $username = trim($_POST['username']);
    $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    echo $stmt->rowCount() > 0 ? 'exists' : 'available';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['check_username'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    $errors = [];
    
    // Doğrulama kontrolleri
    if (empty($name)) $errors[] = "Ad Soyad alanı gereklidir.";
    if (empty($username)) $errors[] = "Kullanıcı adı gereklidir.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Geçerli bir e-posta adresi giriniz.";
    if (!preg_match("/^5[0-9]{9}$/", $phone)) $errors[] = "Geçerli bir telefon numarası giriniz.";
    if (strlen($password) < 6) $errors[] = "Şifre en az 6 karakter olmalıdır.";
    if ($password !== $confirm_password) $errors[] = "Şifreler eşleşmiyor.";

    // Kullanıcı adı ve e-posta kontrolü
    $stmt = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $email]);
    if ($stmt->rowCount() > 0) {
        $errors[] = "Bu kullanıcı adı veya e-posta adresi zaten kullanılmakta.";
    }

    if (empty($errors)) {
        try {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $db->prepare("INSERT INTO users (username, password, email, phone, name, role) VALUES (?, ?, ?, ?, ?, 'student')");
            $stmt->execute([$username, $hashedPassword, $email, $phone, $name]);

            $_SESSION['success_message'] = "Kayıt başarılı! Giriş yapabilirsiniz.";
            header("Location: login.php");
            exit();
        } catch(PDOException $e) {
            $errors[] = "Kayıt sırasında bir hata oluştu.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kayıt Ol</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(120deg, #4e73df 0%, #224abe 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 20px 0;
        }
        .register-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            box-shadow: 0 0 30px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            max-width: 500px;
            width: 100%;
            margin: 20px auto;
        }
        .register-header {
            background: #4e73df;
            padding: 30px;
            text-align: center;
            color: white;
        }
        .register-header i {
            font-size: 3rem;
            margin-bottom: 10px;
        }
        .register-body {
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
        .btn-register {
            padding: 12px 20px;
            border-radius: 10px;
            font-weight: 600;
            letter-spacing: 0.5px;
            background: #4e73df;
            border: none;
            width: 100%;
            margin-top: 20px;
        }
        .btn-register:hover {
            background: #2e59d9;
        }
        .login-link {
            text-align: center;
            margin-top: 20px;
            color: #666;
        }
        .login-link a {
            color: #4e73df;
            text-decoration: none;
            font-weight: 600;
        }
        .login-link a:hover {
            text-decoration: underline;
        }
        .username-preview {
            background-color: #f8f9fc;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 10px 15px;
            margin-top: 10px;
            font-size: 0.9em;
        }
        .username-preview strong {
            color: #4e73df;
            font-weight: 600;
        }
        .edit-username-btn {
            padding: 0;
            margin-left: 10px;
            text-decoration: none;
            border: none;
            background: none;
            color: #4e73df;
            cursor: pointer;
            font-size: 0.9em;
        }
        .edit-username-btn:hover {
            text-decoration: underline;
            color: #2e59d9;
        }
        .username-edit {
            display: none;
            margin-top: 10px;
            padding: 10px;
            background-color: #f8f9fc;
            border-radius: 5px;
        }
        .username-edit small {
            display: block;
            margin-top: 5px;
            color: #6c757d;
        }
        .form-control.is-invalid {
            border-color: #dc3545;
            padding-right: calc(1.5em + 0.75rem);
            background-image: url("data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='#dc3545'><circle cx='6' cy='6' r='4.5'/><path stroke-linejoin='round' d='M5.8 3.6h.4L6 6.5z'/><circle cx='6' cy='8.2' r='.6' fill='#dc3545' stroke='none'/></svg>");
            background-repeat: no-repeat;
            background-position: right calc(0.375em + 0.1875rem) center;
            background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
        }
        .form-control.is-valid {
            border-color: #198754;
            padding-right: calc(1.5em + 0.75rem);
            background-image: url("data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 8 8'><path fill='#198754' d='M2.3 6.73L.6 4.53c-.4-1.04.46-1.4 1.1-.8l1.1 1.4 3.4-3.8c.6-.63 1.6-.27 1.2.7l-4 4.6c-.43.5-.8.4-1.1.1z'/></svg>");
            background-repeat: no-repeat;
            background-position: right calc(0.375em + 0.1875rem) center;
            background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
        }
        .invalid-feedback {
            display: none;
            width: 100%;
            margin-top: 0.25rem;
            font-size: 0.875em;
            color: #dc3545;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="register-container">
                    <div class="register-header">
                        <i class="bi bi-person-plus-fill"></i>
                        <h3>Kayıt Ol</h3>
                        <p class="mb-0">Öğrenci Bilgi Sistemine Hoş Geldiniz</p>
                    </div>
                    <div class="register-body">
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?php echo $error; ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="" id="registerForm">
                            <div class="mb-3">
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-person"></i>
                                    </span>
                                    <input type="text" class="form-control" id="name" name="name" placeholder="Ad Soyad" required>
                                </div>
                                <div class="username-preview">
                                    Kullanıcı adınız: <strong id="usernameDisplay"></strong>
                                    <button type="button" class="edit-username-btn">düzenle</button>
                                </div>
                                <div class="username-edit">
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="bi bi-at"></i>
                                        </span>
                                        <input type="text" class="form-control" id="username" name="username" placeholder="Kullanıcı adı">
                                    </div>
                                    <div id="usernameWarning" class="invalid-feedback" style="display: none;">
                                        Bu kullanıcı adı zaten kullanılıyor.
                                    </div>
                                    <small class="text-muted">Sadece harf, rakam ve alt çizgi kullanabilirsiniz.</small>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-envelope"></i>
                                    </span>
                                    <input type="email" class="form-control" name="email" placeholder="E-posta" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-phone"></i>
                                    </span>
                                    <input type="tel" class="form-control" id="phone" name="phone" placeholder="Telefon (5XXXXXXXXX)" maxlength="10" required>
                                </div>
                                <div id="phoneWarning" class="invalid-feedback" style="display: none;">
                                    Telefon numarası 10 haneli olmalı ve 5 ile başlamalıdır.
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
                            <div class="mb-4">
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-lock"></i>
                                    </span>
                                    <input type="password" class="form-control" name="confirm_password" placeholder="Şifre Tekrar" required>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-register btn-primary">Kayıt Ol</button>
                            <div class="login-link">
                                Zaten hesabınız var mı? <a href="login.php">Giriş Yapın</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Ad Soyad alanı için sadece blur (odak kaybı) event listener'ı
    document.getElementById('name').addEventListener('blur', function() {
        const formattedValue = formatNameInput(this.value);
        this.value = formattedValue;
        
        // Kullanıcı adını güncelle
        if (formattedValue) {
            generateUniqueUsername(formattedValue).then(username => {
                document.getElementById('usernameDisplay').textContent = username;
                document.getElementById('username').value = username;
                updateUsernameStatus(true);
            });
        }
    });

    // İsim formatlama fonksiyonu
    function formatNameInput(input) {
        if (!input) return '';
        
        // Boşlukları düzenle (başta ve sonda)
        let formatted = input.trim();
        
        // Birden fazla boşluğu teke indir
        formatted = formatted.replace(/\s+/g, ' ');
        
        // Her kelimenin ilk harfini büyük yap
        return formatted.split(' ').map(word => {
            if (!word) return '';
            
            let firstChar = word.charAt(0).toLowerCase();
            let rest = word.slice(1).toLowerCase();
            
            // Türkçe karakter düzeltmeleri
            if (firstChar === 'i') firstChar = 'İ';
            else if (firstChar === 'ı') firstChar = 'I';
            else firstChar = firstChar.toUpperCase();
            
            rest = rest
                .replace('I', 'ı')
                .replace('İ', 'i');
            
            return firstChar + rest;
        }).join(' ');
    }

    function createUsername(name) {
        return name.toLowerCase()
            .replace(/ı/g, 'i')
            .replace(/ğ/g, 'g')
            .replace(/ü/g, 'u')
            .replace(/ş/g, 's')
            .replace(/ö/g, 'o')
            .replace(/ç/g, 'c')
            .replace(/İ/g, 'i')
            .replace(/Ğ/g, 'g')
            .replace(/Ü/g, 'u')
            .replace(/Ş/g, 's')
            .replace(/Ö/g, 'o')
            .replace(/Ç/g, 'c')
            .replace(/[^a-z0-9]/g, '');
    }

    async function checkUsername(username) {
        try {
            const formData = new FormData();
            formData.append('check_username', '1');
            formData.append('username', username);

            const response = await fetch('register.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.text();
            return result === 'available';
        } catch (error) {
            console.error('Hata:', error);
            return false;
        }
    }

    async function generateUniqueUsername(name) {
        let username = createUsername(name);
        let counter = 1;
        let isAvailable = await checkUsername(username);
        
        while (!isAvailable) {
            username = createUsername(name) + counter;
            isAvailable = await checkUsername(username);
            counter++;
        }
        
        return username;
    }

    function toggleUsernameEdit() {
        const usernameEdit = document.querySelector('.username-edit');
        const currentDisplay = window.getComputedStyle(usernameEdit).display;
        
        if (currentDisplay === 'none') {
            usernameEdit.style.display = 'block';
            document.getElementById('username').focus();
        } else {
            usernameEdit.style.display = 'none';
        }
    }

    function updateUsernameStatus(isAvailable) {
        const usernameInput = document.getElementById('username');
        const usernameWarning = document.getElementById('usernameWarning');
        const submitButton = document.querySelector('button[type="submit"]');
        
        if (isAvailable) {
            usernameInput.classList.remove('is-invalid');
            usernameInput.classList.add('is-valid');
            usernameWarning.style.display = 'none';
            submitButton.disabled = false;
        } else {
            usernameInput.classList.remove('is-valid');
            usernameInput.classList.add('is-invalid');
            usernameWarning.style.display = 'block';
            submitButton.disabled = true;
        }
    }

    // Telefon numarası kontrolü için fonksiyon
    function validatePhone(phone) {
        // Sadece rakamları al
        const numbers = phone.replace(/[^0-9]/g, '');
        
        // 10 haneli ve 5 ile başlıyor mu kontrol et
        return numbers.length === 10 && numbers.startsWith('5');
    }

    // Telefon alanı için event listener
    document.getElementById('phone').addEventListener('input', function(e) {
        // Sadece rakam girişine izin ver
        let value = this.value.replace(/[^0-9]/g, '');
        
        // Maksimum 10 karakter
        if (value.length > 10) {
            value = value.slice(0, 10);
        }
        
        // Input değerini güncelle
        this.value = value;
        
        // Validasyon kontrolü
        const isValid = validatePhone(value);
        const warningElement = document.getElementById('phoneWarning');
        
        if (value.length > 0) {
            if (isValid) {
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
                warningElement.style.display = 'none';
            } else {
                this.classList.remove('is-valid');
                this.classList.add('is-invalid');
                warningElement.style.display = 'block';
                
                // Özel hata mesajları
                if (!value.startsWith('5')) {
                    warningElement.textContent = 'Telefon numarası 5 ile başlamalıdır.';
                } else if (value.length !== 10) {
                    warningElement.textContent = `Telefon numarası 10 haneli olmalıdır. (${value.length}/10)`;
                }
            }
        } else {
            // Alan boşsa sınıfları kaldır
            this.classList.remove('is-invalid', 'is-valid');
            warningElement.style.display = 'none';
        }
    });

    // Düzenle butonu için event listener
    document.querySelector('.edit-username-btn').addEventListener('click', function(e) {
        e.preventDefault();
        toggleUsernameEdit();
    });

    // Kullanıcı adı alanı için event listener
    let usernameTimeout;
    document.getElementById('username').addEventListener('input', function() {
        let value = this.value.toLowerCase();
        value = value.replace(/[^a-z0-9_]/g, '');
        this.value = value;
        document.getElementById('usernameDisplay').textContent = value;
        
        clearTimeout(usernameTimeout);
        usernameTimeout = setTimeout(async () => {
            if (value.length >= 3) {
                const isAvailable = await checkUsername(value);
                updateUsernameStatus(isAvailable);
            }
        }, 500);
    });

    // Form gönderimi için event listener
    document.getElementById('registerForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const username = document.getElementById('username').value;
        const phone = document.getElementById('phone').value;
        
        if (!validatePhone(phone)) {
            alert('Lütfen geçerli bir telefon numarası giriniz.');
            return;
        }
        
        if (username.length < 3) {
            alert('Kullanıcı adı en az 3 karakter olmalıdır.');
            return;
        }
        
        const isAvailable = await checkUsername(username);
        if (!isAvailable) {
            alert('Bu kullanıcı adı zaten kullanılıyor. Lütfen başka bir kullanıcı adı seçin.');
            return;
        }
        
        this.submit();
    });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>