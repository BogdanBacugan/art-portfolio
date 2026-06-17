<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Проверяем авторизацию и роль художника
if (!isLoggedIn() || getUserRole() != 'artist') {
    header('Location: ../login.php');
    exit;
}

$error = '';
$success = '';
$artist_id = $_SESSION['user_id'];

// Получаем все работы художника для выбора
$artworks = $pdo->prepare("
    SELECT id, title FROM artworks 
    WHERE artist_id = ? 
    ORDER BY created_at DESC
");
$artworks->execute(array($artist_id));
$artworks_list = $artworks->fetchAll(PDO::FETCH_ASSOC);

// Добавление новой позиции
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_item'])) {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $price = floatval($_POST['price']);
    $artwork_id = !empty($_POST['artwork_id']) ? intval($_POST['artwork_id']) : null;
    $sort_order = intval($_POST['sort_order']);
    
    if (empty($title) || $price <= 0) {
        $error = 'Заполните название и цену';
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO price_list (artist_id, title, description, price, artwork_id, sort_order) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        if ($stmt->execute(array($artist_id, $title, $description, $price, $artwork_id, $sort_order))) {
            $success = 'Позиция добавлена!';
        } else {
            $error = 'Ошибка при добавлении';
        }
    }
}

// Редактирование позиции
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_item'])) {
    $item_id = intval($_POST['item_id']);
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $price = floatval($_POST['price']);
    $artwork_id = !empty($_POST['artwork_id']) ? intval($_POST['artwork_id']) : null;
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $sort_order = intval($_POST['sort_order']);
    
    $stmt = $pdo->prepare("
        UPDATE price_list 
        SET title = ?, description = ?, price = ?, artwork_id = ?, is_active = ?, sort_order = ?
        WHERE id = ? AND artist_id = ?
    ");
    if ($stmt->execute(array($title, $description, $price, $artwork_id, $is_active, $sort_order, $item_id, $artist_id))) {
        $success = 'Позиция обновлена!';
    } else {
        $error = 'Ошибка при обновлении';
    }
}

// Удаление позиции
if (isset($_GET['delete'])) {
    $item_id = intval($_GET['delete']);
    $stmt = $pdo->prepare("DELETE FROM price_list WHERE id = ? AND artist_id = ?");
    if ($stmt->execute(array($item_id, $artist_id))) {
        $success = 'Позиция удалена';
    } else {
        $error = 'Ошибка при удалении';
    }
}

