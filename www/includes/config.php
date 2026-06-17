<?php
session_start();

// Соль для шифрования (только одно определение)
if (!defined('SALT')) {
    define('SALT', 'your-secret-salt-key-2024-change-this');
}

$host = 'localhost';
$dbname = 'art_portfolio';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Ошибка подключения к БД: " . $e->getMessage());
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getUserRole() {
    return isset($_SESSION['user_role']) ? $_SESSION['user_role'] : null;
}

function h($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

define('UPLOAD_PATH', '/assets/uploads/artworks/');