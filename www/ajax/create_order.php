<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(array('success' => false, 'message' => 'Требуется авторизация'));
    exit;
}

$client_id = $_SESSION['user_id'];
$artist_id = isset($_POST['artist_id']) ? (int)$_POST['artist_id'] : 0;
$price_list_id = isset($_POST['price_list_id']) ? (int)$_POST['price_list_id'] : 0;
$title = isset($_POST['title']) ? trim($_POST['title']) : '';
$price = isset($_POST['price']) ? floatval($_POST['price']) : 0;
$currency = isset($_POST['currency']) ? trim($_POST['currency']) : 'RUB';
$client_message = isset($_POST['client_message']) ? trim($_POST['client_message']) : '';

if (!$artist_id) {
    echo json_encode(array('success' => false, 'message' => 'Неверный ID художника'));
    exit;
}

if (empty($title) || $price <= 0) {
    echo json_encode(array('success' => false, 'message' => 'Заполните все поля'));
    exit;
}

try {
    $artwork_id = null;
    if ($price_list_id) {
        $stmt = $pdo->prepare("SELECT artwork_id FROM price_list WHERE id = ?");
        $stmt->execute(array($price_list_id));
        $price_item = $stmt->fetch();
        if ($price_item) {
            $artwork_id = $price_item['artwork_id'];
        }
    }
    
    // Создаем заказ
    $stmt = $pdo->prepare("
        INSERT INTO orders (client_id, artist_id, artwork_id, price_list_id, title, price, currency, client_message, status) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'new')
    ");
    $stmt->execute(array($client_id, $artist_id, $artwork_id, $price_list_id, $title, $price, $currency, $client_message));
    $order_id = $pdo->lastInsertId();
    
    // Генерируем ключ для шифрования
    $encryption_key = get_order_key($order_id, $client_id, $artist_id);
    
    // Сохраняем первое сообщение - ТОЛЬКО ЗАШИФРОВАННОЕ (без поля message)
    if (!empty($client_message)) {
        $encrypted_msg = encrypt_message($client_message, $encryption_key);
        $stmt = $pdo->prepare("
            INSERT INTO order_messages (order_id, user_id, encrypted_message, is_encrypted) 
            VALUES (?, ?, ?, 1)
        ");
        $stmt->execute(array($order_id, $client_id, $encrypted_msg));
    }
    
    // Получаем информацию о клиенте
    $stmt = $pdo->prepare("SELECT username, full_name FROM users WHERE id = ?");
    $stmt->execute(array($client_id));
    $client = $stmt->fetch();
    $client_name = !empty($client['full_name']) ? $client['full_name'] : $client['username'];
    $symbol = $currency == 'USD' ? '$' : '₽';
    
    // Создаем уведомление для художника
    $stmt = $pdo->prepare("
        INSERT INTO notifications (user_id, type, source_user_id, source_id, message) 
        VALUES (?, 'order', ?, ?, ?)
    ");
    $message = $client_name . " сделал(а) заказ: " . $title . " на сумму " . number_format($price, 0, '', ' ') . " " . $symbol;
    if (!empty($client_message)) {
        $short_msg = mb_substr($client_message, 0, 50);
        if (mb_strlen($client_message) > 50) $short_msg .= '...';
        $message .= " с сообщением: \"" . $short_msg . "\"";
    }
    $stmt->execute(array($artist_id, $client_id, $order_id, $message));
    
    echo json_encode(array(
        'success' => true,
        'order_id' => $order_id,
        'message' => 'Заказ отправлен художнику!'
    ));
    
} catch (Exception $e) {
    echo json_encode(array('success' => false, 'message' => 'Ошибка: ' . $e->getMessage()));
}
?>