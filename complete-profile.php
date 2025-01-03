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

// Profil zaten tamamlanmışsa profile.php'ye yönlendir
if ($user['profile_completed']) {
    header("Location: profile.php");
    exit();
}

// İl listesi
$cities = [
    'Adana', 'Adıyaman', 'Afyonkarahisar', 'Ağrı', 'Amasya', 'Ankara', 'Antalya', 'Artvin',
    // ... diğer iller
];

// Eğitim seviyeleri
$education_levels = [
    'İlkokul',
    'Ortaokul',
    'Lise',
    'Üniversite',
    'Mezun'
];

$success_message = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $tc_no = trim($_POST['tc_no']);
    $phone_owner = trim($_POST['phone_owner']);
    $city = trim($_POST['city']);
    $district = trim($_POST['district']);
    $education_level = trim($_POST['education_level']);

    // TC Kimlik Numarası kontrolü
    if (!preg_match("/^[0-9]{11}$/", $tc_no)) {
        $errors[] = "Geçerli bir TC Kimlik Numarası giriniz.";
    }

    // Diğer alanların boş olup olmadığını kontrol et
    if (empty($phone_owner)) $errors[] = "Telefon sahibini belirtiniz.";
    if (empty($city)) $errors[] = "İl seçiniz.";
    if (empty($district)) $errors[] = "İlçe giriniz.";
    if (empty($education_level)) $errors[] = "Eğitim durumunuzu seçiniz.";

    // Hata yoksa güncelle
    if (empty($errors)) {
        try {
            $stmt = $db->prepare("UPDATE users SET tc_no = ?, phone_owner = ?, city = ?, district = ?, education_level = ?, profile_completed = 1 WHERE id = ?");
            $stmt->execute([$tc_no, $phone_owner, $city, $district, $education_level, $_SESSION['user_id']]);
            
            $_SESSION['success_message'] = "Profiliniz başarıyla tamamlandı.";
            header("Location: profile.php");
            exit();
        } catch(PDOException $e) {
            $errors[] = "Güncelleme sırasında bir hata oluştu.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Bilgilerini Tamamla</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(120deg, #4e73df 0%, #224abe 100%);
            min-height: 100vh;
            padding: 20px 0;
        }
        .complete-profile-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            box-shadow: 0 0 30px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            max-width: 800px;
            margin: 20px auto;
        }
        .complete-profile-header {
            background: #4e73df;
            padding: 30px;
            text-align: center;
            color: white;
        }
        .complete-profile-body {
            padding: 40px;
        }
        .form-control, .form-select {
            border-radius: 10px;
            padding: 12px 20px;
            height: auto;
            background: #f8f9fc;
            border: 2px solid #eaecf4;
        }
        .btn-complete {
            background: #4e73df;
            color: white;
            padding: 12px 20px;
            border-radius: 10px;
            border: none;
            width: 100%;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="complete-profile-container">
            <div class="complete-profile-header">
                <i class="bi bi-person-circle display-4"></i>
                <h3>Profil Bilgilerini Tamamla</h3>
                <p class="text-warning mb-0">
                    Sistemi kullanabilmek için lütfen aşağıdaki bilgileri eksiksiz doldurunuz.
                </p>
            </div>
            <div class="complete-profile-body">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo $error; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="mb-3">
                        <label class="form-label">TC Kimlik Numarası</label>
                        <input type="text" class="form-control" name="tc_no" maxlength="11" 
                               value="<?php echo htmlspecialchars($user['tc_no'] ?? ''); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Telefon Numarası Sahibi</label>
                        <input type="text" class="form-control" name="phone_owner" 
                               value="<?php echo htmlspecialchars($user['phone_owner'] ?? ''); ?>" 
                               placeholder="Örn: Kendim, Annem, Babam" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">İl</label>
                        <select class="form-select" name="city" required>
                            <option value="">İl Seçiniz</option>
                            <?php foreach ($cities as $city): ?>
                                <option value="<?php echo $city; ?>" 
                                    <?php echo ($user['city'] ?? '') === $city ? 'selected' : ''; ?>>
                                    <?php echo $city; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">İlçe</label>
                        <input type="text" class="form-control" name="district" 
                               value="<?php echo htmlspecialchars($user['district'] ?? ''); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Eğitim Durumu</label>
                        <select class="form-select" name="education_level" required>
                            <option value="">Eğitim Durumu Seçiniz</option>
                            <?php foreach ($education_levels as $level): ?>
                                <option value="<?php echo $level; ?>" 
                                    <?php echo ($user['education_level'] ?? '') === $level ? 'selected' : ''; ?>>
                                    <?php echo $level; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-complete">Profili Tamamla</button>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>