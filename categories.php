<?php
session_start();
require_once 'config.php';

// Admin kontrolü
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Kategori silme işlemi
if (isset($_POST['delete_category'])) {
    $category_id = $_POST['category_id'];
    
    // Önce alt kategorileri kontrol et
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM categories WHERE parent_id = ?");
    $stmt->execute([$category_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['count'] > 0) {
        $_SESSION['error'] = "Bu kategorinin alt kategorileri bulunmaktadır. Önce alt kategorileri silmelisiniz.";
    } else {
        // Kategoriye ait sınavları kontrol et
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM exam_categories WHERE category_id = ?");
        $stmt->execute([$category_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['count'] > 0) {
            $_SESSION['error'] = "Bu kategoriye ait sınavlar bulunmaktadır. Önce ilgili sınavları silmelisiniz.";
        } else {
            // Kategoriyi sil
            $stmt = $db->prepare("DELETE FROM categories WHERE id = ?");
            if ($stmt->execute([$category_id])) {
                $_SESSION['success'] = "Kategori başarıyla silindi.";
            } else {
                $_SESSION['error'] = "Kategori silinirken bir hata oluştu.";
            }
        }
    }
    header("Location: categories.php");
    exit();
}

// Kategori durumunu güncelleme
if (isset($_POST['toggle_status'])) {
    $category_id = $_POST['category_id'];
    $new_status = $_POST['new_status'];
    
    $stmt = $db->prepare("UPDATE categories SET status = ? WHERE id = ?");
    if ($stmt->execute([$new_status, $category_id])) {
        $_SESSION['success'] = "Kategori durumu güncellendi.";
    } else {
        $_SESSION['error'] = "Kategori durumu güncellenirken bir hata oluştu.";
    }
    header("Location: categories.php");
    exit();
}

// Kategorileri çek
try {
    // Ana sorguyu düzelt
    $stmt = $db->query("
        WITH RECURSIVE CategoryHierarchy AS (
            -- Ana kategoriler
            SELECT 
                c.*,
                0 as level,
                CAST(c.name AS CHAR(1000)) as path
            FROM categories c
            WHERE c.parent_id IS NULL
            
            UNION ALL
            
            -- Alt kategoriler
            SELECT 
                c.*,
                ch.level + 1,
                CONCAT(ch.path, ' > ', c.name)
            FROM categories c
            INNER JOIN CategoryHierarchy ch ON c.parent_id = ch.id
        )
        SELECT 
            ch.*,
            p.name as parent_name,
            (SELECT COUNT(*) FROM exam_categories WHERE category_id = ch.id) as exam_count
        FROM CategoryHierarchy ch
        LEFT JOIN categories p ON ch.parent_id = p.id
        ORDER BY ch.path
    ");
    
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug log
    error_log("Toplam kategori sayısı: " . count($categories));
    
    // Kategorileri hiyerarşik yapıya dönüştür
    $categoriesTree = [];
    
    // Önce tüm kategorileri ID'lerine göre indexle
    $categoryMap = [];
    foreach ($categories as $category) {
        $categoryMap[$category['id']] = $category;
        $categoryMap[$category['id']]['children'] = [];
    }
    
    // Hiyerarşiyi oluştur
    foreach ($categories as $category) {
        if ($category['parent_id'] === null) {
            // Ana kategori
            $categoriesTree[$category['id']] = &$categoryMap[$category['id']];
        } else {
            // Alt kategori
            $parent = &$categoryMap[$category['parent_id']];
            $parent['children'][$category['id']] = &$categoryMap[$category['id']];
        }
    }
    
    // Debug: Her ana kategoriyi ve alt kategori sayısını logla
    foreach ($categoriesTree as $mainCategory) {
        error_log("Ana Kategori: {$mainCategory['name']} - Alt kategori sayısı: " . count($mainCategory['children']));
    }

} catch (PDOException $e) {
    error_log("Kategori sorgu hatası: " . $e->getMessage());
    $_SESSION['error'] = "Veritabanı hatası oluştu.";
    $categoriesTree = [];
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kategori Yönetimi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        .main-category {
            border: none;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .main-category .card-header {
            border-radius: 10px 10px 0 0;
            padding: 15px 20px;
        }
        
        .category-actions .btn {
            margin-left: 5px;
        }
        
        .table {
            margin-bottom: 0;
        }
        
        .table td {
            vertical-align: middle;
            padding: 12px 15px;
        }
        
        .btn-group-sm > .btn {
            padding: .25rem .5rem;
        }

        .badge {
            padding: 6px 12px;
            font-weight: 500;
        }

        .content-container {
            padding: 20px;
        }

        .category-header-title {
            font-size: 1.1rem;
            font-weight: 600;
        }

        .subcategory-table {
            margin-left: 2rem;
            background-color: #f8f9fa;
            border-radius: 8px;
        }

        .subcategory-row {
            border-left: 3px solid #e9ecef;
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
                <div class="content-container">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2 class="h3">Kategori Yönetimi</h2>
                        <a href="create_category.php" class="btn btn-primary">
                            <i class="bi bi-plus-lg"></i> Yeni Kategori
                        </a>
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

                    <div class="row">
                        <?php foreach ($categoriesTree as $mainCategory): ?>
                            <div class="col-12">
                                <div class="card main-category">
                                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                                        <h5 class="mb-0 category-header-title">
                                            <i class="bi <?php echo htmlspecialchars($mainCategory['icon']); ?> me-2"></i>
                                            <?php echo htmlspecialchars($mainCategory['name']); ?>
                                        </h5>
                                        <div class="category-actions">
                                            <a href="create_category.php?parent_id=<?php echo $mainCategory['id']; ?>" 
                                               class="btn btn-sm btn-light" title="Alt Kategori Ekle">
                                                <i class="bi bi-plus-lg"></i>
                                            </a>
                                            <a href="edit_category.php?id=<?php echo $mainCategory['id']; ?>" 
                                               class="btn btn-sm btn-light" title="Düzenle">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <?php if (empty($mainCategory['children'])): ?>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="category_id" value="<?php echo $mainCategory['id']; ?>">
                                                    <button type="submit" name="delete_category" 
                                                            class="btn btn-sm btn-light"
                                                            onclick="return confirm('Bu kategoriyi silmek istediğinizden emin misiniz?');"
                                                            title="Sil">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <?php if (!empty($mainCategory['children'])): ?>
                                        <div class="card-body p-0">
                                            <div class="table-responsive">
                                                <table class="table table-hover">
                                                    <thead>
                                                        <tr>
                                                            <th>Alt Kategori</th>
                                                            <th>Durum</th>
                                                            <th>Sınav Sayısı</th>
                                                            <th class="text-end">İşlemler</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($mainCategory['children'] as $subCategory): ?>
                                                            <tr>
                                                                <td>
                                                                    <div class="d-flex flex-column">
                                                                        <div>
                                                                            <i class="bi <?php echo htmlspecialchars($subCategory['icon']); ?> me-2"></i>
                                                                            <?php echo htmlspecialchars($subCategory['name']); ?>
                                                                        </div>
                                                                        
                                                                        <?php if (!empty($subCategory['children'])): ?>
                                                                            <div class="mt-3">
                                                                                <table class="table table-sm subcategory-table">
                                                                                    <?php foreach ($subCategory['children'] as $subSubCategory): ?>
                                                                                        <tr class="subcategory-row">
                                                                                            <td>
                                                                                                <i class="bi <?php echo htmlspecialchars($subSubCategory['icon']); ?> me-2"></i>
                                                                                                <?php echo htmlspecialchars($subSubCategory['name']); ?>
                                                                                            </td>
                                                                                            <td>
                                                                                                <span class="badge <?php echo $subSubCategory['status'] ? 'bg-success' : 'bg-danger'; ?>">
                                                                                                    <?php echo $subSubCategory['status'] ? 'Aktif' : 'Pasif'; ?>
                                                                                                </span>
                                                                                            </td>
                                                                                            <td><?php echo $subSubCategory['exam_count']; ?></td>
                                                                                            <td class="text-end">
                                                                                                <div class="btn-group btn-group-sm">
                                                                                                    <a href="topics.php?category_id=<?php echo $subSubCategory['id']; ?>" 
                                                                                                       class="btn btn-outline-info" title="Konuları Yönet">
                                                                                                        <i class="bi bi-list-check"></i>
                                                                                                    </a>
                                                                                                    <a href="edit_category.php?id=<?php echo $subSubCategory['id']; ?>" 
                                                                                                       class="btn btn-outline-warning" title="Düzenle">
                                                                                                        <i class="bi bi-pencil"></i>
                                                                                                    </a>
                                                                                                    <form method="POST" class="d-inline">
                                                                                                        <input type="hidden" name="category_id" 
                                                                                                               value="<?php echo $subSubCategory['id']; ?>">
                                                                                                        <button type="submit" name="delete_category" 
                                                                                                                class="btn btn-outline-danger"
                                                                                                                onclick="return confirm('Bu kategoriyi silmek istediğinizden emin misiniz?');"
                                                                                                                title="Sil">
                                                                                                            <i class="bi bi-trash"></i>
                                                                                                        </button>
                                                                                                    </form>
                                                                                                </div>
                                                                                            </td>
                                                                                        </tr>
                                                                                    <?php endforeach; ?>
                                                                                </table>
                                                                            </div>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </td>
                                                                <td>
                                                                    <span class="badge <?php echo $subCategory['status'] ? 'bg-success' : 'bg-danger'; ?>">
                                                                        <?php echo $subCategory['status'] ? 'Aktif' : 'Pasif'; ?>
                                                                    </span>
                                                                </td>
                                                                <td><?php echo $subCategory['exam_count']; ?></td>
                                                                <td class="text-end">
                                                                    <div class="btn-group btn-group-sm">
                                                                        <a href="create_category.php?parent_id=<?php echo $subCategory['id']; ?>" 
                                                                           class="btn btn-outline-success" title="Alt Kategori Ekle">
                                                                            <i class="bi bi-plus-lg"></i>
                                                                        </a>
                                                                        <a href="topics.php?category_id=<?php echo $subCategory['id']; ?>" 
                                                                           class="btn btn-outline-info" title="Konuları Yönet">
                                                                            <i class="bi bi-list-check"></i>
                                                                        </a>
                                                                        <a href="edit_category.php?id=<?php echo $subCategory['id']; ?>" 
                                                                           class="btn btn-outline-warning" title="Düzenle">
                                                                            <i class="bi bi-pencil"></i>
                                                                        </a>
                                                                        <form method="POST" class="d-inline">
                                                                            <input type="hidden" name="category_id" value="<?php echo $subCategory['id']; ?>">
                                                                            <button type="submit" name="delete_category" 
                                                                                    class="btn btn-outline-danger"
                                                                                    onclick="return confirm('Bu kategoriyi silmek istediğinizden emin misiniz?');"
                                                                                    title="Sil">
                                                                                <i class="bi bi-trash"></i>
                                                                            </button>
                                                                        </form>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>