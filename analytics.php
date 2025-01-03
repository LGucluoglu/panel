<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

// Yetki kontrolü
if (!isAdmin()) {
    header("Location: login.php");
    exit();
}

// Genel istatistikler
$stmt = $db->prepare("
    SELECT 
        COUNT(DISTINCT se.user_id) as total_students,
        COUNT(se.id) as total_exams,
        AVG(se.score) as avg_score,
        COUNT(CASE WHEN se.score >= 70 THEN 1 END) as successful_exams,
        SUM(se.correct_answers) as total_correct,
        SUM(se.wrong_answers) as total_wrong
    FROM student_exams se
    WHERE se.status = 'completed'
");
$stmt->execute();
$general_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Konu bazlı başarı oranları
$stmt = $db->prepare("
    SELECT 
        t.name as topic_name,
        COUNT(se.id) as exam_count,
        AVG(se.score) as avg_score,
        MIN(se.score) as min_score,
        MAX(se.score) as max_score
    FROM student_exams se
    JOIN exams e ON se.exam_id = e.id
    JOIN topics t ON e.topic_id = t.id
    WHERE se.status = 'completed'
    GROUP BY t.id
    ORDER BY avg_score DESC
");
$stmt->execute();
$topic_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Aylık sınav istatistikleri
$stmt = $db->prepare("
    SELECT 
        DATE_FORMAT(se.created_at, '%Y-%m') as month,
        COUNT(*) as exam_count,
        AVG(score) as avg_score,
        COUNT(DISTINCT se.user_id) as student_count
    FROM student_exams se
    WHERE se.status = 'completed'
    GROUP BY month
    ORDER BY month DESC
    LIMIT 12
");
$stmt->execute();
$monthly_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// En başarılı öğrenciler
$stmt = $db->prepare("
    SELECT 
        u.name,
        COUNT(se.id) as exam_count,
        AVG(se.score) as avg_score,
        MAX(se.score) as max_score
    FROM users u
    JOIN student_exams se ON u.id = se.user_id
    WHERE se.status = 'completed'
    GROUP BY u.id
    HAVING exam_count >= 3
    ORDER BY avg_score DESC
    LIMIT 10
");
$stmt->execute();
$top_students = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Başarı Analizleri - Admin Panel</title>
    <link rel="stylesheet" href="css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1>Başarı Analizleri
