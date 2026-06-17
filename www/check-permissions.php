<?php
$folder = 'assets/uploads/artworks/';

echo "<h2>Проверка прав на папку: $folder</h2>";

// Проверяем, существует ли папка
if (file_exists($folder)) {
    echo "✅ Папка существует<br>";
    
    // Проверяем права на запись
    if (is_writable($folder)) {
        echo "✅ Папка доступна для записи<br>";
    } else {
        echo "❌ Папка НЕ доступна для записи<br>";
        echo "Текущие права: " . substr(sprintf('%o', fileperms($folder)), -4) . "<br>";
    }
    
    // Пробуем создать тестовый файл
    $test_file = $folder . 'test.txt';
    if (file_put_contents($test_file, 'test')) {
        echo "✅ Тестовый файл создан<br>";
        unlink($test_file); // удаляем
        echo "✅ Тестовый файл удален<br>";
    } else {
        echo "❌ Не удалось создать тестовый файл<br>";
    }
} else {
    echo "❌ Папка НЕ существует<br>";
    // Пробуем создать папку
    if (mkdir($folder, 0777, true)) {
        echo "✅ Папка создана!<br>";
    } else {
        echo "❌ Не удалось создать папку<br>";
    }
}
?>