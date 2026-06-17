<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user_id = $_SESSION['user_id'];

// Получаем информацию о заказе
$stmt = $pdo->prepare("
    SELECT o.*, 
           c.username as client_username, c.full_name as client_full_name, c.avatar as client_avatar,
           a.username as artist_username, a.full_name as artist_full_name, a.avatar as artist_avatar
    FROM orders o
    JOIN users c ON o.client_id = c.id
    JOIN users a ON o.artist_id = a.id
    WHERE o.id = ? AND (o.client_id = ? OR o.artist_id = ?)
");
$stmt->execute(array($order_id, $user_id, $user_id));
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header('Location: orders.php');
    exit;
}

// Генерируем ключ для шифрования
$encryption_key = get_order_key($order_id, $order['client_id'], $order['artist_id']);

$is_client = ($user_id == $order['client_id']);
$is_artist = ($user_id == $order['artist_id']);
$other_id = $is_client ? $order['artist_id'] : $order['client_id'];

// Отправка сообщения (только зашифрованное)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $message = trim($_POST['message']);
    $image_name = null;
    
    if (!empty($_FILES['image']['name'])) {
        $target_dir = "assets/uploads/order_images/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $image_name = time() . '_' . basename($_FILES["image"]["name"]);
        $target_file = $target_dir . $image_name;
        
        $check = getimagesize($_FILES["image"]["tmp_name"]);
        if ($check !== false) {
            move_uploaded_file($_FILES["image"]["tmp_name"], $target_file);
        } else {
            $image_name = null;
        }
    }
    
    if (!empty($message) || $image_name) {
        // Шифруем сообщение
        $encrypted_message = encrypt_message($message, $encryption_key);
        
        // Сохраняем ТОЛЬКО зашифрованное сообщение
        $stmt = $pdo->prepare("
            INSERT INTO order_messages (order_id, user_id, encrypted_message, is_encrypted, image) 
            VALUES (?, ?, ?, 1, ?)
        ");
        $stmt->execute(array($order_id, $user_id, $encrypted_message, $image_name));
        
        $user_name = $_SESSION['user_name'] ?: $_SESSION['username'];
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, source_user_id, source_id, message) VALUES (?, 'order_message', ?, ?, ?)");
        $stmt->execute(array($other_id, $user_id, $order_id, $user_name . " написал(а) сообщение в заказе \"" . $order['title'] . "\""));
        
        header('Location: order_chat.php?id=' . $order_id);
        exit;
    }
}

// Получаем все сообщения (только зашифрованные)
$stmt = $pdo->prepare("
    SELECT m.*, u.username, u.full_name, u.avatar
    FROM order_messages m
    JOIN users u ON m.user_id = u.id
    WHERE m.order_id = ?
    ORDER BY m.created_at ASC
");
$stmt->execute(array($order_id));
$messages_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Дешифруем сообщения
$messages = array();
foreach ($messages_raw as $msg) {
    $decrypted = decrypt_message($msg['encrypted_message'], $encryption_key);
    $msg['display_message'] = !empty($decrypted) ? $decrypted : '[Сообщение недоступно]';
    $messages[] = $msg;
}

// Обновление статуса заказа
if (isset($_GET['update_status'])) {
    $status = $_GET['update_status'];
    $valid_statuses = array('new', 'in_progress', 'completed', 'cancelled');
    
    if (in_array($status, $valid_statuses) && $order['artist_id'] == $user_id) {
        $stmt = $pdo->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute(array($status, $order_id));
        
        $status_names = array(
            'new' => 'новый',
            'in_progress' => 'взят в работу',
            'completed' => 'выполнен',
            'cancelled' => 'отменен'
        );
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, type, source_user_id, source_id, message) 
            VALUES (?, 'order_status', ?, ?, ?)
        ");
        $message = "Статус вашего заказа \"" . $order['title'] . "\" изменен на: " . $status_names[$status];
        $stmt->execute(array($order['client_id'], $user_id, $order_id, $message));
        
        header('Location: order_chat.php?id=' . $order_id);
        exit;
    }
}

