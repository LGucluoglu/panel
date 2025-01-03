<?php
session_start();
require_once 'config.php';

// Admin kontrolü
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Türkçe karakterleri dönüştüren fonksiyon
function createUsername($name) {
    $turkish = array("ı", "ğ", "ü", "ş", "ö", "ç", "İ", "Ğ", "Ü", "Ş", "Ö", "Ç", " ");
    $english = array("i", "g", "u", "s", "o", "c", "i", "g", "u", "s", "o", "c", "");
    $username = str_replace($turkish, $english, mb_strtolower($name, 'UTF-8'));
    $username = preg_replace('/[^a-z0-9]/', '', $username);
    return $username;
}

// Kullanıcı silme işlemi
if (isset($_POST['delete_user'])) {
    $user_id = $_POST['user_id'];
    try {
        $stmt = $db->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'");
        $stmt->execute([$user_id]);
        $success_message = "Kullanıcı başarıyla silindi.";
    } catch(PDOException $e) {
        $errors[] = "Silme işlemi sırasında bir hata oluştu.";
    }
}

// Kullanıcı düzenleme işlemi
if (isset($_POST['edit_user'])) {
    $user_id = $_POST['user_id'];
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = trim($_POST['password']);
    
    $errors = [];
    
    if (empty($name)) $errors[] = "Ad Soyad alanı gereklidir.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Geçerli bir e-posta adresi giriniz.";
    if (!preg_match("/^[0-9]{10,11}$/", $phone)) $errors[] = "Geçerli bir telefon numarası giriniz.";

    $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmt->execute([$email, $user_id]);
    if ($stmt->rowCount() > 0) {
        $errors[] = "Bu e-posta adresi zaten kullanılmakta.";
    }

    if (empty($errors)) {
        try {
            if (!empty($password)) {
                if (strlen($password) < 6) {
                    $errors[] = "Şifre en az 6 karakter olmalıdır.";
                } else {
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("UPDATE users SET name = ?, email = ?, phone = ?, password = ? WHERE id = ? AND role != 'admin'");
                    $stmt->execute([$name, $email, $phone, $hashedPassword, $user_id]);
                }
            } else {
                $stmt = $db->prepare("UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ? AND role != 'admin'");
                $stmt->execute([$name, $email, $phone, $user_id]);
            }
            $success_message = "Kullanıcı bilgileri başarıyla güncellendi.";
        } catch(PDOException $e) {
            $errors[] = "Güncelleme sırasında bir hata oluştu.";
        }
    }
}
// Kullanıcı ekleme işlemi
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_user'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $role = 'student'; // Varsayılan olarak student rolü atanıyor
    
    $errors = [];
    
    if (empty($name)) $errors[] = "Ad Soyad alanı gereklidir.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Geçerli bir e-posta adresi giriniz.";
    if (!preg_match("/^[0-9]{10,11}$/", $phone)) $errors[] = "Geçerli bir telefon numarası giriniz.";
    if (strlen($password) < 6) $errors[] = "Şifre en az 6 karakter olmalıdır.";

    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->rowCount() > 0) {
        $errors[] = "Bu e-posta adresi zaten kullanılmakta.";
    }

    if (empty($errors)) {
        try {
            $username = createUsername($name);
            $base_username = $username;
            $i = 1;
            
            while (true) {
                $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
                $stmt->execute([$username]);
                if ($stmt->rowCount() == 0) break;
                $username = $base_username . $i;
                $i++;
            }

            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $db->prepare("INSERT INTO users (username, password, email, phone, name, role) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$username, $hashedPassword, $email, $phone, $name, $role]);

            $success_message = "Kullanıcı başarıyla eklendi! Kullanıcı adı: " . $username;
        } catch(PDOException $e) {
            $errors[] = "Kayıt sırasında bir hata oluştu.";
        }
    }
}

