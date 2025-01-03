<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json; charset=UTF-8');

try {
    // Debug log
    error_log("get_topics.php çağrıldı");
    
    // Admin kontrolü
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        throw new Exception('Yetkisiz erişim');
    }

    // Kategori ID kontrolü
    $category_id = filter_input(INPUT_GET, 'category_id', FILTER_VALIDATE_INT);
    error_log("Kategori ID: " . $category_id); // Debug log
    
    if ($category_id === false || $category_id === null) {
        throw new Exception('Geçersiz Kategori ID');
    }

    // Konuları getir
    $stmt = $db->prepare("
        SELECT id, name 
        FROM topics 
        WHERE category_id = ? AND status = 1 
        ORDER BY name ASC
    ");
    
    $stmt->execute([$category_id]);
    $topics = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("Bulunan konu sayısı: " . count($topics)); // Debug log
    
    echo json_encode($topics, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log("Hata: " . $e->getMessage()); // Debug log
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}