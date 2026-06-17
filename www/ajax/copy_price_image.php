<?php
session_start();
require_once '../includes/config.php';

header('Content-Type: application/json');

if (!isLoggedIn() || getUserRole() != 'artist') {
    echo json_encode(array('success' => false, 'message' => 'Доступ запрещен'));
    exit;
}

$image = isset($_POST['image']) ? trim($_POST['image']) : '';

if (empty($image)) {
    echo json_encode(array('success' => false, 'message' => 'Не указано изображение'));
    exit;
}

$source = $_SERVER['DOCUMENT_ROOT'] . "/assets/uploads/artworks/" . $image;
$target_dir = $_SERVER['DOCUMENT_ROOT'] . "/assets/uploads/price_images/";

if (!file_exists($target_dir)) {
    mkdir($target_dir, 0777, true);
}

$ext = pathinfo($image, PATHINFO_EXTENSION);
$new_name = 'price_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
$target = $target_dir . $new_name;

if (copy($source, $target)) {
    echo json_encode(array('success' => true, 'new_name' => $new_name));
} else {
    echo json_encode(array('success' => false, 'message' => 'Ошибка копирования'));
}
?>