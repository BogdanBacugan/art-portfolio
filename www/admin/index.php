<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../includes/config.php';

if (!isLoggedIn() || getUserRole() != 'artist') {
    header('Location: ../login.php');
    exit;
}

$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM artworks WHERE artist_id = ?");
$stmt->execute(array($_SESSION['user_id']));
$row = $stmt->fetch();
$artworks_count = $row['total'];

$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM messages WHERE receiver_id = ? AND is_read = FALSE");
$stmt->execute(array($_SESSION['user_id']));
$row = $stmt->fetch();
$new_messages = $row['total'];

$stmt = $pdo->prepare("
    SELECT * FROM artworks 
    WHERE artist_id = ? 
    ORDER BY created_at DESC 
    LIMIT 5
");
$stmt->execute(array($_SESSION['user_id']));
$recent_artworks = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Панель художника</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        .admin-container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0; }
        .stat-card { background: #f5f5f5; padding: 20px; border-radius: 8px; text-align: center; }
        .stat-value { font-size: 2rem; font-weight: bold; color: #333; }
        .stat-label { color: #666; margin-top: 5px; }
        .admin-actions { margin: 20px 0; display: flex; gap: 10px; }
        .btn { padding: 10px 20px; text-decoration: none; border-radius: 4px; }
        .btn-primary { background: #007bff; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .table th, .table td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        .table th { background: #f8f9fa; }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="admin-container">
        <h1>Панель управления</h1>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?= $artworks_count ?></div>
                <div class="stat-label">Всего работ</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $new_messages ?></div>
                <div class="stat-label">Новых сообщений</div>
            </div>
        </div>
        
        <div class="admin-actions">
            <a href="artworks/create.php" class="btn btn-primary">➕ Добавить работу</a>
            <a href="artworks/index.php" class="btn btn-secondary">📋 Все работы</a>
            <a href="messages.php" class="btn btn-secondary">✉️ Сообщения</a>
        </div>
        
        <h2>Последние добавленные работы</h2>
        <div class="recent-works">
            <?php if (empty($recent_artworks)): ?>
                <p>У вас пока нет работ. <a href="artworks/create.php">Добавьте первую!</a></p>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Изображение</th>
                            <th>Название</th>
                            <th>Дата</th>
                            <th>Цена</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_artworks as $work): ?>
                        <tr>
                            <td>
                                <img src="/assets/uploads/artworks/<?= h($work['image_url']) ?>" 
                                     alt="" style="width: 50px; height: 50px; object-fit: cover;">
                            </td>
                            <td><?= h($work['title']) ?></td>
                            <td><?= date('d.m.Y', strtotime($work['created_at'])) ?></td>
                            <td><?= $work['price'] ? number_format($work['price'], 0, '', ' ') . ' ₽' : '—' ?></td>
                            <td>
                                <a href="artworks/edit.php?id=<?= $work['id'] ?>">✏️</a>
                                <a href="artworks/delete.php?id=<?= $work['id'] ?>" 
                                   onclick="return confirm('Удалить работу?')">🗑️</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
</body>
</html>