<?php
$folder = $_SERVER['DOCUMENT_ROOT'] . "/assets/uploads/artworks/";
$files = scandir($folder);
echo "<h2>Файлы в папке:</h2>";
foreach ($files as $file) {
    if ($file != '.' && $file != '..') {
        echo "<div>";
        echo "Файл: " . $file . "<br>";
        echo "<img src='/assets/uploads/artworks/" . $file . "' style='max-width: 200px;'><br><br>";
        echo "</div>";
    }
}
?>