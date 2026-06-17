<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

$artist_id = isset($_GET['artist_id']) ? (int)$_GET['artist_id'] : 0;

if ($artist_id == 0) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT id, username, full_name, avatar, role FROM users WHERE id = ?");
$stmt->execute(array($artist_id));
$artist = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$artist || $artist['role'] != 'artist') {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT pl.*, a.title as artwork_title
    FROM price_list pl
    LEFT JOIN artworks a ON pl.artwork_id = a.id
    WHERE pl.artist_id = ? AND pl.is_active = TRUE
    ORDER BY pl.sort_order ASC, pl.created_at ASC
");
$stmt->execute(array($artist_id));
$price_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php include 'includes/header.php'; ?>

<style>
.price-page-container { max-width: 1200px; margin: 0 auto; padding: 20px; }
.artist-header { display: flex; align-items: center; gap: 20px; background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 30px; }
.artist-avatar { width: 80px; height: 80px; border-radius: 50%; object-fit: cover; }
.artist-avatar-placeholder { width: 80px; height: 80px; border-radius: 50%; background: linear-gradient(135deg, #667eea, #764ba2); color: white; display: flex; align-items: center; justify-content: center; font-size: 32px; font-weight: bold; }
.artist-info h1 { margin: 0 0 5px; font-size: 1.5rem; }
.price-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
.price-table th, .price-table td { padding: 15px; border: 1px solid #e0e0e0; text-align: left; vertical-align: top; }
.price-table th { background: #f5f5f5; }
.price-table .price { font-weight: bold; color: #2c3e50; font-size: 1.2rem; }
.empty-message { text-align: center; padding: 50px; color: #666; background: #f8f9fa; border-radius: 8px; }
.back-link { display: inline-block; margin-bottom: 20px; color: #3498db; text-decoration: none; }
.btn { display: inline-block; padding: 8px 16px; background: #28a745; color: white; text-decoration: none; border-radius: 4px; border: none; cursor: pointer; }
.order-form { display: none; background: #f8f9fa; padding: 15px; margin-top: 10px; border-radius: 8px; }
.order-form.active { display: block; }
.order-form textarea { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 10px; }

/* ===== КАРУСЕЛЬ ДЛЯ ИЗОБРАЖЕНИЙ ===== */
.carousel {
    position: relative;
    width: 180px;
    height: 120px;
    overflow: hidden;
    border-radius: 8px;
    background: #f0f0f0;
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
    padding: 5px 10px;
    border-radius: 50%;
    font-size: 14px;
    z-index: 10;
    transition: background 0.2s;
}
.carousel-prev:hover, .carousel-next:hover {
    background: rgba(0,0,0,0.8);
}
.carousel-prev { left: 5px; }
.carousel-next { right: 5px; }
.no-image {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 180px;
    height: 120px;
    background: #f8f9fa;
    color: #999;
    border-radius: 8px;
    font-size: 0.8rem;
}
.images-cell {
    width: 200px;
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
        content: "Услуга: ";
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

<div class="price-page-container">
    <a href="artist.php?id=<?= $artist_id ?>" class="back-link">← Вернуться к художнику</a>
    
    <div class="artist-header">
        <?php if (!empty($artist['avatar']) && file_exists('assets/uploads/avatars/'.$artist['avatar'])): ?>
            <img src="assets/uploads/avatars/<?= htmlspecialchars($artist['avatar']) ?>" class="artist-avatar">
        <?php else: ?>
            <div class="artist-avatar-placeholder"><?= mb_substr(($artist['full_name'] ?: $artist['username']), 0, 1) ?></div>
        <?php endif; ?>
        <div class="artist-info">
            <h1><?= htmlspecialchars($artist['full_name'] ?: $artist['username']) ?></h1>
            <p>Прайс-лист художника</p>
        </div>
    </div>
    
    <?php if (empty($price_items)): ?>
        <div class="empty-message">😔 У художника пока нет позиций в прайс-листе</div>
    <?php else: ?>
        <table class="price-table">
            <thead>
                <tr>
                    <th>Услуга/Работа</th>
                    <th>Описание</th>
                    <th>Цена</th>
                    <th class="images-cell">Примеры</th>
                    <th>Действие</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($price_items as $item): 
                    $images = array();
                    if (!empty($item['images'])) {
                        $images = explode(',', $item['images']);
                    }
                ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($item['title']) ?></strong></td>
                        <td><?= nl2br(htmlspecialchars($item['description'])) ?></td>
                        <td class="price"><?= number_format($item['price'], 0, '', ' ') ?> <?= $item['currency'] == 'USD' ? '$' : '₽' ?></td>
                        
                        <!-- КАРУСЕЛЬ С ИЗОБРАЖЕНИЯМИ -->
                        <td class="images-cell">
                            <?php if (!empty($images)): ?>
                                <div class="carousel" data-carousel-id="<?= $item['id'] ?>">
                                    <div class="carousel-inner">
                                        <?php foreach ($images as $index => $img): ?>
                                            <div class="carousel-item <?= $index == 0 ? 'active' : '' ?>" data-index="<?= $index ?>">
                                                <img src="/assets/uploads/price_images/<?= htmlspecialchars($img) ?>" alt="Пример работы">
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php if (count($images) > 1): ?>
                                        <button class="carousel-prev" data-id="<?= $item['id'] ?>">❮</button>
                                        <button class="carousel-next" data-id="<?= $item['id'] ?>">❯</button>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div class="no-image">📷 Нет примера</div>
                            <?php endif; ?>
                        </td>
                        
                        <td>
                            <?php if (isLoggedIn() && $_SESSION['user_id'] != $artist_id): ?>
                                <button class="btn order-btn" data-price-list-id="<?= $item['id'] ?>" data-title="<?= htmlspecialchars($item['title']) ?>" data-price="<?= $item['price'] ?>" data-currency="<?= $item['currency'] ?>">🛒 Заказать</button>
                                <div id="order-form-<?= $item['id'] ?>" class="order-form">
                                    <form class="order-submit" data-price-list-id="<?= $item['id'] ?>" data-artist-id="<?= $artist_id ?>" data-title="<?= htmlspecialchars($item['title']) ?>" data-price="<?= $item['price'] ?>" data-currency="<?= $item['currency'] ?>">
                                        <textarea name="message" rows="3" placeholder="Опишите ваши пожелания к заказу (размер, цвет, сроки и т.д.)"></textarea>
                                        <button type="submit" class="btn" style="background:#28a745;">📩 Отправить</button>
                                        <button type="button" class="cancel-btn" style="background:#6c757d; color:white; padding:8px 16px; border:none; border-radius:4px;">Отмена</button>
                                    </form>
                                </div>
                            <?php elseif (!isLoggedIn()): ?>
                                <a href="login.php" class="btn" style="background:#3498db;">Войти</a>
                            <?php elseif ($_SESSION['user_id'] == $artist_id): ?>
                                <span style="color:#999;">Ваш прайс-лист</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<script>
// Кнопки заказа
document.querySelectorAll('.order-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.getElementById('order-form-' + this.dataset.priceListId).classList.add('active');
    });
});

document.querySelectorAll('.cancel-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        this.closest('.order-form').classList.remove('active');
    });
});

document.querySelectorAll('.order-submit').forEach(form => {
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        var data = this.dataset;
        var message = this.querySelector('textarea').value;
        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'ajax/create_order.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4 && xhr.status === 200) {
                try {
                    var res = JSON.parse(xhr.responseText);
                    alert(res.message);
                    if (res.success) {
                        form.closest('.order-form').classList.remove('active');
                        form.querySelector('textarea').value = '';
                    }
                } catch(e) { alert('Ошибка'); }
            }
        };
        xhr.send('artist_id=' + data.artistId + '&price_list_id=' + data.priceListId + '&title=' + encodeURIComponent(data.title) + '&price=' + data.price + '&currency=' + data.currency + '&client_message=' + encodeURIComponent(message));
    });
});

// ===== КАРУСЕЛЬ =====
function initCarousels() {
    // Карусель - следующий слайд
    document.querySelectorAll('.carousel-next').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var carousel = this.closest('.carousel');
            var inner = carousel.querySelector('.carousel-inner');
            var items = carousel.querySelectorAll('.carousel-item');
            var activeItem = carousel.querySelector('.carousel-item.active');
            var currentIndex = parseInt(activeItem.dataset.index);
            var nextIndex = (currentIndex + 1) % items.length;
            
            items.forEach(item => item.classList.remove('active'));
            items[nextIndex].classList.add('active');
        });
    });
    
    // Карусель - предыдущий слайд
    document.querySelectorAll('.carousel-prev').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var carousel = this.closest('.carousel');
            var items = carousel.querySelectorAll('.carousel-item');
            var activeItem = carousel.querySelector('.carousel-item.active');
            var currentIndex = parseInt(activeItem.dataset.index);
            var prevIndex = (currentIndex - 1 + items.length) % items.length;
            
            items.forEach(item => item.classList.remove('active'));
            items[prevIndex].classList.add('active');
        });
    });
}

// Запускаем карусель после загрузки страницы
document.addEventListener('DOMContentLoaded', initCarousels);
</script>

<?php include 'includes/footer.php'; ?>