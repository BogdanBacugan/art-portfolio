<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Проверка загрузки файлов</h2>";

$upload_dir = $_SERVER['DOCUMENT_ROOT'] . "/assets/uploads/artworks/";
echo "Папка для загрузок: " . $upload_dir . "<br>";

if (file_exists($upload_dir)) {
    echo "✅ Папка существует<br>";
    
    $files = scandir($upload_dir);
    $images = array();
    foreach ($files as $file) {
        if ($file != '.' && $file != '..' && !is_dir($upload_dir . $file)) {
            $images[] = $file;
        }
    }
    
    echo "<h3>Найдено файлов: " . count($images) . "</h3>";
    
    if (count($images) > 0) {
        echo "<div style='display: flex; flex-wrap: wrap; gap: 20px;'>";
        foreach ($images as $img) {
            $img_url = "/assets/uploads/artworks/" . $img;
            echo "<div style='border: 1px solid #ddd; padding: 10px; width: 200px;'>";
            echo "<img src='" . $img_url . "' style='width: 100%; height: 150px; object-fit: cover;'><br>";
            echo $img . "<br>";
            echo "<a href='" . $img_url . "' target='_blank'>Открыть</a>";
            echo "</div>";
        }
        echo "</div>";
    } else {
        echo "❌ Нет файлов в папке загрузок<br>";
    }
} else {
    echo "❌ Папка не существует<br>";
    echo "Пытаюсь создать...";
    if (mkdir($upload_dir, 0777, true)) {
        echo "✅ Создано!";
    } else {
        echo "❌ Не удалось создать";
    }
}

// Проверяем последние добавленные работы в БД
echo "<h2>Последние работы в базе данных</h2>";
require_once 'includes/config.php';

$stmt = $pdo->query("SELECT id, title, image_url FROM artworks ORDER BY id DESC LIMIT 5");
$works = $stmt->fetchAll();

if (count($works) > 0) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Название</th><th>image_url</th><th>Проверка</th></tr>";
    foreach ($works as $work) {
        echo "<tr>";
        echo "<td>" . $work['id'] . "</td>";
        echo "<td>" . h($work['title']) . "</td>";
        echo "<td>" . $work['image_url'] . "</td>";
        $file_path = $_SERVER['DOCUMENT_ROOT'] . "/assets/uploads/artworks/" . $work['image_url'];
        if (file_exists($file_path)) {
            echo "<td>✅ Файл существует</td>";
        } else {
            echo "<td>❌ Файл НЕ существует: " . $file_path . "</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "В базе нет работ";
}
?>