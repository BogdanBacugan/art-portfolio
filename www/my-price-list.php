<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

if (!isLoggedIn() || getUserRole() != 'artist') {
    header('Location: login.php');
    exit;
}

$error = '';
$success = '';
$artist_id = $_SESSION['user_id'];
$show_form = isset($_GET['create']) ? true : false;

$artworks = $pdo->prepare("
    SELECT id, title, image_url FROM artworks 
    WHERE artist_id = ? 
    ORDER BY created_at DESC
");
$artworks->execute(array($artist_id));
$artworks_list = $artworks->fetchAll(PDO::FETCH_ASSOC);

// Добавление
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_item'])) {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $price = floatval($_POST['price']);
    $currency = isset($_POST['currency']) ? $_POST['currency'] : 'RUB';
    $artwork_id = !empty($_POST['artwork_id']) ? intval($_POST['artwork_id']) : null;
    $sort_order = intval($_POST['sort_order']);
    $images = isset($_POST['images']) ? trim($_POST['images']) : '';
    
    if (empty($title) || $price <= 0) {
        $error = 'Заполните название и цену';
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO price_list (artist_id, title, description, price, currency, artwork_id, sort_order, images) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        if ($stmt->execute(array($artist_id, $title, $description, $price, $currency, $artwork_id, $sort_order, $images))) {
            $success = 'Позиция добавлена!';
            $show_form = false;
        } else {
            $error = 'Ошибка при добавлении';
        }
    }
}

// Редактирование
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_item'])) {
    $item_id = intval($_POST['item_id']);
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $price = floatval($_POST['price']);
    $currency = isset($_POST['currency']) ? $_POST['currency'] : 'RUB';
    $artwork_id = !empty($_POST['artwork_id']) ? intval($_POST['artwork_id']) : null;
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $sort_order = intval($_POST['sort_order']);
    $images = isset($_POST['images']) ? trim($_POST['images']) : '';
    
    $stmt = $pdo->prepare("
        UPDATE price_list 
        SET title = ?, description = ?, price = ?, currency = ?, artwork_id = ?, is_active = ?, sort_order = ?, images = ?
        WHERE id = ? AND artist_id = ?
    ");
    if ($stmt->execute(array($title, $description, $price, $currency, $artwork_id, $is_active, $sort_order, $images, $item_id, $artist_id))) {
        $success = 'Позиция обновлена!';
    } else {
        $error = 'Ошибка при обновлении';
    }
}

// Удаление
if (isset($_GET['delete'])) {
    $item_id = intval($_GET['delete']);
    $stmt = $pdo->prepare("DELETE FROM price_list WHERE id = ? AND artist_id = ?");
    if ($stmt->execute(array($item_id, $artist_id))) {
        $success = 'Позиция удалена';
    } else {
        $error = 'Ошибка при удалении';
    }
}

// Получаем все позиции
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

<?php include 'includes/header.php'; ?>

<style>
.price-container {
    max-width: 1200px;
    margin: 40px auto;
    padding: 20px;
}
.price-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    padding: 30px;
}
.price-card h1 {
    font-size: 1.8rem;
    margin-bottom: 5px;
}
.price-card > p {
    color: #666;
    margin-bottom: 25px;
    padding-bottom: 15px;
    border-bottom: 1px solid #eee;
}
.form-box {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 30px;
}
.form-box h3 {
    margin-bottom: 15px;
}
.form-group {
    margin-bottom: 15px;
}
.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
}
.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 1rem;
}
.form-row {
    display: flex;
    gap: 15px;
}
.form-row .form-group {
    flex: 1;
}
.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 6px;
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
.btn-secondary {
    background: #6c757d;
    color: white;
}
.btn-success {
    background: #28a745;
    color: white;
}
.create-btn {
    text-align: center;
    margin: 20px 0;
}

/* ТАБЛИЦА */
.price-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}
.price-table th,
.price-table td {
    padding: 10px 8px;
    border: 1px solid #ddd;
    text-align: left;
    vertical-align: top;
}
.price-table th {
    background: #f5f5f5;
    font-weight: 600;
}
/* ФИКСИРОВАННАЯ ШИРИНА КОЛОНОК */

