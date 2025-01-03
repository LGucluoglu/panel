<?php
session_start();
require_once 'config.php';

// Admin kontrolü
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Ana kategorileri çek
$stmt = $db->query("
    SELECT * FROM categories 
    WHERE parent_id IS NULL AND status = 1 
    ORDER BY name ASC
");
$main_categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Aktif seviyeleri çek
$stmt = $db->query("SELECT * FROM levels WHERE status = 1 ORDER BY name ASC");
$levels = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Soru ekleme işlemi
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $question_text = trim($_POST['question_text']);
    $category_id = $_POST['category_id'];
    $topic_id = $_POST['topic_id'];  // Yeni eklendi
    $level_id = $_POST['level_id'];
    $options = array_map('trim', $_POST['options']);
    $correct_answer = $_POST['correct_answer'];
    $explanation = trim($_POST['explanation']);
    $question_type = $_POST['question_type'];
    $image_position = isset($_POST['image_position']) ? $_POST['image_position'] : null;

    $errors = [];

    // Validasyonlar
    if ($question_type !== 'image' && empty($question_text)) {
        $errors[] = "Soru metni boş bırakılamaz.";
    }

    if (empty($category_id)) {
        $errors[] = "Kategori seçilmelidir.";
    }

    if (empty($topic_id)) {
        $errors[] = "Konu seçilmelidir.";
    }

    if (empty($level_id)) {
        $errors[] = "Seviye seçilmelidir.";
    }

    if (count(array_filter($options)) < 5) {
        $errors[] = "Tüm şıklar doldurulmalıdır.";
    }

    if (!isset($_POST['correct_answer'])) {
        $errors[] = "Lütfen doğru cevabı işaretleyiniz.";
    }

    // Görsel kontrolü
    if (($question_type === 'image' || $question_type === 'mixed') && !isset($_FILES['question_image'])) {
        $errors[] = "Lütfen bir görsel yükleyiniz.";
    }

    // Hata yoksa kaydet
    if (empty($errors)) {
        try {
            $db->beginTransaction();

            // Görsel varsa yükle
            $image_path = null;
            if (isset($_FILES['question_image']) && $_FILES['question_image']['error'] === UPLOAD_ERR_OK) {
                $image_path = handleImageUpload($_FILES['question_image']);
            }

            // Soruyu kaydet
            $stmt = $db->prepare("
                INSERT INTO questions (
                    question_text, category_id, topic_id, level_id,
                    explanation, created_by, created_at,
                    question_type, image_path, image_position
                ) VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?)
            ");

            $stmt->execute([
                $question_text,
                $category_id,
                $topic_id,
                $level_id,
                $explanation,
                $_SESSION['user_id'],
                $question_type,
                $image_path,
                $image_position
            ]);

            $question_id = $db->lastInsertId();

            // Şıkları kaydet
            $stmt = $db->prepare("
                INSERT INTO question_options (
                    question_id, option_text, is_correct
                ) VALUES (?, ?, ?)
            ");

            foreach ($options as $index => $option_text) {
                $is_correct = ($index == $correct_answer) ? 1 : 0;
                $stmt->execute([$question_id, $option_text, $is_correct]);
            }

            $db->commit();
            $_SESSION['success'] = "Soru başarıyla eklendi.";
            header("Location: question_bank.php");
            exit();

        } catch (PDOException $e) {
            $db->rollBack();
            $errors[] = "Veritabanı hatası: " . $e->getMessage();
        }
    }
}

// Görsel yükleme fonksiyonu
function handleImageUpload($file) {
    $upload_dir = 'uploads/questions/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];

    if (!in_array($file_extension, $allowed_extensions)) {
        throw new Exception('Geçersiz dosya formatı.');
    }

    if ($file['size'] > 5 * 1024 * 1024) { // 5MB
        throw new Exception('Dosya boyutu çok büyük.');
    }

    $new_filename = uniqid() . '.' . $file_extension;
    $upload_path = $upload_dir . $new_filename;

    if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
        throw new Exception('Dosya yükleme hatası.');
    }

    return $upload_path;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yeni Soru Ekle</title>
    
    <!-- CSS Dosyaları -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        .wrapper {
            display: flex;
            width: 100%;
            align-items: stretch;
        }

        #sidebar {
            min-width: 250px;
            max-width: 250px;
            min-height: 100vh;
            transition: all 0.3s;
        }

        #content {
            width: 100%;
            padding: 20px;
            min-height: 100vh;
            transition: all 0.3s;
        }

        .form-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            padding: 30px;
            margin: 20px 0;
            max-width: 100%;
        }

        .option-container {
            position: relative;
            margin-bottom: 15px;
        }

        .option-letter {
            position: absolute;
            left: -40px;
            top: 50%;
            transform: translateY(-50%);
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #4e73df;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-top: -10px;
        }

        .note-editor {
            background: white;
        }
        
        .note-modal {
            position: fixed !important;
            top: 50% !important;
            left: 50% !important;
            transform: translate(-50%, -50%) !important;
            z-index: 1050 !important;
        }
        
        .note-modal-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 1049;
            background: rgba(0,0,0,0.3);
        }
        
        .note-editor.note-frame {
            border: 1px solid #dee2e6;
        }
        
        .note-editing-area {
            background: white;
        }

        .border-dashed {
            border-style: dashed !important;
        }

        #dropZone {
            transition: all 0.3s ease;
            cursor: pointer;
        }

        #dropZone:hover {
            background-color: #f8f9fa;
        }

        #dropZone.dragover {
            background-color: #e9ecef;
            border-color: #0d6efd;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>

        <!-- Page Content -->
        <div id="content">
            <div class="container-fluid">
                <div class="form-container">
                    <h2 class="mb-4">Yeni Soru Ekle</h2>
                    
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
					                        <!-- Kategori ve Seviye Seçimi -->
                        <div class="row mb-4">
                            <div class="col-md-3">
                                <label for="main_category" class="form-label">Ana Kategori</label>
                                <select class="form-select" id="main_category" required>
                                    <option value="">Seçiniz</option>
                                    <?php foreach ($main_categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>">
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="sub_category" class="form-label">Alt Kategori</label>
                                <select class="form-select" id="sub_category" disabled>
                                    <option value="">Önce ana kategori seçiniz</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="sub_sub_category" class="form-label">Alt Kategori 2</label>
                                <select class="form-select" id="sub_sub_category" disabled>
                                    <option value="">Önce alt kategori seçiniz</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="topic_id" class="form-label">Konu</label>
                                <select class="form-select" id="topic_id" name="topic_id" disabled>
                                    <option value="">Önce kategori seçiniz</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="level_id" class="form-label">Seviye</label>
                                <select class="form-select" id="level_id" name="level_id" required>
                                    <option value="">Seviye Seçiniz</option>
                                    <?php foreach ($levels as $level): ?>
                                        <option value="<?php echo $level['id']; ?>">
                                            <?php echo htmlspecialchars($level['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Soru Tipi ve İçerik -->
                        <div class="mb-4">
                            <label class="form-label fw-bold">Soru Tipi</label>
                            <div class="btn-group w-100" role="group">
                                <input type="radio" class="btn-check" name="question_type" id="type_text" value="text" checked>
                                <label class="btn btn-outline-primary" for="type_text">
                                    <i class="bi bi-text-paragraph"></i> Metin
                                </label>

                                <input type="radio" class="btn-check" name="question_type" id="type_image" value="image">
                                <label class="btn btn-outline-primary" for="type_image">
                                    <i class="bi bi-image"></i> Görsel
                                </label>

                                <input type="radio" class="btn-check" name="question_type" id="type_mixed" value="mixed">
                                <label class="btn btn-outline-primary" for="type_mixed">
                                    <i class="bi bi-layout-text-window"></i> Metin + Görsel
                                </label>
                            </div>
                        </div>

                        <!-- Görsel Yükleme Alanı -->
                        <div id="imageUploadSection" class="mb-4" style="display: none;">
                            <label class="form-label">Soru Görseli</label>
                            <div class="card p-3 bg-light">
                                <div class="text-center mb-3">
                                    <img id="questionImagePreview" src="" class="img-fluid d-none mb-2" style="max-height: 300px;">
                                    <div id="dropZone" class="border-2 border-dashed p-4 text-center bg-white rounded">
                                        <i class="bi bi-cloud-arrow-up fs-2"></i>
                                        <p class="mb-0">Görseli sürükleyip bırakın veya seçin</p>
                                        <small class="text-muted">Maximum boyut: 5MB. İzin verilen formatlar: JPG, PNG, GIF</small>
                                    </div>
                                </div>
                                <input type="file" class="form-control" id="questionImage" name="question_image" 
                                       accept="image/*" style="display: none;">
                                
                                <!-- Görsel Pozisyonu -->
                                <div class="mt-2">
                                    <label class="form-label">Görsel Pozisyonu</label>
                                    <select class="form-select" name="image_position" id="imagePosition">
                                        <option value="top">Metin Üstünde</option>
                                        <option value="bottom">Metin Altında</option>
                                        <option value="right">Metin Sağında</option>
                                        <option value="left">Metin Solunda</option>
                                    </select>
                                </div>
                            </div>
                        </div>
						                        <!-- Soru Metni -->
                        <div id="textEditorSection" class="mb-4">
                            <label class="form-label">Soru Metni</label>
                            <textarea class="summernote" name="question_text"><?php echo isset($_POST['question_text']) ? htmlspecialchars($_POST['question_text']) : ''; ?></textarea>
                        </div>

                        <!-- Şıklar -->
                        <div class="mb-4">
                            <label class="form-label fw-bold">Şıklar ve Doğru Cevap</label>
                            <div class="alert alert-info">
                                Lütfen şıkları girdikten sonra doğru cevabı işaretleyiniz.
                            </div>
                            <div class="ms-5">
                                <?php 
                                $letters = ['A', 'B', 'C', 'D', 'E'];
                                foreach ($letters as $index => $letter): 
                                ?>
                                    <div class="option-container">
                                        <div class="option-letter"><?php echo $letter; ?></div>
                                        <div class="input-group mb-3">
                                            <input type="text" class="form-control" 
                                                   name="options[<?php echo $index; ?>]" 
                                                   placeholder="<?php echo $letter; ?> şıkkını giriniz"
                                                   value="<?php echo isset($_POST['options'][$index]) ? htmlspecialchars($_POST['options'][$index]) : ''; ?>"
                                                   required>
                                            <div class="input-group-text bg-light">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" 
                                                           name="correct_answer" value="<?php echo $index; ?>"
                                                           id="correct_<?php echo $letter; ?>"
                                                           <?php echo isset($_POST['correct_answer']) && $_POST['correct_answer'] == $index ? 'checked' : ''; ?>
                                                           required>
                                                    <label class="form-check-label" for="correct_<?php echo $letter; ?>">
                                                        Doğru Cevap
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Açıklama -->
                        <div class="mb-4">
                            <label for="explanation" class="form-label">Açıklama (İsteğe bağlı)</label>
                            <textarea class="form-control" 
                                      id="explanation" 
                                      name="explanation" 
                                      rows="4" 
                                      style="resize: vertical;"
                            ><?php echo isset($_POST['explanation']) ? htmlspecialchars($_POST['explanation']) : ''; ?></textarea>
                        </div>

                        <!-- Gizli inputlar -->
                        <input type="hidden" name="category_id" id="final_category_id">
                        <input type="hidden" name="topic_id" id="final_topic_id">

                        <!-- Gönder Butonu -->
                        <div class="text-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg"></i> Soruyu Kaydet
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
	    <!-- JavaScript Dosyaları -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/lang/summernote-tr-TR.min.js"></script>

    <script>
        $(document).ready(function() {
    // Summernote başlatma
    $('.summernote').summernote({
        lang: 'tr-TR',
        height: 300,
        placeholder: 'Soru metnini buraya giriniz...',
        toolbar: [
            ['style', ['style']],
            ['font', ['bold', 'underline', 'italic', 'clear']],
            ['fontname', ['fontname']],
            ['color', ['color']],
            ['para', ['ul', 'ol', 'paragraph']],
            ['table', ['table']],
            ['insert', ['link', 'picture']],
            ['view', ['fullscreen', 'codeview']]
        ],
        callbacks: {
            onImageUpload: function(files) {
                for(let i = 0; i < files.length; i++) {
                    uploadImage(files[i], this);
                }
            }
        }
    });

    // Görsel yükleme işlemleri
    function uploadImage(file, editor) {
        let formData = new FormData();
        formData.append('file', file);
        $.ajax({
            url: 'upload_image.php',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                let imageUrl = JSON.parse(response).url;
                $(editor).summernote('insertImage', imageUrl);
            },
            error: function() {
                alert('Görsel yükleme başarısız.');
            }
        });
    }

    // Soru tipi değiştiğinde
    $('input[name="question_type"]').change(function() {
        const selectedType = $(this).val();
        if(selectedType === 'text') {
            $('#imageUploadSection').hide();
            $('#textEditorSection').show();
        } else if(selectedType === 'image') {
            $('#imageUploadSection').show();
            $('#textEditorSection').hide();
        } else if(selectedType === 'mixed') {
            $('#imageUploadSection').show();
            $('#textEditorSection').show();
        }
    });

    // Kategori işlemleri
$('#main_category').change(function() {
    const mainCategoryId = $(this).val();
    const subCategory = $('#sub_category');
    const subSubCategory = $('#sub_sub_category');
    const topicSelect = $('#topic_id');
    
    console.log('Ana kategori seçildi:', mainCategoryId); // Debug log
    
    if (!mainCategoryId) {
        subCategory.html('<option value="">Önce ana kategori seçiniz</option>').prop('disabled', true);
        subSubCategory.html('<option value="">Önce alt kategori seçiniz</option>').prop('disabled', true);
        topicSelect.html('<option value="">Önce kategori seçiniz</option>').prop('disabled', true);
        return;
    }
    
    $.ajax({
        url: 'get_categories.php',
        method: 'POST',
        data: { parent_id: mainCategoryId },
        dataType: 'json',
        success: function(response) {
            console.log('Server yanıtı:', response); // Debug log
            
            subCategory.html('<option value="">Alt kategori seçiniz</option>');
            
            if (Array.isArray(response) && response.length > 0) {
                console.log('Kategoriler bulundu'); // Debug log
                response.forEach(function(category) {
                    subCategory.append(`<option value="${category.id}">${category.name}</option>`);
                });
                subCategory.prop('disabled', false);
            } else {
                console.log('Kategori bulunamadı'); // Debug log
                subCategory.prop('disabled', true);
            }
            $('#final_category_id').val(mainCategoryId);
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', error);
            console.error('Status:', status);
            console.error('Response:', xhr.responseText); // Debug log
            alert('Alt kategoriler yüklenirken bir hata oluştu.');
        }
    });
});

$('#sub_category').change(function() {
    const subCategoryId = $(this).val();
    const subSubCategory = $('#sub_sub_category');
    const topicSelect = $('#topic_id');
    
    console.log('Alt kategori seçildi:', subCategoryId); // Debug log
    
    if (!subCategoryId) {
        subSubCategory.html('<option value="">Önce alt kategori seçiniz</option>').prop('disabled', true);
        topicSelect.html('<option value="">Önce kategori seçiniz</option>').prop('disabled', true);
        return;
    }
    
    $.ajax({
        url: 'get_categories.php',
        method: 'POST',
        data: { parent_id: subCategoryId },
        dataType: 'json',
        success: function(response) {
            console.log('Server yanıtı:', response); // Debug log
            
            subSubCategory.html('<option value="">Alt kategori 2 seçiniz</option>');
            
            if (Array.isArray(response) && response.length > 0) {
                console.log('Alt kategoriler bulundu'); // Debug log
                response.forEach(function(category) {
                    subSubCategory.append(`<option value="${category.id}">${category.name}</option>`);
                });
                subSubCategory.prop('disabled', false);
            } else {
                console.log('Alt kategori bulunamadı'); // Debug log
                subSubCategory.prop('disabled', true);
            }
            $('#final_category_id').val(subCategoryId);
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', error);
            console.error('Status:', status);
            console.error('Response:', xhr.responseText);
            alert('Alt kategoriler yüklenirken bir hata oluştu.');
        }
    });
});

$('#sub_sub_category').change(function() {
    const subSubCategoryId = $(this).val();
    const topicSelect = $('#topic_id');
    
    console.log('Alt kategori 2 seçildi:', subSubCategoryId); // Debug log
    
    if (!subSubCategoryId) {
        topicSelect.html('<option value="">Önce alt kategori 2 seçiniz</option>').prop('disabled', true);
        return;
    }
    
    $('#final_category_id').val(subSubCategoryId);
    
    $.ajax({
        url: 'get_topics.php',
        type: 'GET',
        data: { category_id: subSubCategoryId },
        dataType: 'json',
        success: function(response) {
            console.log('Konular yanıtı:', response); // Debug log
            
            topicSelect.html('<option value="">Konu seçiniz</option>');
            
            if (response && response.length > 0) {
                console.log('Konular bulundu:', response.length); // Debug log
                response.forEach(function(topic) {
                    topicSelect.append(`<option value="${topic.id}">${topic.name}</option>`);
                });
                topicSelect.prop('disabled', false);
                
                // Topic ID'yi güncelle
                $('#final_topic_id').val('');
            } else {
                console.log('Konu bulunamadı'); // Debug log
                topicSelect.prop('disabled', true);
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', error);
            console.error('Status:', status);
            console.error('Response:', xhr.responseText);
            alert('Konular yüklenirken bir hata oluştu.');
        }
    });
});

// Konu seçildiğinde
$('#topic_id').change(function() {
    const selectedTopicId = $(this).val();
    if (selectedTopicId) {
        $('#final_topic_id').val(selectedTopicId);
    }
});

    // Görsel yükleme alanı işlemleri
    const dropZone = $('#dropZone');
    const imageInput = $('#questionImage');
    const imagePreview = $('#questionImagePreview');

    dropZone.on('click', function() {
        imageInput.click();
    });

    dropZone.on('dragover', function(e) {
        e.preventDefault();
        $(this).addClass('dragover');
    });

    dropZone.on('dragleave', function(e) {
        e.preventDefault();
        $(this).removeClass('dragover');
    });

    dropZone.on('drop', function(e) {
        e.preventDefault();
        $(this).removeClass('dragover');
        
        const files = e.originalEvent.dataTransfer.files;
        if (files.length > 0) {
            imageInput[0].files = files;
            handleImagePreview(files[0]);
        }
    });

    imageInput.change(function() {
        if (this.files && this.files[0]) {
            handleImagePreview(this.files[0]);
        }
    });

    function handleImagePreview(file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            imagePreview.attr('src', e.target.result).removeClass('d-none');
            dropZone.hide();
        }
        reader.readAsDataURL(file);
    }
});
    </script>
</body>
</html>