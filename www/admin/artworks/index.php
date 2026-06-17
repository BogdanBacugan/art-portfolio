<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../../includes/config.php';

if (!isLoggedIn() || getUserRole() != 'artist') {
    header('Location: ../../login.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT a.*, c.name as category_name 
    FROM artworks a
    LEFT JOIN categories c ON a.category_id = c.id
    WHERE a.artist_id = ?
    ORDER BY a.created_at DESC
");
$stmt->execute(array($_SESSION['user_id']));
$artworks = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Все работы</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        .admin-container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .table th, .table td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        .table th { background: #f8f9fa; }
        .table tr:hover { background: #f5f5f5; }
        .btn { padding: 5px 10px; text-decoration: none; border-radius: 4px; margin: 0 2px; }
        .btn-primary { background: #007bff; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-sm { font-size: 0.875rem; }
        .action-cell { white-space: nowrap; }
        .price { font-weight: bold; color: #28a745; }
        .currency-usd { color: #17a2b8; }
        .currency-rub { color: #28a745; }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="admin-container">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <h1>Все работы</h1>
            <a href="create.php" class="btn btn-primary">➕ Добавить работу</a>
        </div>
        
        <?php if (empty($artworks)): ?>
            <p>У вас пока нет работ. <a href="create.php">Добавьте первую!</a></p>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Изображение</th>
                        <th>Название</th>
                        <th>Категория</th>
                        <th>Техника</th>
                        <th>Год</th>
                        <th>Цена</th>
                        <th>Продажа</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($artworks as $work): ?>
                    <tr>
                        <td>
                            <img src="/assets/uploads/artworks/<?= h($work['image_url']) ?>" 
                                 alt="" style="width: 60px; height: 60px; object-fit: cover; border-radius: 4px;">
                        </td>
                        <td><?= h($work['title']) ?></td>
                        <td><?= h($work['category_name'] ?: '—') ?></td>
                        <td><?= h($work['technique'] ?: '—') ?></td>
                        <td><?= $work['year'] ?: '—' ?></td>
                        <td class="price">
                            <?php if ($work['price']): ?>
                                <?= number_format($work['price'], 0, '', ' ') ?> 
                                <?php if ($work['currency'] == 'USD'): ?>
                                    <span class="currency-usd">$</span>
                                <?php else: ?>
                                    <span class="currency-rub">₽</span>
                                <?php endif; ?>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td><?= $work['is_for_sale'] ? '✅' : '❌' ?></td>
                        <td class="action-cell">
                            <a href="edit.php?id=<?= $work['id'] ?>" class="btn btn-primary btn-sm">✏️ Ред.</a>
                            <a href="delete.php?id=<?= $work['id'] ?>" 
                               class="btn btn-danger btn-sm" 
                               onclick="return confirm('Удалить работу?')">🗑️ Удал.</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
</body>
</html>