<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

// Проверка авторизации и роли художника
if (!isLoggedIn() || getUserRole() != 'artist') {
    header('Location: ../login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Панель художника</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <header>
        <nav class="navbar">
            <div class="logo">
                <a href="../index.php">ArtPortfolio</a>
            </div>
            <ul class="nav-menu">
                <li><a href="index.php">📊 Дашборд</a></li>
                <li><a href="artworks/index.php">🖼️ Мои работы</a></li>
                <li><a href="artworks/create.php">➕ Добавить работу</a></li>
                <li><a href="price_list.php">💰 Прайс-лист</a></li>
                <li><a href="../logout.php">🚪 Выйти</a></li>
            </ul>
        </nav>
    </header>
    <main>