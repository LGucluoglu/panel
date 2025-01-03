<?php
session_start();
require_once 'config.php';

// Admin kontrolü
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Kategori ID kontrolü
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Geçersiz kategori ID'si.";
    header("Location: categories.php");
    exit();
}

$category_id = (int)$_GET['id'];

// Mevcut kategoriyi çek
try {
    $stmt = $db->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([$category_id]);
    $category = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$category) {
        $_SESSION['error'] = "Kategori bulunamadı.";
        header("Location: categories.php");
        exit();
    }

    // Üst kategorileri çek (kendisi ve alt kategorileri hariç)
    $stmt = $db->prepare("
        SELECT id, name, parent_id 
        FROM categories 
        WHERE id != ? AND id NOT IN (
            SELECT id FROM categories WHERE parent_id = ?
        )
        ORDER BY parent_id IS NULL DESC, name ASC
    ");
    $stmt->execute([$category_id, $category_id]);
    $available_parents = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $_SESSION['error'] = "Veritabanı hatası: " . $e->getMessage();
    header("Location: categories.php");
    exit();
}

// Form gönderildiğinde
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $parent_id = !empty($_POST['parent_id']) ? $_POST['parent_id'] : null;
    $icon = trim($_POST['icon']);
    $status = isset($_POST['status']) ? 1 : 0;

    $errors = [];

    // Validasyonlar
    if (empty($name)) {
        $errors[] = "Kategori adı boş bırakılamaz.";
    }

    if (strlen($name) > 100) {
        $errors[] = "Kategori adı 100 karakterden uzun olamaz.";
    }

    // Aynı isimde başka kategori var mı kontrolü
    $stmt = $db->prepare("SELECT COUNT(*) FROM categories WHERE name = ? AND id != ?");
    $stmt->execute([$name, $category_id]);
    if ($stmt->fetchColumn() > 0) {
        $errors[] = "Bu isimde başka bir kategori zaten mevcut.";
    }

    // Hata yoksa güncelle
    if (empty($errors)) {
        $stmt = $db->prepare("
            UPDATE categories 
            SET name = ?, description = ?, parent_id = ?, icon = ?, status = ? 
            WHERE id = ?
        ");

        try {
            if ($stmt->execute([$name, $description, $parent_id, $icon, $status, $category_id])) {
                $_SESSION['success'] = "Kategori başarıyla güncellendi.";
                header("Location: categories.php");
                exit();
            } else {
                $errors[] = "Kategori güncellenirken bir hata oluştu.";
            }
        } catch (PDOException $e) {
            $errors[] = "Veritabanı hatası: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kategori Düzenle</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        .form-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            padding: 30px;
            margin: 20px 0;
        }
        
        .icon-preview {
            font-size: 2rem;
            color: #4e73df;
            margin: 10px 0;
        }
        
        .icon-list {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #e3e6f0;
            border-radius: 5px;
            padding: 10px;
        }
        
        .icon-option {
            display: inline-block;
            padding: 10px;
            cursor: pointer;
            border-radius: 5px;
        }
        
        .icon-option:hover {
            background: #e8eaf6;
        }
        
        .icon-option i {
            font-size: 1.5rem;
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
                <div class="form-container">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2 class="h3">Kategori Düzenle</h2>
                        <a href="categories.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Geri Dön
                        </a>
                    </div>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo $error; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="POST" class="needs-validation" novalidate>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="name" class="form-label">Kategori Adı</label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="<?php echo htmlspecialchars($category['name']); ?>" 
                                       required>
                                <div class="invalid-feedback">
                                    Kategori adı gereklidir.
                                </div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="parent_id" class="form-label">Üst Kategori</label>
                                <select class="form-select" id="parent_id" name="parent_id">
                                    <option value="">Ana Kategori</option>
                                    <?php foreach ($available_parents as $parent): ?>
                                        <option value="<?php echo $parent['id']; ?>"
                                            <?php echo ($category['parent_id'] == $parent['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($parent['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Açıklama</label>
                            <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($category['description']); ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="icon" class="form-label">İkon</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="bi <?php echo htmlspecialchars($category['icon']); ?>" id="selectedIcon"></i>
                                </span>
                                <input type="text" class="form-control" id="icon" name="icon" 
                                       value="<?php echo htmlspecialchars($category['icon']); ?>"
                                       readonly>
                                <button class="btn btn-outline-secondary" type="button" data-bs-toggle="modal" data-bs-target="#iconModal">
                                    İkon Seç
                                </button>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="status" name="status" 
                                       <?php echo $category['status'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="status">Aktif</label>
                            </div>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg"></i> Değişiklikleri Kaydet
                            </button>
                        </div>
                    </form>
                </div>
            </main>
        </div>
    </div>

    <!-- İkon Seçme Modalı -->
    <div class="modal fade" id="iconModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">İkon Seç</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="icon-list">
                        <?php
                        $icons = [
                            'bi-book', 'bi-journal-text', 'bi-pencil', 'bi-calculator',
                            'bi-globe', 'bi-translate', 'bi-diagram-3', 'bi-building',
                            'bi-mortarboard', 'bi-trophy', 'bi-star', 'bi-bookmark',
                            'bi-file-text', 'bi-collection', 'bi-box', 'bi-grid'
                        ];
                        foreach ($icons as $icon):
                        ?>
                            <div class="icon-option" onclick="selectIcon('<?php echo $icon; ?>')">
                                <i class="bi <?php echo $icon; ?>"></i>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validasyonu
        (function () {
            'use strict'
            var forms = document.querySelectorAll('.needs-validation')
            Array.prototype.slice.call(forms).forEach(function (form) {
                form.addEventListener('submit', function (event) {
                    if (!form.checkValidity()) {
                        event.preventDefault()
                        event.stopPropagation()
                    }
                    form.classList.add('was-validated')
                }, false)
            })
        })()

        // İkon seçimi
        function selectIcon(icon) {
            document.getElementById('icon').value = icon;
            document.getElementById('selectedIcon').className = 'bi ' + icon;
            bootstrap.Modal.getInstance(document.getElementById('iconModal')).hide();
        }
    </script>
</body>
</html>