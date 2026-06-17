<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

$artwork_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Получаем данные работы
$stmt = $pdo->prepare("
    SELECT a.*, u.username, u.full_name, u.avatar, u.id as artist_id
    FROM artworks a
    JOIN users u ON a.artist_id = u.id
    WHERE a.id = ?
");
$stmt->execute(array($artwork_id));
$artwork = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$artwork) {
    header('Location: index.php');
    exit;
}

// Увеличиваем счетчик просмотров (только уникальные)
$user_id = isLoggedIn() ? $_SESSION['user_id'] : null;
$session_id = session_id();

$stmt_check = $pdo->prepare("
    SELECT id FROM artwork_views 
    WHERE artwork_id = ? AND (user_id = ? OR (session_id = ? AND user_id IS NULL))
    LIMIT 1
");
$stmt_check->execute(array($artwork_id, $user_id, $session_id));
$existing_view = $stmt_check->fetch();

if (!$existing_view) {
    $stmt_view = $pdo->prepare("
        INSERT INTO artwork_views (artwork_id, user_id, session_id) 
        VALUES (?, ?, ?)
    ");
    $stmt_view->execute(array($artwork_id, $user_id, $session_id));
    
    $stmt_update = $pdo->prepare("UPDATE artworks SET views = views + 1 WHERE id = ?");
    $stmt_update->execute(array($artwork_id));
    $artwork['views'] = $artwork['views'] + 1;
}

// Получаем количество лайков
$stmt_likes = $pdo->prepare("SELECT COUNT(*) FROM likes WHERE artwork_id = ?");
$stmt_likes->execute(array($artwork_id));
$likes_count = $stmt_likes->fetchColumn();

// Проверяем, поставил ли пользователь лайк
$user_liked = false;
if (isLoggedIn()) {
    $stmt_user_like = $pdo->prepare("SELECT id FROM likes WHERE user_id = ? AND artwork_id = ?");
    $stmt_user_like->execute(array($_SESSION['user_id'], $artwork_id));
    $user_liked = $stmt_user_like->fetch() ? true : false;
}

// Получаем теги
$tags_array = array();
if (!empty($artwork['tags'])) {
    $tags_array = array_map('trim', explode(',', $artwork['tags']));
    $tags_array = array_filter($tags_array);
}

// Получаем комментарии
$stmt_comments = $pdo->prepare("
    SELECT c.*, u.username, u.full_name, u.avatar
    FROM comments c
    JOIN users u ON c.user_id = u.id
    WHERE c.artwork_id = ?
    ORDER BY c.created_at DESC
");
$stmt_comments->execute(array($artwork_id));
$comments = $stmt_comments->fetchAll(PDO::FETCH_ASSOC);
?>

<?php include 'includes/header.php'; ?>

<style>
.artwork-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}
.artwork-main {
    display: flex;
    gap: 40px;
    margin-bottom: 40px;
}
.artwork-image {
    flex: 2;
}
.artwork-image img {
    width: 100%;
    border-radius: 8px;
}
.artwork-info {
    flex: 1;
}
.artwork-info h1 {
    margin: 0 0 10px 0;
}
.artist-link {
    display: flex;
    align-items: center;
    gap: 10px;
    text-decoration: none;
    color: #333;
    margin-bottom: 20px;
}
.artist-avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    object-fit: cover;
}
.stats {
    display: flex;
    gap: 20px;
    margin: 20px 0;
    padding: 15px 0;
    border-top: 1px solid #eee;
    border-bottom: 1px solid #eee;
}
.stats span {
    display: flex;
    align-items: center;
    gap: 5px;
    color: #666;
}
.btn-like {
    padding: 10px 20px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 1rem;
    background: #f0f0f0;
    color: #333;
    transition: all 0.2s;
}
.btn-like.liked {
    background: #e74c3c;
    color: white;
}
.btn-buy {
    padding: 10px 20px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 1rem;
    background: #28a745;
    color: white;
    margin-left: 10px;
}
.btn-buy:hover {
    background: #218838;
}
.sold-badge {
    display: inline-block;
    background: #6c757d;
    color: white;
    padding: 10px 20px;
    border-radius: 4px;
    font-size: 1rem;
    font-weight: bold;
    margin-left: 10px;
}
.tags {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin: 20px 0;
}
.tag {
    background: #f0f0f0;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    text-decoration: none;
    color: #666;
}
.price {
    font-size: 1.5rem;
    font-weight: bold;
    color: #2c3e50;
    margin: 20px 0;
}
.price.sold {
    text-decoration: line-through;
    color: #999;
}
.description {
    margin-top: 20px;
}
.description h3 {
    margin-bottom: 10px;
}

/* Комментарии */
.comments-section {
    margin-top: 40px;
    padding-top: 20px;
    border-top: 2px solid #eee;
}
.comments-section h3 {
    margin-bottom: 20px;
    font-size: 1.3rem;
}
.comment-form {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 30px;
}
.comment-form textarea {
    width: 100%;
    padding: 12px;
    border: 1px solid #ddd;
    border-radius: 8px;
    resize: vertical;
    font-size: 1rem;
    margin-bottom: 10px;
}
.comment-form button {
    padding: 8px 20px;
    background: #3498db;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
}
.comment-form button:hover {
    background: #2980b9;
}
.comments-list {
    max-height: 500px;
    overflow-y: auto;
}
.comment-item {
    display: flex;
    gap: 15px;
    padding: 15px;
    border-bottom: 1px solid #eee;
}
.comment-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
}
.comment-avatar-placeholder {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    font-weight: bold;
}
.comment-content {
    flex: 1;
}
.comment-header {
    display: flex;
    gap: 10px;
    align-items: baseline;
    margin-bottom: 5px;
}
.comment-author {
    font-weight: bold;
    color: #333;
    text-decoration: none;
}
.comment-author:hover {
    color: #3498db;
}
.comment-date {
    font-size: 0.7rem;
    color: #999;
}
.comment-text {
    color: #555;
    line-height: 1.4;
}
.empty-comments {
    text-align: center;
    padding: 40px;
    color: #999;
}
</style>