.price-table th:nth-child(2) { width: 18%; }  /* Название */
.price-table th:nth-child(3) { width: 28%; }  /* Описание */
.price-table th:nth-child(4) { width: 90px; } /* Цена */
.price-table th:nth-child(5) { width: 130px; } /* Примеры - под размер карусели (120px + отступы) */
.price-table th:nth-child(6) { width: 70px; text-align: center; } /* Статус */
.price-table th:nth-child(7) { width: 90px; text-align: center; } /* Действия */

.price-table tr.inactive {
    background: #f8f9fa;
    color: #999;
}
.badge-active {
    background: #d4edda;
    color: #155724;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 11px;
}
.badge-inactive {
    background: #f8d7da;
    color: #721c24;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 11px;
}
.price-value {
    font-weight: bold;
    color: #2c3e50;
}
.empty-message {
    text-align: center;
    padding: 40px;
    background: #f8f9fa;
    border-radius: 8px;
    color: #666;
}
.alert {
    padding: 12px;
    margin-bottom: 20px;
    border-radius: 6px;
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

/* КНОПКИ ДЕЙСТВИЙ */
.btn-edit-icon {
    background: none;
    border: none;
    cursor: pointer;
    font-size: 1.1rem;
    padding: 5px;
    margin: 0;
    transition: transform 0.2s;
    color: #3498db;
}
.btn-edit-icon:hover {
    transform: scale(1.1);
}
.btn-delete-icon {
    background: none;
    border: none;
    cursor: pointer;
    font-size: 1.1rem;
    padding: 5px;
    margin: 0;
    transition: transform 0.2s;
    color: #e74c3c;
}
.btn-delete-icon:hover {
    transform: scale(1.1);
}
.action-cell {
    white-space: nowrap;
    text-align: center;
}

/* ФОРМА РЕДАКТИРОВАНИЯ */
.edit-form {
    display: none;
    background: #f8f9fa;
    padding: 15px;
    margin-top: 15px;
    border-radius: 8px;
    border-left: 3px solid #3498db;
}
.edit-form.active {
    display: block;
}
.edit-form .form-group {
    margin-bottom: 10px;
}
.edit-form .form-group label {
    display: block;
    margin-bottom: 4px;
    font-weight: 500;
    font-size: 0.8rem;
    color: #555;
}
.edit-form .form-group input,
.edit-form .form-group select,
.edit-form .form-group textarea {
    width: 100%;
    padding: 6px 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 0.85rem;
    box-sizing: border-box;
}
.edit-form .form-row {
    display: flex;
    gap: 10px;
    margin-bottom: 10px;
}
.edit-form .form-row .form-group {
    flex: 1;
    margin-bottom: 0;
}
.edit-form .checkbox-group {
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 10px 0;
}
.edit-form .form-actions {
    display: flex;
    gap: 10px;
    margin-top: 12px;
}
.edit-form .btn-save {
    background: #28a745;
    color: white;
    border: none;
    padding: 6px 15px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 0.8rem;
}
.edit-form .btn-cancel {
    background: #6c757d;
    color: white;
    border: none;
    padding: 6px 15px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 0.8rem;
}

/* КАРУСЕЛЬ */
.carousel {
    position: relative;
    width: 120px;
    height: 90px;
    overflow: hidden;
    border-radius: 6px;
    background: #f0f0f0;
    margin: 0 auto; 
}
.carousel-inner {
    width: 100%;
    height: 100%;
    position: relative;
}
.carousel-item {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    opacity: 0;
    transition: opacity 0.3s ease;
}
.carousel-item.active {
    opacity: 1;
}
.carousel-item img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.carousel-prev, .carousel-next {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    background: rgba(0,0,0,0.5);
    color: white;
    border: none;
    cursor: pointer;
    padding: 2px 6px;
    border-radius: 50%;
    font-size: 10px;
    z-index: 10;
}
.carousel-prev:hover, .carousel-next:hover {
    background: rgba(0,0,0,0.8);
}
.carousel-prev { left: 2px; }
.carousel-next { right: 2px; }

/* ВЫБОР ИЗОБРАЖЕНИЙ */
.images-control {
    margin-top: 10px;
}
.image-buttons {
    display: flex;
    gap: 10px;
    margin-bottom: 10px;
}
.btn-small {
    padding: 5px 12px;
    font-size: 0.75rem;
    border: none;
    border-radius: 4px;
    cursor: pointer;
}
.btn-upload {
    background: #28a745;
    color: white;
}
.btn-select {
    background: #3498db;
    color: white;
}
.images-preview {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 10px;
}
.preview-img {
    position: relative;
    width: 60px;
    height: 60px;
}
.preview-img img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 4px;
    border: 1px solid #ddd;
}
.preview-img .remove {
    position: absolute;
    top: -6px;
    right: -6px;
    background: #e74c3c;
    color: white;
    border-radius: 50%;
    width: 18px;
    height: 18px;
    font-size: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
}
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
    justify-content: center;
    align-items: center;
}
.modal.active {
    display: flex;
}
.modal-content {
    background: white;
    border-radius: 12px;
    max-width: 600px;
    width: 90%;
    max-height: 80%;
    overflow: auto;
    padding: 20px;
}
.modal-content h3 {
    margin: 0 0 15px 0;
}
.modal-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
    gap: 10px;
}
.modal-work {
    cursor: pointer;
    text-align: center;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 8px;
    transition: all 0.2s;
}
.modal-work:hover {
    border-color: #3498db;
    background: #f0f0f0;
}
.modal-work img {
    width: 80px;
    height: 80px;
    object-fit: cover;
    border-radius: 4px;
}
.modal-work span {
    display: block;
    font-size: 0.7rem;
    margin-top: 5px;
}
/* Мобильная версия для прайс-листа */
@media (max-width: 768px) {
    /* Скрываем шапку таблицы на мобильных */
    .price-table thead {
        display: none;
    }
    
    /* Превращаем строки в блоки */
    .price-table tbody,
    .price-table tr,
    .price-table td {
        display: block;
        width: 100%;
    }
    
    /* Каждая позиция — отдельная карточка */
    .price-table tr {
        margin-bottom: 20px;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        background: white;
        overflow: hidden;
    }
    
    /* Ячейки внутри карточки */
    .price-table td {
        padding: 10px 15px;
        border: none;
        border-bottom: 1px solid #f0f0f0;
        position: relative;
        padding-left: 35%;
    }
    
    /* Последняя ячейка без нижней границы */
    .price-table td:last-child {
        border-bottom: none;
    }
    
    /* Добавляем псевдо-заголовки для каждой ячейки */
    .price-table td:nth-child(1):before {
        content: "Услуга ";
        font-weight: bold;
        position: absolute;
        left: 15px;
        width: 30%;
    }
    
    .price-table td:nth-child(2):before {
        content: "Описание: ";
        font-weight: bold;
        position: absolute;
        left: 15px;
        width: 30%;
    }
    
    .price-table td:nth-child(3):before {
        content: "Цена: ";
        font-weight: bold;
        position: absolute;
        left: 15px;
        width: 30%;
    }
    
    .price-table td:nth-child(4):before {
        content: "Примеры: ";
        font-weight: bold;
        position: absolute;
        left: 15px;
        width: 30%;
    }
    
    .price-table td:nth-child(5):before {
        content: "Действие: ";
        font-weight: bold;
        position: absolute;
        left: 15px;
        width: 30%;
    }
    
    /* Текст в ячейках выравниваем */
    .price-table td {
        text-align: right;
    }
    
    .price-table td .carousel {
        margin: 5px auto;
    }
    
    /* Карусель на мобильных */
    .carousel {
        width: 100%;
        height: 120px;
    }
    
    /* Кнопка заказа на мобильных */
    .price-table td .btn {
        display: inline-block;
        width: 100%;
        text-align: center;
    }
}
</style>

