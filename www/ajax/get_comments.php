<?php
require_once '../includes/config.php';

header('Content-Type: application/json');

$artwork_id = isset($_GET['artwork_id']) ? (int)$_GET['artwork_id'] : 0;

if (!$artwork_id) {
    echo json_encode(['success' => false, 'message' => 'Неверный ID работы']);
    exit;
}

try {
    // Получаем комментарии
    $stmt = $pdo->prepare("
        SELECT c.*, u.username, u.full_name, u.avatar
        FROM comments c
        JOIN users u ON c.user_id = u.id
        WHERE c.artwork_id = ?
        ORDER BY c.created_at DESC
    ");
    $stmt->execute(array($artwork_id));
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $comments_data = array();
    foreach ($comments as $comment) {
        $comments_data[] = array(
            'id' => $comment['id'],
            'username' => $comment['username'],
            'full_name' => $comment['full_name'],
            'avatar' => $comment['avatar'],
            'comment' => nl2br(htmlspecialchars($comment['comment'])),
            'created_at' => date('d.m.Y H:i', strtotime($comment['created_at']))
        );
    }
    
    echo json_encode([
        'success' => true,
        'comments' => $comments_data
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>