<div class="artwork-container">
    <div class="artwork-main">
        <div class="artwork-image">
            <img src="assets/uploads/artworks/<?= htmlspecialchars($artwork['image_url']) ?>" 
                 alt="<?= htmlspecialchars($artwork['title']) ?>">
        </div>
        <div class="artwork-info">
            <h1><?= htmlspecialchars($artwork['title']) ?></h1>
            
            <a href="artist.php?id=<?= $artwork['artist_id'] ?>" class="artist-link">
                <?php if (!empty($artwork['avatar']) && file_exists('assets/uploads/avatars/'.$artwork['avatar'])): ?>
                    <img src="assets/uploads/avatars/<?= htmlspecialchars($artwork['avatar']) ?>" class="artist-avatar">
                <?php else: ?>
                    <div class="artist-avatar" style="background: linear-gradient(135deg, #667eea, #764ba2); display: flex; align-items: center; justify-content: center; color: white; font-size: 20px; width: 50px; height: 50px; border-radius: 50%;">
                        <?= mb_substr(($artwork['full_name'] ?: $artwork['username']), 0, 1) ?>
                    </div>
                <?php endif; ?>
                <span class="artist-name"><?= htmlspecialchars($artwork['full_name'] ?: $artwork['username']) ?></span>
            </a>
            
            <div class="stats">
                <span>👁️ <?= $artwork['views'] ?> просмотров</span>
                <span>❤️ <span id="likes-count"><?= $likes_count ?></span> лайков</span>
                <span>💬 <span id="comments-count"><?= count($comments) ?></span> комментариев</span>
                <span>📅 <?= date('d.m.Y', strtotime($artwork['created_at'])) ?></span>
            </div>
            
            <div class="action-buttons">
                <?php if (isLoggedIn() && $_SESSION['user_id'] != $artwork['artist_id']): ?>
                    <button id="like-btn" class="btn-like <?= $user_liked ? 'liked' : '' ?>" data-artwork-id="<?= $artwork_id ?>">
                        <?= $user_liked ? '❤️ Лайк' : '🤍 Лайк' ?>
                    </button>
                <?php endif; ?>
                
                <?php if ($artwork['price'] && $artwork['is_for_sale']): ?>
                    <?php if ($artwork['is_sold']): ?>
                        <span class="sold-badge">❌ ПРОДАНО</span>
                    <?php elseif (isLoggedIn() && $_SESSION['user_id'] != $artwork['artist_id']): ?>
                        <button id="buy-btn" class="btn-buy" data-artwork-id="<?= $artwork_id ?>" data-price="<?= $artwork['price'] ?>" data-currency="<?= $artwork['currency'] ?>" data-title="<?= htmlspecialchars($artwork['title']) ?>">
                            🛒 Купить за <?= format_price($artwork['price'], $artwork['currency']) ?>
                        </button>
                    <?php elseif (!isLoggedIn()): ?>
                        <a href="login.php" class="btn-buy" style="text-decoration: none; display: inline-block;">Войдите, чтобы купить</a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            
            <?php if ($artwork['price'] && $artwork['is_for_sale']): ?>
                <div class="price <?= $artwork['is_sold'] ? 'sold' : '' ?>">
                    <?= format_price($artwork['price'], $artwork['currency']) ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($tags_array)): ?>
                <div class="tags">
                    <?php foreach ($tags_array as $tag): ?>
                        <a href="index.php?search=<?= urlencode($tag) ?>" class="tag">
                            #<?= htmlspecialchars($tag) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <div class="description">
                <h3>Описание</h3>
                <p><?= nl2br(htmlspecialchars($artwork['description'])) ?></p>
            </div>
            
            <?php if ($artwork['technique']): ?>
                <div class="technique">
                    <strong>Техника:</strong> <?= htmlspecialchars($artwork['technique']) ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- КОММЕНТАРИИ -->
    <div class="comments-section">
        <h3>💬 Комментарии (<span id="comments-count-bottom"><?= count($comments) ?></span>)</h3>
        
        <?php if (isLoggedIn()): ?>
            <div class="comment-form">
                <textarea id="comment-text" rows="3" placeholder="Напишите комментарий..."></textarea>
                <button id="submit-comment" data-artwork-id="<?= $artwork_id ?>">Отправить</button>
            </div>
        <?php else: ?>
            <div class="comment-form" style="text-align: center;">
                <p><a href="login.php">Войдите</a>, чтобы оставить комментарий</p>
            </div>
        <?php endif; ?>
        
        <div id="comments-list" class="comments-list">
            <?php foreach ($comments as $comment): ?>
                <div class="comment-item" data-comment-id="<?= $comment['id'] ?>">
                    <a href="artist.php?id=<?= $comment['user_id'] ?>">
                        <?php if (!empty($comment['avatar']) && file_exists('assets/uploads/avatars/'.$comment['avatar'])): ?>
                            <img src="assets/uploads/avatars/<?= htmlspecialchars($comment['avatar']) ?>" class="comment-avatar">
                        <?php else: ?>
                            <div class="comment-avatar-placeholder">
                                <?= mb_substr(($comment['full_name'] ?: $comment['username']), 0, 1) ?>
                            </div>
                        <?php endif; ?>
                    </a>
                    <div class="comment-content">
                        <div class="comment-header">
                            <a href="artist.php?id=<?= $comment['user_id'] ?>" class="comment-author">
                                <?= htmlspecialchars($comment['full_name'] ?: $comment['username']) ?>
                            </a>
                            <span class="comment-date"><?= date('d.m.Y H:i', strtotime($comment['created_at'])) ?></span>
                        </div>
                        <div class="comment-text"><?= nl2br(htmlspecialchars($comment['comment'])) ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <?php if (empty($comments)): ?>
                <div class="empty-comments">
                    <p>😔 Пока нет комментариев. Будьте первым!</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Лайк
