<?php
session_start();
require_once '../includes/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(array('success' => false, 'message' => 'Требуется авторизация'));
    exit;
}

$buyer_id = $_SESSION['user_id'];
$artwork_id = isset($_POST['artwork_id']) ? (int)$_POST['artwork_id'] : 0;
$title = isset($_POST['title']) ? trim($_POST['title']) : '';
$price = isset($_POST['price']) ? floatval($_POST['price']) : 0;
$currency = isset($_POST['currency']) ? trim($_POST['currency']) : 'RUB';
$message = isset($_POST['message']) ? trim($_POST['message']) : '';

if (!$artwork_id) {
    echo json_encode(array('success' => false, 'message' => 'Неверный ID работы'));
    exit;
}

try {
    // Получаем информацию о работе
    $stmt = $pdo->prepare("SELECT artist_id, title, is_sold, is_for_sale FROM artworks WHERE id = ?");
    $stmt->execute(array($artwork_id));
    $artwork = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$artwork) {
        echo json_encode(array('success' => false, 'message' => 'Работа не найдена'));
        exit;
    }
    
    if ($artwork['is_sold']) {
        echo json_encode(array('success' => false, 'message' => 'Эта работа уже продана'));
        exit;
    }
    
    if (!$artwork['is_for_sale']) {
        echo json_encode(array('success' => false, 'message' => 'Эта работа не продается'));
        exit;
    }
    
    if ($artwork['artist_id'] == $buyer_id) {
        echo json_encode(array('success' => false, 'message' => 'Нельзя купить свою работу'));
        exit;
    }
    
    // Отмечаем работу как проданную
    $stmt = $pdo->prepare("UPDATE artworks SET is_sold = TRUE, is_available = FALSE WHERE id = ?");
    $stmt->execute(array($artwork_id));
    
    // Создаем заказ
    $stmt = $pdo->prepare("
        INSERT INTO orders (client_id, artist_id, artwork_id, title, price, currency, client_message, status) 
        VALUES (?, ?, ?, ?, ?, ?, ?, 'new')
    ");
    $stmt->execute(array($buyer_id, $artwork['artist_id'], $artwork_id, $title, $price, $currency, $message));
    $order_id = $pdo->lastInsertId();
    
    // Получаем информацию о покупателе
    $stmt = $pdo->prepare("SELECT username, full_name FROM users WHERE id = ?");
    $stmt->execute(array($buyer_id));
    $buyer = $stmt->fetch();
    $buyer_name = !empty($buyer['full_name']) ? $buyer['full_name'] : $buyer['username'];
    $symbol = $currency == 'USD' ? '$' : '₽';
    
    // Создаем уведомление для художника
    $stmt = $pdo->prepare("
        INSERT INTO notifications (user_id, type, source_user_id, source_id, message) 
        VALUES (?, 'purchase', ?, ?, ?)
    ");
    $message_text = $buyer_name . " купил(а) вашу работу \"" . $artwork['title'] . "\" за " . number_format($price, 0, '', ' ') . " " . $symbol;
    $stmt->execute(array($artwork['artist_id'], $buyer_id, $artwork_id, $message_text));
    
    echo json_encode(array(
        'success' => true,
        'order_id' => $order_id,
        'message' => 'Поздравляем! Вы купили работу. Художник свяжется с вами в ближайшее время.'
    ));
    
} catch (Exception $e) {
    echo json_encode(array('success' => false, 'message' => $e->getMessage()));
}
?>