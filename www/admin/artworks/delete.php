<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../../includes/config.php';

if (!isLoggedIn() || getUserRole() != 'artist') {
    header('Location: ../../login.php');
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Проверяем, принадлежит ли работа этому художнику
$stmt = $pdo->prepare("SELECT image_url FROM artworks WHERE id = ? AND artist_id = ?");
$stmt->execute(array($id, $_SESSION['user_id']));
$work = $stmt->fetch();

if ($work) {
    // Удаляем файл изображения
    $file_path = "../../assets/uploads/artworks/" . $work['image_url'];
    if (file_exists($file_path)) {
        unlink($file_path);
    }
    
    // Удаляем из БД (теги удалятся автоматически из-за ON DELETE CASCADE)
    $stmt = $pdo->prepare("DELETE FROM artworks WHERE id = ?");
    $stmt->execute(array($id));
    
    $_SESSION['message'] = "Работа удалена";
}

header('Location: /portfolio.php');
exit;