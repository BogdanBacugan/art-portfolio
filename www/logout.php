<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// АБСОЛЮТНО НИЧЕГО не выводим до header()
require_once 'includes/config.php';

// Очищаем все данные сессии
$_SESSION = array();

// Уничтожаем сессию
session_destroy();

// Перенаправляем на страницу входа
header('Location: login.php');
exit;
?>