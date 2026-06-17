<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Берем ID из URL - ЭТО ГЛАВНЫЙ ID, ЕГО НЕЛЬЗЯ МЕНЯТЬ
$viewing_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Если ID не указан - перенаправляем на главную
if ($viewing_id == 0) {
    header('Location: index.php');
    exit;
}

// Получаем информацию о пользователе
$stmt = $pdo->prepare("
    SELECT u.*, COUNT(a.id) as artworks_count
    FROM users u
    LEFT JOIN artworks a ON u.id = a.artist_id
    WHERE u.id = ?
    GROUP BY u.id
");
$stmt->execute(array($viewing_id));
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: index.php');
    exit;
}

$is_artist = ($user['role'] == 'artist');
$is_own_profile = (isLoggedIn() && $_SESSION['user_id'] == $viewing_id);

// Обработка создания папки (только для своего профиля)
$folder_error = '';
$folder_success = '';
$show_create_form = isset($_GET['create_folder']) ? true : false;

if ($is_own_profile && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_folder'])) {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    
    if (empty($name)) {
        $folder_error = 'Введите название папки';
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM folders WHERE user_id = ?");
        $stmt->execute(array($viewing_id));
        $folder_count = $stmt->fetchColumn();
        
        if ($folder_count >= 5) {
            $folder_error = 'Вы можете создать не более 5 папок';
        } else {
            $stmt = $pdo->prepare("INSERT INTO folders (user_id, name, description, sort_order) VALUES (?, ?, ?, ?)");
            if ($stmt->execute(array($viewing_id, $name, $description, 0))) {
                $folder_success = 'Папка создана!';
                $show_create_form = false;
                header('Location: artist.php?id=' . $viewing_id);
                exit;
            } else {
                $folder_error = 'Ошибка при создании папки';
            }
        }
    }
}

// Обработка удаления папки (только для своего профиля)
if ($is_own_profile && isset($_GET['delete_folder'])) {
    $folder_id = intval($_GET['delete_folder']);
    
    $stmt = $pdo->prepare("DELETE FROM artwork_folders WHERE folder_id = ?");
    $stmt->execute(array($folder_id));
    
    $stmt = $pdo->prepare("DELETE FROM folders WHERE id = ? AND user_id = ?");
    $stmt->execute(array($folder_id, $viewing_id));
    
    header('Location: artist.php?id=' . $viewing_id);
    exit;
}

// Получаем количество подписчиков
$stmt_sub = $pdo->prepare("SELECT COUNT(*) FROM subscriptions WHERE following_id = ?");
$stmt_sub->execute(array($viewing_id));
$subscribers_count = $stmt_sub->fetchColumn();

// Получаем количество подписок
$stmt_following = $pdo->prepare("SELECT COUNT(*) FROM subscriptions WHERE follower_id = ?");
$stmt_following->execute(array($viewing_id));
$following_count = $stmt_following->fetchColumn();

// Проверяем подписку текущего пользователя
$is_subscribed = false;
if (isLoggedIn() && $_SESSION['user_id'] != $viewing_id && $is_artist) {
    $stmt_check = $pdo->prepare("SELECT id FROM subscriptions WHERE follower_id = ? AND following_id = ?");
    $stmt_check->execute(array($_SESSION['user_id'], $viewing_id));
    $is_subscribed = $stmt_check->fetch() ? true : false;
}

