<?php
require_once 'includes/config.php';

echo "<h2>Сброс пароля</h2>";

// Укажите ваши данные
$username = 'guest'; // ЗАМЕНИТЕ НА ВАШ ЛОГИН
$new_password = '123456'; // НОВЫЙ ПАРОЛЬ

// Генерируем соль
$chars = './ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
$salt = '';
for ($i = 0; $i < 22; $i++) {
    $salt .= $chars[rand(0, 63)];
}
$salt = '$2y$10$' . $salt;

// Хешируем пароль
$hashed = crypt($new_password, $salt);

// Обновляем в базе
$stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE username = ?");
if ($stmt->execute(array($hashed, $username))) {
    echo "✅ Пароль для пользователя <strong>$username</strong> изменен на <strong>$new_password</strong><br>";
    echo "Хеш: $hashed<br>";
    echo "<br><a href='login.php'>➡ Перейти к входу</a>";
} else {
    echo "❌ Ошибка: пользователь '$username' не найден";
}

echo "<br><br><small>После входа УДАЛИТЕ этот файл!</small>";
?>