<div class="price-container">
    <div class="price-card">
        <h1>💰 Мой прайс-лист</h1>
        <p>Управляйте своими ценами и услугами</p>
        
        <?php if ($error): ?>
            <div class="alert error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        
        <?php if (empty($price_items)): ?>
            <div class="empty-message">
                <p>😔 У вас пока нет позиций в прайс-листе</p>
                <p>Нажмите кнопку ниже, чтобы создать свой первый прайс-лист</p>
            </div>
        <?php else: ?>
            <table class="price-table">
                <thead>
                    <tr>
                        
                        <th>Название</th>
                        <th>Описание</th>
                        <th>Цена</th>
                        <th>Примеры</th>
                        <th>Статус</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($price_items as $item): 
                        $images = array();
                        if (!empty($item['images'])) {
                            $images = explode(',', $item['images']);
                        }
                    ?>
                        <tr class="<?= $item['is_active'] ? '' : 'inactive' ?>">
                            
                            <td><strong><?= htmlspecialchars($item['title']) ?></strong></td>
                            <td><?= nl2br(htmlspecialchars($item['description'])) ?></td>
                            <td class="price-value"><?= number_format($item['price'], 0, '', ' ') ?> <?= $item['currency'] == 'USD' ? '$' : '₽' ?></td>
                            
                            <!-- КАРУСЕЛЬ С ПРИМЕРАМИ -->
                            <td>
                                <?php if (!empty($images)): ?>
                                    <div class="carousel" data-carousel-id="<?= $item['id'] ?>">
                                        <div class="carousel-inner">
                                            <?php foreach ($images as $index => $img): ?>
                                                <div class="carousel-item <?= $index == 0 ? 'active' : '' ?>" data-index="<?= $index ?>">
                                                    <img src="/assets/uploads/price_images/<?= htmlspecialchars($img) ?>" alt="Пример" onerror="this.src='/assets/img/no-image.jpg'">
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <?php if (count($images) > 1): ?>
                                            <button class="carousel-prev" data-id="<?= $item['id'] ?>">❮</button>
                                            <button class="carousel-next" data-id="<?= $item['id'] ?>">❯</button>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="no-image">—</span>
                                <?php endif; ?>
                            </td>
                            
                            <td style="text-align: center;">
                                <?php if ($item['is_active']): ?>
                                    <span class="badge-active">Активен</span>
                                <?php else: ?>
                                    <span class="badge-inactive">Скрыт</span>
                                <?php endif; ?>
                             </td>
                            <td class="action-cell">
                                <button onclick="toggleEditForm(<?= $item['id'] ?>)" class="btn-edit-icon" title="Редактировать">✏️</button>
                                <a href="?delete=<?= $item['id'] ?>" class="btn-delete-icon" onclick="return confirm('Удалить позицию?')" title="Удалить">🗑️</a>
                                
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
                                                <input type="number" name="price" value="<?= $item['price'] ?>" step="any" required>
                                            </div>
                                            <div class="form-group">
                                                <label>Валюта</label>
                                                <select name="currency">
                                                    <option value="RUB" <?= $item['currency'] == 'RUB' ? 'selected' : '' ?>>₽ Рубль</option>
                                                    <option value="USD" <?= $item['currency'] == 'USD' ? 'selected' : '' ?>>$ Доллар</option>
                                                </select>
                                            </div>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label>Порядок сортировки</label>
                                            <input type="number" name="sort_order" value="<?= $item['sort_order'] ?>">
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
                                            <label>Изображения для примера (до 3)</label>
                                            <div class="images-control">
                                                <div class="image-buttons">
                                                    <button type="button" class="btn-small btn-upload" onclick="uploadImage(<?= $item['id'] ?>)">📤 Загрузить</button>
                                                    <button type="button" class="btn-small btn-select" onclick="openPortfolioModal(<?= $item['id'] ?>)">🖼️ Выбрать из портфолио</button>
                                                </div>
                                                <input type="hidden" name="images" id="images-input-<?= $item['id'] ?>" value="<?= htmlspecialchars($item['images']) ?>">
                                                <div class="images-preview" id="images-preview-<?= $item['id'] ?>">
                                                    <?php 
                                                    $current_images = array();
                                                    if (!empty($item['images'])) {
                                                        $current_images = explode(',', $item['images']);
                                                    }
                                                    foreach ($current_images as $img): 
                                                    ?>
                                                        <div class="preview-img" data-img="<?= htmlspecialchars($img) ?>">
                                                            <img src="/assets/uploads/price_images/<?= htmlspecialchars($img) ?>">
                                                            <span class="remove" onclick="removeImageFromList(<?= $item['id'] ?>, '<?= htmlspecialchars($img) ?>')">×</span>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                            <small>Можно загрузить новое изображение или выбрать из已有的 работ</small>
                                        </div>
                                        
                                        <div class="checkbox-group">
                                            <label>
                                                <input type="checkbox" name="is_active" <?= $item['is_active'] ? 'checked' : '' ?>>
                                                Активен
                                            </label>
                                        </div>
                                        
                                        <div class="form-actions">
                                            <button type="submit" name="edit_item" class="btn-save">💾 Сохранить</button>
                                            <button type="button" class="btn-cancel" onclick="toggleEditForm(<?= $item['id'] ?>)">Отмена</button>
                                        </div>
                                    </form>
                                </div>
                             </td>
                         </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        
        <?php if (!$show_form): ?>
            <div class="create-btn">
                <?php if (empty($price_items)): ?>
                    <a href="?create=1" class="btn btn-success">➕ Создать прайс-лист</a>
                <?php else: ?>
                    <a href="?create=1" class="btn btn-primary">➕ Добавить новую позицию</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
       <?php if ($show_form): ?>
    <div class="form-box">
        <h3>📝 <?= empty($price_items) ? 'Создание прайс-листа' : 'Добавление новой позиции' ?></h3>
        <form method="POST" action="?create=1" id="add-item-form">
            <div class="form-group">
                <label>Название *</label>
                <input type="text" name="title" required>
            </div>
            <div class="form-group">
                <label>Описание</label>
                <textarea name="description" rows="3"></textarea>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Цена (₽/$) *</label>
                    <input type="number" name="price" step="any" min="0" required>
                </div>
                <div class="form-group">
                    <label>Валюта</label>
                    <select name="currency">
                        <option value="RUB">₽ Рубль</option>
                        <option value="USD">$ Доллар</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Порядок сортировки</label>
                <input type="number" name="sort_order" value="0">
            </div>
            
            
            <!-- ИЗОБРАЖЕНИЯ ДЛЯ ПРИМЕРА -->
            <div class="form-group">
                <label>Изображения для примера (до 3)</label>
                <div class="images-control">
                    <div class="image-buttons">
                        <button type="button" class="btn-small btn-upload" onclick="uploadImageForAdd()">📤 Загрузить</button>
                        <button type="button" class="btn-small btn-select" onclick="openPortfolioModalForAdd()">🖼️ Выбрать из портфолио</button>
                    </div>
                    <input type="hidden" name="images" id="add-images-input" value="">
                    <div class="images-preview" id="add-images-preview"></div>
                </div>
                <small>Можно загрузить новое изображение или выбрать из已有的 работ</small>
            </div>
            
            <div style="display: flex; gap: 10px;">
                <button type="submit" name="add_item" class="btn btn-primary">💾 Сохранить</button>
                <a href="my-price-list.php" class="btn btn-secondary">❌ Отмена</a>
            </div>
        </form>
    </div>
    <div style="text-align: center; margin-top: 20px;">
        <a href="my-price-list.php" class="btn btn-secondary">← Вернуться к списку</a>
    </div>