// Отмечаем сообщения как прочитанные
$stmt = $pdo->prepare("UPDATE order_messages SET is_read = TRUE WHERE order_id = ? AND user_id != ?");
$stmt->execute(array($order_id, $user_id));
?>

<?php include 'includes/header.php'; ?>

<style>
.chat-container {
    max-width: 800px;
    margin: 40px auto;
    padding: 20px;
}
.chat-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    overflow: hidden;
}
.chat-header {
    padding: 20px;
    background: #f8f9fa;
    border-bottom: 1px solid #eee;
}
.chat-header h1 {
    margin: 0 0 5px;
    font-size: 1.3rem;
}
.chat-header .order-info {
    font-size: 0.8rem;
    color: #666;
}
.chat-header .order-status {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.75rem;
    margin-top: 8px;
    font-weight: bold;
}
.status-new { background: #fff3cd; color: #856404; }
.status-in_progress { background: #cce5ff; color: #004085; }
.status-completed { background: #d4edda; color: #155724; }
.status-cancelled { background: #f8d7da; color: #721c24; }
.chat-messages {
    height: 400px;
    overflow-y: auto;
    padding: 20px;
    background: #fafafa;
}
.message {
    display: flex;
    gap: 12px;
    margin-bottom: 20px;
}
.message.my-message {
    flex-direction: row-reverse;
}
.message-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    object-fit: cover;
}
.message-avatar-placeholder {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    font-weight: bold;
}
.message-bubble {
    max-width: 70%;
    background: white;
    padding: 10px 15px;
    border-radius: 12px;
    box-shadow: 0 1px 2px rgba(0,0,0,0.1);
}
.my-message .message-bubble {
    background: #3498db;
    color: white;
}
.message-name {
    font-size: 0.7rem;
    font-weight: bold;
    margin-bottom: 3px;
    color: #666;
}
.my-message .message-name {
    color: #e0e0e0;
}
.message-text {
    font-size: 0.9rem;
    word-wrap: break-word;
}
.message-image {
    margin-top: 8px;
    max-width: 200px;
}
.message-image img {
    max-width: 100%;
    border-radius: 8px;
}
.message-time {
    font-size: 0.6rem;
    color: #999;
    margin-top: 5px;
    text-align: right;
}
.my-message .message-time {
    color: #c0e0ff;
}
.chat-input {
    padding: 20px;
    border-top: 1px solid #eee;
    background: white;
}
.chat-input form {
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.chat-input textarea {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 8px;
    resize: vertical;
    font-family: inherit;
}
.chat-input .input-group {
    display: flex;
    gap: 10px;
    align-items: flex-end;
}
.chat-input .input-group textarea {
    flex: 1;
}
.file-input {
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 8px;
    background: white;
}
.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 0.9rem;
    text-decoration: none;
    display: inline-block;
}
.btn-primary {
    background: #3498db;
    color: white;
}
.btn-primary:hover {
    background: #2980b9;
}
.btn-success {
    background: #28a745;
    color: white;
}
.btn-success:hover {
    background: #218838;
}
.btn-secondary {
    background: #6c757d;
    color: white;
}
.btn-danger {
    background: #dc3545;
    color: white;
}
.btn-back {
    background: #95a5a6;
    color: white;
}
.btn-back:hover {
    background: #7f8c8d;
}
.order-actions {
    display: flex;
    gap: 10px;
    margin-top: 15px;
    flex-wrap: wrap;
}
.participant-info {
    font-size: 0.8rem;
    color: #666;
    margin-top: 8px;
    padding-top: 8px;
    border-top: 1px solid #eee;
}
.empty-message {
    text-align: center;
    padding: 40px;
    color: #999;
}
</style>

<div class="chat-container">
    <a href="orders.php" class="btn-back btn" style="margin-bottom: 15px;">← Назад к списку заказов</a>
    
    <div class="chat-card">
        <div class="chat-header">
            <h1>📦 <?= htmlspecialchars($order['title']) ?></h1>
            <div class="order-info">
                Сумма: <strong><?= number_format($order['price'], 0, '', ' ') ?> <?= $order['currency'] == 'USD' ? '$' : '₽' ?></strong>
                <?php if ($order['description']): ?>
                    <br>Описание: <?= htmlspecialchars($order['description']) ?>
                <?php endif; ?>
            </div>
            <div class="order-status status-<?= $order['status'] ?>">
                <?php 
                    $status_names = array(
                        'new' => '🆕 Новый',
                        'in_progress' => '🔄 В работе',
                        'completed' => '✅ Выполнен',
                        'cancelled' => '❌ Отменен'
                    );
                    echo $status_names[$order['status']];
                ?>
            </div>
            <div class="participant-info">
                <?php if ($is_client): ?>
                    📝 Вы заказчик. Художник: <strong><?= htmlspecialchars($order['artist_full_name'] ?: $order['artist_username']) ?></strong>
                <?php else: ?>
                    🎨 Вы художник. Заказчик: <strong><?= htmlspecialchars($order['client_full_name'] ?: $order['client_username']) ?></strong>
                <?php endif; ?>
            </div>
            
            <?php if ($is_artist): ?>
                <div class="order-actions">
                    <?php if ($order['status'] != 'new'): ?>
                        <a href="?update_status=new&id=<?= $order_id ?>" class="btn btn-secondary">🆕 Вернуть в новый</a>
                    <?php endif; ?>
                    <?php if ($order['status'] != 'in_progress'): ?>
                        <a href="?update_status=in_progress&id=<?= $order_id ?>" class="btn btn-primary">🔄 Взять в работу</a>
                    <?php endif; ?>
                    <?php if ($order['status'] != 'completed'): ?>
                        <a href="?update_status=completed&id=<?= $order_id ?>" class="btn btn-success">✅ Завершить</a>
                    <?php endif; ?>
                    <?php if ($order['status'] != 'cancelled'): ?>
                        <a href="?update_status=cancelled&id=<?= $order_id ?>" class="btn btn-danger" onclick="return confirm('Отменить заказ?')">❌ Отменить</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="chat-messages" id="chat-messages">
            <?php if (empty($messages)): ?>
                <div class="empty-message">
                    <p>😔 Сообщений пока нет</p>
                    <p>Напишите что-нибудь, чтобы начать общение</p>
                </div>
            <?php else: ?>
                <?php foreach ($messages as $msg): ?>
                    <?php $is_my = ($msg['user_id'] == $user_id); ?>
                    <div class="message <?= $is_my ? 'my-message' : '' ?>">
                        <div>
                            <?php if (!empty($msg['avatar']) && file_exists('assets/uploads/avatars/'.$msg['avatar'])): ?>
                                <img src="assets/uploads/avatars/<?= htmlspecialchars($msg['avatar']) ?>" class="message-avatar">
                            <?php else: ?>
                                <div class="message-avatar-placeholder">
                                    <?= mb_substr(($msg['full_name'] ?: $msg['username']), 0, 1) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="message-bubble">
                            <div class="message-name"><?= htmlspecialchars($msg['full_name'] ?: $msg['username']) ?></div>
                            <div class="message-text"><?= nl2br(htmlspecialchars($msg['display_message'])) ?></div>
                            <?php if ($msg['image']): ?>
                                <div class="message-image">
                                    <a href="assets/uploads/order_images/<?= htmlspecialchars($msg['image']) ?>" target="_blank">
                                        <img src="assets/uploads/order_images/<?= htmlspecialchars($msg['image']) ?>" alt="Изображение">
                                    </a>
                                </div>
                            <?php endif; ?>
                            <div class="message-time"><?= date('d.m.Y H:i', strtotime($msg['created_at'])) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <!-- Чат всегда открыт -->
        <div class="chat-input">
            <form method="POST" enctype="multipart/form-data">
                <textarea name="message" rows="2" placeholder="Напишите сообщение..."></textarea>
                <div class="input-group">
                    <input type="file" name="image" accept="image/*" class="file-input">
                    <button type="submit" class="btn btn-primary">📩 Отправить</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
var messagesDiv = document.getElementById('chat-messages');
if (messagesDiv) {
    messagesDiv.scrollTop = messagesDiv.scrollHeight;
}
</script>

<?php include 'includes/footer.php'; ?>