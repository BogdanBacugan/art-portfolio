<?php
session_start();
require_once '../includes/config.php';

header('Content-Type: application/json');

if (!isLoggedIn() || getUserRole() != 'artist') {
    echo json_encode(array('success' => false, 'message' => 'Доступ запрещен'));
    exit;
}

if (!empty($_FILES['image']['name'])) {
    $target_dir = "../assets/uploads/price_images/";
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $ext = pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION);
    $image_name = 'price_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
    $target_file = $target_dir . $image_name;
    
    $check = getimagesize($_FILES["image"]["tmp_name"]);
    if ($check !== false) {
        if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
            echo json_encode(array(
                'success' => true,
                'image_url' => $image_name,
                'full_url' => '/assets/uploads/price_images/' . $image_name
            ));
            exit;
        }
    }
}

echo json_encode(array('success' => false, 'message' => 'Ошибка загрузки'));
?>