<?php endif; ?>
    </div>
</div>

<!-- Модальное окно для выбора из портфолио -->
<div id="portfolio-modal" class="modal">
    <div class="modal-content">
        <h3>🖼️ Выберите работу из портфолио</h3>
        <div class="modal-grid" id="modal-grid">
            <?php foreach ($artworks_list as $artwork): ?>
                <div class="modal-work" data-img="<?= htmlspecialchars($artwork['image_url']) ?>" data-id="<?= $artwork['id'] ?>" data-title="<?= htmlspecialchars($artwork['title']) ?>">
                    <img src="/assets/uploads/artworks/<?= htmlspecialchars($artwork['image_url']) ?>" alt="<?= htmlspecialchars($artwork['title']) ?>">
                    <span><?= htmlspecialchars($artwork['title']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
        <div style="margin-top: 15px; text-align: right;">
            <button class="btn btn-secondary" onclick="closeModal()">Закрыть</button>
        </div>
    </div>
</div>

<script>
let currentItemId = null;

function toggleEditForm(id) {
    var form = document.getElementById('edit-form-' + id);
    if (form.classList.contains('active')) {
        form.classList.remove('active');
    } else {
        form.classList.add('active');
    }
}

// КАРУСЕЛЬ
function initCarousels() {
    // Следующий слайд
    var nextBtns = document.querySelectorAll('.carousel-next');
    for (var i = 0; i < nextBtns.length; i++) {
        nextBtns[i].addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var carousel = this.closest('.carousel');
            var items = carousel.querySelectorAll('.carousel-item');
            var activeItem = carousel.querySelector('.carousel-item.active');
            var currentIndex = parseInt(activeItem.getAttribute('data-index'));
            var nextIndex = (currentIndex + 1) % items.length;
            for (var j = 0; j < items.length; j++) {
                items[j].classList.remove('active');
            }
            items[nextIndex].classList.add('active');
        });
    }
    
    // Предыдущий слайд
    var prevBtns = document.querySelectorAll('.carousel-prev');
    for (var i = 0; i < prevBtns.length; i++) {
        prevBtns[i].addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var carousel = this.closest('.carousel');
            var items = carousel.querySelectorAll('.carousel-item');
            var activeItem = carousel.querySelector('.carousel-item.active');
            var currentIndex = parseInt(activeItem.getAttribute('data-index'));
            var prevIndex = (currentIndex - 1 + items.length) % items.length;
            for (var j = 0; j < items.length; j++) {
                items[j].classList.remove('active');
            }
            items[prevIndex].classList.add('active');
        });
    }
}