document.getElementById('like-btn')?.addEventListener('click', function() {
    var btn = this;
    var artworkId = btn.dataset.artworkId;
    
    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'ajax/like.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            if (xhr.status === 200) {
                try {
                    var data = JSON.parse(xhr.responseText);
                    if (data.success) {
                        if (data.action === 'liked') {
                            btn.className = 'btn-like liked';
                            btn.innerHTML = '❤️ Лайк';
                        } else {
                            btn.className = 'btn-like';
                            btn.innerHTML = '🤍 Лайк';
                        }
                        document.getElementById('likes-count').innerHTML = data.likes_count;
                    } else {
                        alert('Ошибка: ' + data.message);
                    }
                } catch(e) {
                    alert('Ошибка ответа сервера');
                }
            } else {
                alert('Ошибка при отправке запроса');
            }
        }
    };
    
    xhr.send('artwork_id=' + artworkId);
});

// Покупка работы
document.getElementById('buy-btn')?.addEventListener('click', function() {
    var btn = this;
    var artworkId = btn.dataset.artworkId;
    var title = btn.dataset.title;
    var price = btn.dataset.price;
    var currency = btn.dataset.currency;
    var message = prompt('Оставьте сообщение для художника (необязательно):');
    
    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'ajax/buy_artwork.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            if (xhr.status === 200) {
                try {
                    var data = JSON.parse(xhr.responseText);
                    if (data.success) {
                        alert(data.message);
                        location.reload();
                    } else {
                        alert('Ошибка: ' + data.message);
                    }
                } catch(e) {
                    alert('Ошибка ответа сервера');
                }
            } else {
                alert('Ошибка при отправке запроса');
            }
        }
    };
    
    xhr.send('artwork_id=' + artworkId + '&title=' + encodeURIComponent(title) + '&price=' + price + '&currency=' + currency + '&message=' + encodeURIComponent(message || ''));
});

// Комментарий
document.getElementById('submit-comment')?.addEventListener('click', function() {
    var btn = this;
    var artworkId = btn.dataset.artworkId;
    var commentText = document.getElementById('comment-text').value.trim();
    
    if (commentText === '') {
        alert('Напишите комментарий');
        return;
    }
    
    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'ajax/add_comment.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            if (xhr.status === 200) {
                try {
                    var data = JSON.parse(xhr.responseText);
                    if (data.success) {
                        document.getElementById('comment-text').value = '';
                        location.reload();
                    } else {
                        alert('Ошибка: ' + data.message);
                    }
                } catch(e) {
                    alert('Ошибка ответа сервера');
                }
            } else {
                alert('Ошибка при отправке запроса');
            }
        }
    };
    
    xhr.send('artwork_id=' + artworkId + '&comment=' + encodeURIComponent(commentText));
});
</script>

<?php include 'includes/footer.php'; ?>