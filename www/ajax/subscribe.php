<?php
session_start();
require_once '../includes/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(array('success' => false, 'message' => 'Требуется авторизация'));
    exit;
}

$follower_id = $_SESSION['user_id'];
$following_id = isset($_POST['artist_id']) ? (int)$_POST['artist_id'] : 0;

if (!$following_id) {
    echo json_encode(array('success' => false, 'message' => 'Неверный ID художника'));
    exit;
}

if ($follower_id == $following_id) {
    echo json_encode(array('success' => false, 'message' => 'Нельзя подписаться на себя'));
    exit;
}

try {
    // Проверяем, что на кого подписываются - художник
    $stmt_check_artist = $pdo->prepare("SELECT id, username FROM users WHERE id = ? AND role = 'artist'");
    $stmt_check_artist->execute(array($following_id));
    $artist = $stmt_check_artist->fetch();
    
    if (!$artist) {
        echo json_encode(array('success' => false, 'message' => 'Подписываться можно только на художников'));
        exit;
    }
    
    // Получаем информацию о подписчике
    $stmt = $pdo->prepare("SELECT username, full_name FROM users WHERE id = ?");
    $stmt->execute(array($follower_id));
    $follower = $stmt->fetch();
    
    // Проверяем, есть ли уже подписка
    $stmt = $pdo->prepare("SELECT id FROM subscriptions WHERE follower_id = ? AND following_id = ?");
    $stmt->execute(array($follower_id, $following_id));
    $existing = $stmt->fetch();
    
    if ($existing) {
        // Отписываемся
        $stmt = $pdo->prepare("DELETE FROM subscriptions WHERE follower_id = ? AND following_id = ?");
        $stmt->execute(array($follower_id, $following_id));
        $action = 'unsubscribed';
        
        // Удаляем уведомление о подписке
        $stmt = $pdo->prepare("DELETE FROM notifications WHERE user_id = ? AND source_user_id = ? AND type = 'subscribe'");
        $stmt->execute(array($following_id, $follower_id));
        
    } else {
        // Подписываемся
        $stmt = $pdo->prepare("INSERT INTO subscriptions (follower_id, following_id) VALUES (?, ?)");
        $stmt->execute(array($follower_id, $following_id));
        $action = 'subscribed';
        
        // Создаем уведомление
        $follower_name = !empty($follower['full_name']) ? $follower['full_name'] : $follower['username'];
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, type, source_user_id, message) 
            VALUES (?, 'subscribe', ?, ?)
        ");
        $message = $follower_name . " подписался(ась) на вас";
        $stmt->execute(array($following_id, $follower_id, $message));
    }
    
    // Получаем общее количество подписчиков
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM subscriptions WHERE following_id = ?");
    $stmt->execute(array($following_id));
    $subscribers_count = $stmt->fetchColumn();
    
    echo json_encode(array(
        'success' => true,
        'action' => $action,
        'subscribers_count' => (int)$subscribers_count
    ));
    
} catch (Exception $e) {
    echo json_encode(array('success' => false, 'message' => $e->getMessage()));
}
?>