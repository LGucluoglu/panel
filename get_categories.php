<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json; charset=UTF-8');

try {
    // Debug log
    error_log("get_categories.php çağrıldı");
    
    // Admin kontrolü
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        throw new Exception('Yetkisiz erişim');
    }

    // Parent ID kontrolü
    $parent_id = filter_input(INPUT_POST, 'parent_id', FILTER_VALIDATE_INT);
    error_log("Parent ID: " . $parent_id); // Debug log
    
    if ($parent_id === false || $parent_id === null) {
        throw new Exception('Geçersiz Parent ID');
    }

    // Kategorileri getir
    $stmt = $db->prepare("
        SELECT id, name, icon 
        FROM categories 
        WHERE parent_id = ? AND status = 1 
        ORDER BY name ASC
    ");
    
    $stmt->execute([$parent_id]);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("Bulunan kategori sayısı: " . count($categories)); // Debug log
    
    echo json_encode($categories, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log("Hata: " . $e->getMessage()); // Debug log
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}