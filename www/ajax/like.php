<?php
session_start();
require_once '../includes/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(array('success' => false, 'message' => 'Требуется авторизация'));
    exit;
}

$user_id = $_SESSION['user_id'];
$artwork_id = isset($_POST['artwork_id']) ? (int)$_POST['artwork_id'] : 0;

if (!$artwork_id) {
    echo json_encode(array('success' => false, 'message' => 'Неверный ID работы'));
    exit;
}

try {
    // Получаем информацию о работе
    $stmt = $pdo->prepare("SELECT artist_id, title FROM artworks WHERE id = ?");
    $stmt->execute(array($artwork_id));
    $artwork = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$artwork) {
        echo json_encode(array('success' => false, 'message' => 'Работа не найдена'));
        exit;
    }
    
    // Проверяем, есть ли уже лайк
    $stmt = $pdo->prepare("SELECT id FROM likes WHERE user_id = ? AND artwork_id = ?");
    $stmt->execute(array($user_id, $artwork_id));
    $existing = $stmt->fetch();
    
    if ($existing) {
        // Удаляем лайк
        $stmt = $pdo->prepare("DELETE FROM likes WHERE user_id = ? AND artwork_id = ?");
        $stmt->execute(array($user_id, $artwork_id));
        $action = 'unliked';
        
        // Удаляем уведомление о лайке
        $stmt = $pdo->prepare("DELETE FROM notifications WHERE user_id = ? AND source_user_id = ? AND type = 'like' AND source_id = ?");
        $stmt->execute(array($artwork['artist_id'], $user_id, $artwork_id));
        
    } else {
        // Добавляем лайк
        $stmt = $pdo->prepare("INSERT INTO likes (user_id, artwork_id) VALUES (?, ?)");
        $stmt->execute(array($user_id, $artwork_id));
        $action = 'liked';
        
        // Создаем уведомление для художника (если лайк не от себя)
        if ($user_id != $artwork['artist_id']) {
            $stmt = $pdo->prepare("
                INSERT INTO notifications (user_id, type, source_user_id, source_id, message) 
                VALUES (?, 'like', ?, ?, ?)
            ");
            $message = "поставил(а) лайк вашей работе \"" . $artwork['title'] . "\"";
            $stmt->execute(array($artwork['artist_id'], $user_id, $artwork_id, $message));
        }
    }
    
    // Получаем общее количество лайков
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM likes WHERE artwork_id = ?");
    $stmt->execute(array($artwork_id));
    $likes_count = $stmt->fetchColumn();
    
    echo json_encode(array(
        'success' => true,
        'action' => $action,
        'likes_count' => (int)$likes_count
    ));
    
} catch (Exception $e) {
    echo json_encode(array('success' => false, 'message' => $e->getMessage()));
}
?>