// Загрузка изображения
function uploadImage(itemId) {
    var input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/*';
    input.onchange = function(e) {
        var file = e.target.files[0];
        if (!file) return;
        
        var formData = new FormData();
        formData.append('image', file);
        
        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'ajax/upload_price_image.php', true);
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4 && xhr.status === 200) {
                var data = JSON.parse(xhr.responseText);
                if (data.success) {
                    addImageToList(itemId, data.image_url);
                } else {
                    alert('Ошибка загрузки');
                }
            }
        };
        xhr.send(formData);
    };
    input.click();
}

// Открыть модальное окно с портфолио
function openPortfolioModal(itemId) {
    currentItemId = itemId;
    document.getElementById('portfolio-modal').classList.add('active');
}

// Закрыть модальное окно
function closeModal() {
    document.getElementById('portfolio-modal').classList.remove('active');
    currentItemId = null;
}

// Добавить изображение в список
function addImageToList(itemId, imageName) {
    var input = document.getElementById('images-input-' + itemId);
    var currentValue = input.value;
    var imagesArray = currentValue ? currentValue.split(',') : [];
    
    if (imagesArray.length >= 3) {
        alert('Можно добавить не более 3 изображений');
        return;
    }
    
    imagesArray.push(imageName);
    input.value = imagesArray.join(',');
    updatePreview(itemId, imagesArray);
}

// Обновить превью
function updatePreview(itemId, imagesArray) {
    var previewContainer = document.getElementById('images-preview-' + itemId);
    if (!previewContainer) return;
    
    previewContainer.innerHTML = '';
    for (var i = 0; i < imagesArray.length; i++) {
        var img = imagesArray[i];
        var div = document.createElement('div');
        div.className = 'preview-img';
        div.dataset.img = img;
        div.innerHTML = '<img src="/assets/uploads/price_images/' + img + '">' +
                       '<span class="remove" onclick="removeImageFromList(' + itemId + ', \'' + img + '\')">×</span>';
        previewContainer.appendChild(div);
    }
}

// Удалить изображение из списка
function removeImageFromList(itemId, imageName) {
    var input = document.getElementById('images-input-' + itemId);
    var currentValue = input.value;
    var imagesArray = currentValue ? currentValue.split(',') : [];
    var newArray = [];
    
    for (var i = 0; i < imagesArray.length; i++) {
        if (imagesArray[i] !== imageName) {
            newArray.push(imagesArray[i]);
        }
    }
    
    input.value = newArray.join(',');
    updatePreview(itemId, newArray);
}

// Выбор из портфолио
document.querySelectorAll('.modal-work').forEach(function(el) {
    el.addEventListener('click', function() {
        var imgUrl = this.dataset.img;
        if (currentItemId) {
            addImageFromPortfolio(currentItemId, imgUrl);
        }
        closeModal();
    });
});

function addImageFromPortfolio(itemId, imgUrl) {
    var input = document.getElementById('images-input-' + itemId);
    var currentValue = input.value;
    var imagesArray = currentValue ? currentValue.split(',') : [];
    
    if (imagesArray.length >= 3) {
        alert('Можно добавить не более 3 изображений');
        return;
    }
    
    if (imagesArray.indexOf(imgUrl) !== -1) {
        alert('Это изображение уже добавлено');
        return;
    }
    
    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'ajax/copy_price_image.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4 && xhr.status === 200) {
            var data = JSON.parse(xhr.responseText);
            if (data.success) {
                addImageToList(itemId, data.new_name);
            } else {
                alert('Ошибка копирования изображения');
            }
        }
    };
    xhr.send('image=' + encodeURIComponent(imgUrl));
}

// Запускаем карусель после загрузки страницы
document.addEventListener('DOMContentLoaded', initCarousels);
</script>

<?php include 'includes/footer.php'; ?>