// Получаем папки художника
$artist_folders = array();
if ($is_artist) {
    $stmt = $pdo->prepare("SELECT * FROM folders WHERE user_id = ? ORDER BY sort_order ASC, name ASC");
    $stmt->execute(array($viewing_id));
    $artist_folders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Для каждой папки получаем работы
    foreach ($artist_folders as $key => $folder) {
        $stmt = $pdo->prepare("
            SELECT DISTINCT a.*, c.name as category_name
            FROM artworks a
            LEFT JOIN categories c ON a.category_id = c.id
            JOIN artwork_folders af ON a.id = af.artwork_id
            WHERE af.folder_id = ? AND a.artist_id = ?
            ORDER BY a.created_at DESC
        ");
        $stmt->execute(array($folder['id'], $viewing_id));
        $artist_folders[$key]['works'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Получаем работы без папки
$works_without_folder = array();
if ($is_artist) {
    $stmt = $pdo->prepare("
        SELECT a.*, c.name as category_name
        FROM artworks a
        LEFT JOIN categories c ON a.category_id = c.id
        WHERE a.artist_id = ? AND NOT EXISTS (
            SELECT 1 FROM artwork_folders af WHERE af.artwork_id = a.id
        )
        ORDER BY a.created_at DESC
    ");
    $stmt->execute(array($viewing_id));
    $works_without_folder = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Выбранная папка для фильтрации
$selected_folder = isset($_GET['folder']) ? (int)$_GET['folder'] : 0;
$filtered_works = array();

if ($selected_folder > 0) {
    foreach ($artist_folders as $folder) {
        if ($folder['id'] == $selected_folder) {
            $filtered_works = $folder['works'];
            break;
        }
    }
} else {
    // Собираем все работы (уникальные, без дублей)
    $all_works = array();
    foreach ($artist_folders as $folder) {
        foreach ($folder['works'] as $work) {
            $all_works[$work['id']] = $work;
        }
    }
    foreach ($works_without_folder as $work) {
        $all_works[$work['id']] = $work;
    }
    $filtered_works = array_values($all_works);
}

// Подписки и подписчики (только для своего профиля)
$user_following = array();
$user_subscribers = array();
if ($is_own_profile) {
    $stmt = $pdo->prepare("
        SELECT u.id, u.username, u.full_name, u.avatar, u.role
        FROM subscriptions s
        JOIN users u ON s.following_id = u.id
        WHERE s.follower_id = ?
        ORDER BY s.created_at DESC
        LIMIT 20
    ");
    $stmt->execute(array($viewing_id));
    $user_following = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->prepare("
        SELECT u.id, u.username, u.full_name, u.avatar, u.role
        FROM subscriptions s
        JOIN users u ON s.follower_id = u.id
        WHERE s.following_id = ?
        ORDER BY s.created_at DESC
        LIMIT 20
    ");
    $stmt->execute(array($viewing_id));
    $user_subscribers = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<?php include 'includes/header.php'; ?>

<style>
.artist-profile {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}
.profile-header {
    display: flex;
    gap: 30px;
    align-items: center;
    background: #f8f9fa;
    padding: 30px;
    border-radius: 8px;
    margin-bottom: 30px;
}
.avatar-large {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid #fff;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}
.avatar-placeholder-large {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 48px;
    font-weight: bold;
    border: 3px solid #fff;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}
.profile-info {
    flex: 1;
}
.profile-info h1 {
    margin: 0 0 5px 0;
    font-size: 2rem;
}
.username {
    color: #666;
    margin-bottom: 15px;
}
.bio {
    line-height: 1.6;
    color: #444;
    margin: 15px 0;
}
.profile-stats {
    display: flex;
    gap: 40px;
    margin: 15px 0;
    padding: 15px 0;
    border-top: 1px solid #eee;
    border-bottom: 1px solid #eee;
}
.stat-item {
    text-align: center;
}
.stat-number {
    font-size: 1.5rem;
    font-weight: bold;
    color: #333;
}
.stat-label {
    font-size: 0.8rem;
    color: #666;
}
.btn {
    display: inline-block;
    padding: 10px 20px;
    background: #3498db;
    color: white;
    text-decoration: none;
    border-radius: 4px;
    margin-right: 10px;
    border: none;
    cursor: pointer;
    font-size: 1rem;
}
.btn:hover {
    background: #2980b9;
}
.btn-subscribe {
    background: #e74c3c;
}
.btn-subscribe:hover {
    background: #c0392b;
}
.btn-subscribe.subscribed {
    background: #2c3e50;
}
.folders-nav {
    margin: 30px 0 20px;
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
    border-bottom: 1px solid #eee;
    padding-bottom: 15px;
}
.folder-tab {
    display: inline-block;
    padding: 8px 20px;
    background: #f0f0f0;
    color: #666;
    text-decoration: none;
    border-radius: 30px;
    transition: all 0.2s;
    font-size: 0.9rem;
}
.folder-tab:hover {
    background: #e0e0e0;
}
.folder-tab.active {
    background: #3498db;
    color: white;
}
.create-folder-btn {
    background: #28a745;
    color: white;
}
.create-folder-btn:hover {
    background: #218838;
}
.delete-folder-btn {
    background: #dc3545;
    color: white;
    font-size: 0.7rem;
    padding: 4px 8px;
    margin-left: 8px;
    border-radius: 20px;
    text-decoration: none;
}
.delete-folder-btn:hover {
    background: #c82333;
}
.create-folder-form {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
}
.create-folder-form input,
.create-folder-form textarea {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    margin-bottom: 10px;
}
.form-actions {
    display: flex;
    gap: 10px;
}
.gallery-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
}
.gallery-item {
    background: white;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: transform 0.3s;
}
.gallery-item:hover {
    transform: translateY(-5px);
}
.gallery-item img {
    width: 100%;
    height: 200px;
    object-fit: cover;
}
.gallery-info {
    padding: 15px;
}
.gallery-info h3 {
    margin: 0 0 5px;
    font-size: 1.1rem;
}
.price {
    font-weight: bold;
    color: #2c3e50;
    margin-top: 8px;
}
.price.sold {
    text-decoration: line-through;
    color: #999;
}
.sold-badge {
    display: inline-block;
    background: #dc3545;
    color: white;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 0.7rem;
    margin-left: 8px;
}
.empty-message {
    text-align: center;
    padding: 60px;
    background: #f8f9fa;
    border-radius: 8px;
    color: #666;
}
.client-message {
    background: #f0f0f0;
    padding: 30px;
    text-align: center;
    border-radius: 8px;
    color: #666;
}
.subscriptions-section {
    margin-top: 30px;
    background: #f8f9fa;
    border-radius: 8px;
    padding: 20px;
}
.subscriptions-section h3 {
    margin: 0 0 15px 0;
    font-size: 1.2rem;
}
.subscriptions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 15px;
}
.subscription-card {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px;
    background: white;
    border-radius: 8px;
    text-decoration: none;
    transition: all 0.2s;
}
.subscription-card:hover {
    background: #e9ecef;
    transform: translateX(5px);
}
.subscription-avatar {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    object-fit: cover;
}
.subscription-avatar-placeholder {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    font-weight: bold;
}
.subscription-info {
    flex: 1;
}
.subscription-name {
    font-weight: bold;
    color: #333;
}
.subscription-username {
    font-size: 0.75rem;
    color: #666;
}
.subscription-role {
    font-size: 0.7rem;
    padding: 2px 6px;
    border-radius: 10px;
    background: #e0e0e0;
    display: inline-block;
}
.empty-list {
    text-align: center;
    padding: 20px;
    color: #666;
}
.alert {
    padding: 12px;
    margin-bottom: 20px;
    border-radius: 6px;
}
.alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}
.alert-error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}
@media (max-width: 768px) {
    .profile-header {
        flex-direction: column;
        text-align: center;
    }
    .profile-stats {
        justify-content: center;
    }
    .gallery-grid {
        grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
    }
}
</style>

<div class="artist-profile">
    <div class="profile-header">
        <?php if (!empty($user['avatar']) && file_exists('assets/uploads/avatars/'.$user['avatar'])): ?>
            <img src="assets/uploads/avatars/<?= htmlspecialchars($user['avatar']) ?>" class="avatar-large">
        <?php else: ?>
            <div class="avatar-placeholder-large">
                <?= mb_substr(($user['full_name'] ?: $user['username']), 0, 1) ?>
            </div>
        <?php endif; ?>
        
        <div class="profile-info">
            <h1><?= htmlspecialchars($user['full_name'] ?: $user['username']) ?></h1>
            <p class="username">@<?= htmlspecialchars($user['username']) ?></p>
            <?php if ($user['bio']): ?>
                <p class="bio"><?= nl2br(htmlspecialchars($user['bio'])) ?></p>
            <?php endif; ?>
            
            <div class="profile-stats">
                <?php if ($is_artist): ?>
                    <div class="stat-item">
                        <div class="stat-number"><?= count($filtered_works) ?></div>
                        <div class="stat-label">работ</div>
                    </div>
                <?php endif; ?>
                <div class="stat-item">
                    <div class="stat-number" id="subscribers-count"><?= $subscribers_count ?></div>
                    <div class="stat-label">подписчиков</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?= $following_count ?></div>
                    <div class="stat-label">подписок</div>
                </div>
            </div>
            
            <div class="profile-actions">
                <?php if ($is_artist): ?>
                    <a href="price-list.php?artist_id=<?= $viewing_id ?>" class="btn">
                        📋 Посмотреть прайс-лист
                    </a>
                <?php endif; ?>
                
                <?php if (isLoggedIn() && $_SESSION['user_id'] != $viewing_id && $is_artist): ?>
                    <button id="subscribe-btn" class="btn btn-subscribe <?= $is_subscribed ? 'subscribed' : '' ?>" data-artist-id="<?= $viewing_id ?>">
                        <?= $is_subscribed ? '✅ Подписан' : '🔔 Подписаться' ?>
                    </button>
                <?php endif; ?>
                
                <?php if ($is_own_profile && $is_artist): ?>
                    <a href="admin/price_list.php" class="btn">
                        ⚙️ Редактировать прайс-лист
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($is_artist): ?>
        <!-- Навигация по папкам -->
        <div class="folders-nav">
            <a href="artist.php?id=<?php echo $viewing_id; ?>" class="folder-tab <?php echo $selected_folder == 0 ? 'active' : ''; ?>">
                📁 Все работы
            </a>
            <?php foreach ($artist_folders as $folder): ?>
                <div style="display: inline-flex; align-items: center;">
                    <a href="artist.php?id=<?php echo $viewing_id; ?>&folder=<?php echo $folder['id']; ?>" class="folder-tab <?php echo $selected_folder == $folder['id'] ? 'active' : ''; ?>">
                        📂 <?php echo htmlspecialchars($folder['name']); ?>
                        <span style="font-size: 0.7rem;">(<?php echo count($folder['works']); ?>)</span>
                    </a>
                    <?php if ($is_own_profile): ?>
                        <a href="artist.php?id=<?php echo $viewing_id; ?>&delete_folder=<?php echo $folder['id']; ?>" class="delete-folder-btn" onclick="return confirm('Удалить папку? Работы не удалятся')">✕</a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            
            <?php if ($is_own_profile && count($artist_folders) < 5 && !$show_create_form): ?>
                <a href="artist.php?id=<?php echo $viewing_id; ?>&create_folder=1" class="folder-tab create-folder-btn">➕ Создать папку</a>
            <?php endif; ?>
        </div>
        
        <!-- Форма создания папки -->
        <?php if ($is_own_profile && $show_create_form && count($artist_folders) < 5): ?>
            <div class="create-folder-form">
                <h4>📁 Создать новую папку</h4>
                <?php if ($folder_error): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($folder_error); ?></div>
                <?php endif; ?>
                <?php if ($folder_success): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($folder_success); ?></div>
                <?php endif; ?>
                <form method="POST" action="artist.php?id=<?php echo $viewing_id; ?>&create_folder=1">
                    <input type="text" name="name" placeholder="Название папки" required>
                    <textarea name="description" rows="2" placeholder="Описание (необязательно)"></textarea>
                    <div class="form-actions">
                        <button type="submit" name="create_folder" class="btn btn-primary">Создать</button>
                        <a href="artist.php?id=<?php echo $viewing_id; ?>" class="btn btn-secondary">Отмена</a>
                    </div>
                </form>
            </div>
        <?php endif; ?>
        
        <!-- Галерея работ -->
        <?php if (empty($filtered_works)): ?>
            <div class="empty-message">
                <p>😔 В этой папке пока нет работ</p>
                <?php if ($is_own_profile): ?>
                    <p><a href="/admin/artworks/create.php" class="btn" style="margin-top: 10px;">➕ Добавить работу</a></p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="gallery-grid">
                <?php foreach ($filtered_works as $work): ?>
                    <div class="gallery-item">
                        <a href="artwork.php?id=<?php echo $work['id']; ?>" style="text-decoration: none; color: inherit;">
                            <img src="assets/uploads/artworks/<?php echo htmlspecialchars($work['image_url']); ?>" 
                                 alt="<?php echo htmlspecialchars($work['title']); ?>">
                            <div class="gallery-info">
                                <h3><?php echo htmlspecialchars($work['title']); ?></h3>
                                <?php if ($work['category_name']): ?>
                                    <p><?php echo htmlspecialchars($work['category_name']); ?></p>
                                <?php endif; ?>
                                <?php if ($work['price'] && $work['is_for_sale'] && !$work['is_sold']): ?>
                                    <p class="price"><?php echo number_format($work['price'], 0, '', ' '); ?> <?php echo $work['currency'] == 'USD' ? '$' : '₽'; ?></p>
                                <?php elseif ($work['is_sold']): ?>
                                    <p class="price sold"><?php echo number_format($work['price'], 0, '', ' '); ?> <?php echo $work['currency'] == 'USD' ? '$' : '₽'; ?> <span class="sold-badge">Продано</span></p>
                                <?php endif; ?>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
    <?php else: ?>
        <div class="client-message">
            <p>👤 Этот пользователь не является художником</p>
            <p style="font-size: 0.9rem; margin-top: 10px;">Только художники могут публиковать свои работы и иметь прайс-лист</p>
        </div>
    <?php endif; ?>
    
    <!-- РАЗДЕЛЫ ПОДПИСОК (ТОЛЬКО ДЛЯ СВОЕГО ПРОФИЛЯ) -->
    <?php if ($is_own_profile): ?>
        <div class="subscriptions-section">
            <h3>📌 Мои подписки (<?php echo $following_count; ?>)</h3>
            <?php if (empty($user_following)): ?>
                <div class="empty-list">
                    <p>Вы еще ни на кого не подписаны</p>
                    <p><a href="index.php" class="btn">Найти художников</a></p>
                </div>
            <?php else: ?>
                <div class="subscriptions-grid">
                    <?php foreach ($user_following as $follow): ?>
                        <a href="artist.php?id=<?php echo $follow['id']; ?>" class="subscription-card">
                            <?php if (!empty($follow['avatar']) && file_exists('assets/uploads/avatars/'.$follow['avatar'])): ?>
                                <img src="assets/uploads/avatars/<?php echo htmlspecialchars($follow['avatar']); ?>" class="subscription-avatar">
                            <?php else: ?>
                                <div class="subscription-avatar-placeholder">
                                    <?php echo mb_substr(($follow['full_name'] ?: $follow['username']), 0, 1); ?>
                                </div>
                            <?php endif; ?>
                            <div class="subscription-info">
                                <div class="subscription-name"><?php echo htmlspecialchars($follow['full_name'] ?: $follow['username']); ?></div>
                                <div class="subscription-username">@<?php echo htmlspecialchars($follow['username']); ?></div>
                                <span class="subscription-role"><?php echo $follow['role'] == 'artist' ? '🎨 Художник' : '👤 Пользователь'; ?></span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="subscriptions-section">
            <h3>👥 Мои подписчики (<?php echo $subscribers_count; ?>)</h3>
            <?php if (empty($user_subscribers)): ?>
                <div class="empty-list">
                    <p>На вас пока никто не подписан</p>
                </div>
            <?php else: ?>
                <div class="subscriptions-grid">
                    <?php foreach ($user_subscribers as $subscriber): ?>
                        <a href="artist.php?id=<?php echo $subscriber['id']; ?>" class="subscription-card">
                            <?php if (!empty($subscriber['avatar']) && file_exists('assets/uploads/avatars/'.$subscriber['avatar'])): ?>
                                <img src="assets/uploads/avatars/<?php echo htmlspecialchars($subscriber['avatar']); ?>" class="subscription-avatar">
                            <?php else: ?>
                                <div class="subscription-avatar-placeholder">
                                    <?php echo mb_substr(($subscriber['full_name'] ?: $subscriber['username']), 0, 1); ?>
                                </div>
                            <?php endif; ?>
                            <div class="subscription-info">
                                <div class="subscription-name"><?php echo htmlspecialchars($subscriber['full_name'] ?: $subscriber['username']); ?></div>
                                <div class="subscription-username">@<?php echo htmlspecialchars($subscriber['username']); ?></div>
                                <span class="subscription-role"><?php echo $subscriber['role'] == 'artist' ? '🎨 Художник' : '👤 Пользователь'; ?></span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<script>
var subscribeBtn = document.getElementById('subscribe-btn');
if (subscribeBtn) {
    subscribeBtn.addEventListener('click', function() {
        var btn = this;
        var artistId = btn.dataset.artistId;
        
        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'ajax/subscribe.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                if (xhr.status === 200) {
                    try {
                        var data = JSON.parse(xhr.responseText);
                        if (data.success) {
                            if (data.action === 'subscribed') {
                                btn.className = 'btn btn-subscribe subscribed';
                                btn.innerHTML = '✅ Подписан';
                            } else {
                                btn.className = 'btn btn-subscribe';
                                btn.innerHTML = '🔔 Подписаться';
                            }
                            document.getElementById('subscribers-count').innerHTML = data.subscribers_count;
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
        
        xhr.send('artist_id=' + artistId);
    });
}
</script>

<?php include 'includes/footer.php'; ?>