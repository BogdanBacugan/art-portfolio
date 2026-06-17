<?php
/**
 * Вспомогательные функции для сайта
 */

/**
 * Форматирование цены с валютой
 */
function format_price($price, $currency) {
    if ($price == null || $price == 0) return '';
    $symbol = $currency == 'USD' ? '$' : '₽';
    return number_format($price, 0, '', ' ') . ' ' . $symbol;
}

/**
 * Получение количества непрочитанных уведомлений
 */
function getUnreadNotificationsCount($user_id, $pdo) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = FALSE");
    $stmt->execute(array($user_id));
    return $stmt->fetchColumn();
}

/**
 * Получение количества новых заказов (для художника)
 */
function getNewOrdersCount($user_id, $pdo) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE artist_id = ? AND status = 'new'");
    $stmt->execute(array($user_id));
    return $stmt->fetchColumn();
}

/**
 * Генерация ключа для заказа
 */
function get_order_key($order_id, $client_id, $artist_id) {
    return md5($order_id . $client_id . $artist_id . SALT);
}

/**
 * Шифрование текста
 */
function encrypt_message($data, $key) {
    if (empty($data)) return '';
    $result = '';
    $key_len = strlen($key);
    for ($i = 0; $i < strlen($data); $i++) {
        $char = substr($data, $i, 1);
        $keychar = substr($key, $i % $key_len, 1);
        $char = chr(ord($char) + ord($keychar));
        $result .= $char;
    }
    return base64_encode($result);
}

/**
 * Дешифрование текста
 */
function decrypt_message($data, $key) {
    if (empty($data)) return '';
    $data = base64_decode($data);
    $result = '';
    $key_len = strlen($key);
    for ($i = 0; $i < strlen($data); $i++) {
        $char = substr($data, $i, 1);
        $keychar = substr($key, $i % $key_len, 1);
        $char = chr(ord($char) - ord($keychar));
        $result .= $char;
    }
    return $result;
}
?>