// Получаем все позиции прайс-листа художника (только свои)
$stmt = $pdo->prepare("
    SELECT pl.*, a.title as artwork_title 
    FROM price_list pl
    LEFT JOIN artworks a ON pl.artwork_id = a.id
    WHERE pl.artist_id = ?
    ORDER BY pl.sort_order ASC, pl.created_at ASC
");
$stmt->execute(array($artist_id));
$price_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Мой прайс-лист</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .container {
            max-width: 1000px;
            margin: 40px auto;
            padding: 20px;
        }
        .card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 25px;
        }
        .card h1 {
            margin: 0 0 5px 0;
            font-size: 1.8rem;
        }
        .card p {
            color: #666;
            margin-bottom: 25px;
        }
        .divider {
            height: 1px;
            background: #eee;
            margin: 20px 0;
        }
        .price-form {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        .price-form h3 {
            margin: 0 0 15px 0;
        }
        .price-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .price-table th, .price-table td {
            padding: 12px;
            border: 1px solid #ddd;
            text-align: left;
        }
        .price-table th {
            background: #f5f5f5;
        }
        .price-table tr.inactive {
            background: #f8f9fa;
            color: #999;
        }
        .badge-active {
            background: #d4edda;
            color: #155724;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 12px;
        }
        .badge-inactive {
            background: #f8d7da;
            color: #721c24;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 12px;
        }
        .price-value {
            font-weight: bold;
            color: #2c3e50;
        }
        .edit-form {
            display: none;
            background: #fff;
            padding: 15px;
            margin-top: 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
        }
        .edit-form.active {
            display: block;
        }
        .btn-small {
            padding: 5px 10px;
            margin-right: 5px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 12px;
        }
        .btn-small.danger {
            background: #dc3545;
            color: white;
        }
        .form-row {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
        }
        .form-row .form-group {
            flex: 1;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary {
            background: #3498db;
            color: white;
        }
        .btn-primary:hover {
            background: #2980b9;
        }
        .alert {
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .alert.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .alert.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .empty-message {
            text-align: center;
            padding: 40px;
            background: #f8f9fa;
            border-radius: 8px;
            color: #666;
        }
        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: #3498db;
            text-decoration: none;
        }
        @media (max-width: 768px) {
            .price-table {
                display: block;
                overflow-x: auto;
            }
            .form-row {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <h1>💰 Мой прайс-лист</h1>
            <p>Здесь вы можете управлять своими ценами и услугами</p>
            
            <div class="divider"></div>
            
            <?php if ($error): ?>
                <div class="alert error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            
            <!-- Форма добавления новой позиции -->
            <div class="price-form">
                <h3>➕ Добавить новую позицию</h3>
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="title">Название услуги/работы *</label>
                        <input type="text" id="title" name="title" required>
                    </div>
                    <div class="form-group">
                        <label for="description">Описание</label>
                        <textarea id="description" name="description" rows="3" placeholder="Подробное описание услуги или работы"></textarea>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="price">Цена (₽) *</label>
                            <input type="number" id="price" name="price" step="100" min="0" required>
                        </div>
                        <div class="form-group">
                            <label for="sort_order">Порядок сортировки</label>
                            <input type="number" id="sort_order" name="sort_order" value="0">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="artwork_id">Привязать к работе (необязательно)</label>
                        <select id="artwork_id" name="artwork_id">
                            <option value="">-- Без привязки --</option>
                            <?php foreach ($artworks_list as $artwork): ?>
                                <option value="<?= $artwork['id'] ?>"><?= htmlspecialchars($artwork['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small>Если привязать к работе, на странице прайс-листа будет ссылка на эту работу</small>
                    </div>
                    <button type="submit" name="add_item" class="btn btn-primary">➕ Добавить</button>
                </form>
            </div>
            
            <!-- Список моих позиций -->
            <h3>📋 Мои позиции</h3>
            
            <?php if (empty($price_items)): ?>
                <div class="empty-message">
                    <p>😔 У вас пока нет позиций в прайс-листе</p>
                    <p>Добавьте первую позицию, чтобы клиенты знали ваши цены</p>
                </div>
            <?php else: ?>
                <table class="price-table">
                    <thead>
                        <tr>
                            <th>№</th>
                            <th>Название</th>
                            <th>Описание</th>
                            <th>Цена</th>
                            <th>Связанная работа</th>
                            <th>Статус</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($price_items as $item): ?>
                            <tr class="<?= $item['is_active'] ? '' : 'inactive' ?>">
                                <td><?= $item['sort_order'] ?></td>
                                <td><strong><?= htmlspecialchars($item['title']) ?></strong></td>
                                <td><?= nl2br(htmlspecialchars($item['description'])) ?></td>
                                <td class="price-value"><?= number_format($item['price'], 0, '', ' ') ?> ₽</td>
                                <td>
                                    <?php if ($item['artwork_id']): ?>
                                        <a href="../artwork.php?id=<?= $item['artwork_id'] ?>" target="_blank">
                                            <?= htmlspecialchars($item['artwork_title']) ?>
                                        </a>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($item['is_active']): ?>
                                        <span class="badge-active">Активен</span>
                                    <?php else: ?>
                                        <span class="badge-inactive">Скрыт</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button onclick="toggleEditForm(<?= $item['id'] ?>)" class="btn-small">✏️ Ред.</button>
                                    <a href="?delete=<?= $item['id'] ?>" class="btn-small danger" onclick="return confirm('Удалить позицию?')">🗑️ Уд.</a>
                                    
                                    <div id="edit-form-<?= $item['id'] ?>" class="edit-form">
                                        <form method="POST" action="">
                                            <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                                            <div class="form-group">
                                                <label>Название</label>
                                                <input type="text" name="title" value="<?= htmlspecialchars($item['title']) ?>" required>
                                            </div>
                                            <div class="form-group">
                                                <label>Описание</label>
                                                <textarea name="description" rows="2"><?= htmlspecialchars($item['description']) ?></textarea>
                                            </div>
                                            <div class="form-row">
                                                <div class="form-group">
                                                    <label>Цена</label>
                                                    <input type="number" name="price" value="<?= $item['price'] ?>" step="100" required>
                                                </div>
                                                <div class="form-group">
                                                    <label>Порядок</label>
                                                    <input type="number" name="sort_order" value="<?= $item['sort_order'] ?>">
                                                </div>
                                            </div>
                                            <div class="form-group">
                                                <label>Привязать к работе</label>
                                                <select name="artwork_id">
                                                    <option value="">-- Без привязки --</option>
                                                    <?php foreach ($artworks_list as $artwork): ?>
                                                        <option value="<?= $artwork['id'] ?>" <?= $item['artwork_id'] == $artwork['id'] ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($artwork['title']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="form-group">
                                                <label>
                                                    <input type="checkbox" name="is_active" <?= $item['is_active'] ? 'checked' : '' ?>>
                                                    Активен
                                                </label>
                                            </div>
                                            <button type="submit" name="edit_item" class="btn-primary">💾 Сохранить</button>
                                            <button type="button" onclick="toggleEditForm(<?= $item['id'] ?>)" style="background:#6c757d;color:white;padding:5px 10px;border:none;border-radius:4px;cursor:pointer;">Отмена</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            
            <a href="../artist.php?id=<?= $artist_id ?>" class="back-link">← Вернуться в профиль</a>
        </div>
    </div>
    
    <script>
    function toggleEditForm(id) {
        var form = document.getElementById('edit-form-' + id);
        if (form.classList.contains('active')) {
            form.classList.remove('active');
        } else {
            form.classList.add('active');
        }
    }
    </script>
</body>
</html>