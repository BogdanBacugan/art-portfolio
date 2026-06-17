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
$comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';

if (!$artwork_id) {
    echo json_encode(array('success' => false, 'message' => 'Неверный ID работы'));
    exit;
}

if (empty($comment)) {
    echo json_encode(array('success' => false, 'message' => 'Комментарий не может быть пустым'));
    exit;
}

try {
    // Получаем информацию о работе
    $stmt = $pdo->prepare("SELECT artist_id, title FROM artworks WHERE id = ?");
    $stmt->execute(array($artwork_id));
    $artwork = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Добавляем комментарий
    $stmt = $pdo->prepare("INSERT INTO comments (artwork_id, user_id, comment) VALUES (?, ?, ?)");
    $stmt->execute(array($artwork_id, $user_id, $comment));
    $comment_id = $pdo->lastInsertId();
    
    // Создаем уведомление для художника (если комментарий не от себя)
    if ($user_id != $artwork['artist_id']) {
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, type, source_user_id, source_id, message) 
            VALUES (?, 'comment', ?, ?, ?)
        ");
        $message = "оставил(а) комментарий к вашей работе \"" . $artwork['title'] . "\"";
        $stmt->execute(array($artwork['artist_id'], $user_id, $artwork_id, $message));
    }
    
    // Получаем информацию о пользователе
    $stmt = $pdo->prepare("SELECT username, full_name, avatar FROM users WHERE id = ?");
    $stmt->execute(array($user_id));
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $full_name = !empty($user['full_name']) ? $user['full_name'] : $user['username'];
    $first_letter = mb_substr($full_name, 0, 1);
    
    echo json_encode(array(
        'success' => true,
        'comment_id' => $comment_id,
        'username' => $user['username'],
        'full_name' => $full_name,
        'avatar' => $user['avatar'],
        'comment' => nl2br(htmlspecialchars($comment, ENT_QUOTES, 'UTF-8')),
        'created_at' => date('d.m.Y H:i'),
        'user_id' => $user_id,
        'first_letter' => $first_letter
    ));
    
} catch (Exception $e) {
    echo json_encode(array('success' => false, 'message' => $e->getMessage()));
}
?>