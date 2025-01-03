<?php
session_start();
require_once 'config.php';

// Admin kontrolü
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    die(json_encode(['error' => 'Yetkisiz erişim']));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['file'])) {
    http_response_code(400);
    die(json_encode(['error' => 'Geçersiz istek']));
}

$file = $_FILES['file'];
$upload_dir = 'uploads/questions/';

// Klasör yoksa oluştur
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Dosya kontrolü
$allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
if (!in_array($file['type'], $allowed_types)) {
    http_response_code(400);
    die(json_encode(['error' => 'Geçersiz dosya türü']));
}

// Boyut kontrolü (5MB)
if ($file['size'] > 5 * 1024 * 1024) {
    http_response_code(400);
    die(json_encode(['error' => 'Dosya boyutu çok büyük']));
}

// Güvenli dosya adı oluştur
$file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$new_filename = uniqid() . '.' . $file_extension;
$upload_path = $upload_dir . $new_filename;

// Dosyayı yükle
if (move_uploaded_file($file['tmp_name'], $upload_path)) {
    // Veritabanına kaydet
    try {
        $stmt = $db->prepare("
            INSERT INTO question_images (
                file_name, file_path, uploaded_by, created_at
            ) VALUES (?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $file['name'],
            $upload_path,
            $_SESSION['user_id']
        ]);

        echo json_encode([
            'success' => true,
            'url' => $upload_path,
            'id' => $db->lastInsertId()
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        die(json_encode(['error' => 'Veritabanı hatası']));
    }
} else {
    http_response_code(500);
    die(json_encode(['error' => 'Dosya yükleme hatası']));
}