// Kullanıcı listesini çek (admin hariç)
$stmt = $db->query("SELECT id, name, username, email, phone, role FROM users WHERE role != 'admin' ORDER BY id DESC");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kullanıcı Yönetimi - Admin Paneli</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        .content-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        .section-title {
            color: #4e73df;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e3e6f0;
        }
        .table-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            padding: 20px;
            margin-top: 20px;
        }
        .btn-action {
            width: 32px;
            height: 32px;
            padding: 0;
            line-height: 32px;
            text-align: center;
            margin: 0 2px;
        }
        .table {
            margin-bottom: 0;
        }
        .modal-content {
            border-radius: 15px;
            border: none;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
        }
        .modal-header {
            border-bottom: 1px solid #e3e6f0;
            background: #f8f9fc;
            border-radius: 15px 15px 0 0;
        }
        .modal-footer {
            border-top: 1px solid #e3e6f0;
            background: #f8f9fc;
            border-radius: 0 0 15px 15px;
        }
        .form-control {
            border-radius: 10px;
        }
        .btn {
            border-radius: 10px;
            padding: 8px 16px;
        }
        .alert {
            border-radius: 15px;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid">
        <div class="row">
            <?php include 'sidebar.php'; ?>

            <!-- Ana İçerik -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <!-- Başlık ve Yeni Ekle Butonu -->
                <div class="content-container mt-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <h1 class="h3">Kullanıcı Yönetimi</h1>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                            <i class="bi bi-plus-lg me-2"></i>Yeni Kullanıcı Ekle
                        </button>
                    </div>
                </div>

                <!-- Uyarı ve Hata Mesajları -->
                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo $error; ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Kullanıcı Listesi -->
                <div class="content-container">
                    <h4 class="section-title">Kullanıcı Listesi</h4>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Ad Soyad</th>
                                    <th>Kullanıcı Adı</th>
                                    <th>E-posta</th>
                                    <th>Telefon</th>
                                    <th>İşlemler</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo $user['id']; ?></td>
                                    <td><?php echo htmlspecialchars($user['name']); ?></td>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><?php echo htmlspecialchars($user['phone']); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-warning btn-action" data-bs-toggle="modal" data-bs-target="#editUserModal<?php echo $user['id']; ?>">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="btn btn-sm btn-danger btn-action" data-bs-toggle="modal" data-bs-target="#deleteUserModal<?php echo $user['id']; ?>">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
								                                <!-- Düzenleme Modal -->
                                <div class="modal fade" id="editUserModal<?php echo $user['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Kullanıcı Düzenle</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <form method="POST" action="">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    <div class="mb-3">
                                                        <label class="form-label">Ad Soyad</label>
                                                        <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">E-posta</label>
                                                        <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Telefon</label>
                                                        <input type="tel" class="form-control" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Yeni Şifre (Değiştirmek istemiyorsanız boş bırakın)</label>
                                                        <input type="password" class="form-control" name="password">
                                                    </div>
                                                    <input type="hidden" name="edit_user" value="1">
                                                    <button type="submit" class="btn btn-primary">Güncelle</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Silme Modal -->
                                <div class="modal fade" id="deleteUserModal<?php echo $user['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Kullanıcı Sil</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <p>Bu kullanıcıyı silmek istediğinizden emin misiniz?</p>
                                                <p><strong><?php echo htmlspecialchars($user['name']); ?></strong></p>
                                            </div>
                                            <div class="modal-footer">
                                                <form method="POST" action="">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    <input type="hidden" name="delete_user" value="1">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                                                    <button type="submit" class="btn btn-danger">Sil</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Kullanıcı Ekleme Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Yeni Kullanıcı Ekle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label class="form-label">Ad Soyad</label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">E-posta</label>
                            <input type="email" class="form-control" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Telefon</label>
                            <input type="tel" class="form-control" name="phone" placeholder="5XXXXXXXXX" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Şifre</label>
                            <input type="password" class="form-control" name="password" required>
                        </div>
                        <input type="hidden" name="add_user" value="1">
                        <button type="submit" class="btn btn-primary">Kaydet</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>