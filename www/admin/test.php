<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Сначала подключаем config
require_once '../includes/config.php';

// Только после этого выводим
echo "Начинаем...<br>";
echo "Config подключен!<br>";

echo "isLoggedIn() = " . (isLoggedIn() ? 'true' : 'false') . "<br>";
echo "getUserRole() = " . getUserRole() . "<br>";

// Проверка подключения к БД
$stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
$row = $stmt->fetch();
echo "Всего пользователей в БД: " . $row['count'] . "<br>";

phpinfo();