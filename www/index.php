<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_term = '%' . $search . '%';
$search_type = isset($_GET['search_type']) ? $_GET['search_type'] : 'all';

// Параметры для разворачивания
$show_all_subscriptions = isset($_GET['show_all_subscriptions']) ? true : false;
$show_all_recent = isset($_GET['show_all_recent']) ? true : false;

// Получаем популярных художников
$artists = $pdo->query("
    SELECT u.id, u.username, u.full_name, u.avatar, 
           COUNT(DISTINCT a.id) as artworks_count,
           COUNT(DISTINCT s.follower_id) as subscribers_count
    FROM users u
    LEFT JOIN artworks a ON u.id = a.artist_id
    LEFT JOIN subscriptions s ON u.id = s.following_id
    WHERE u.role = 'artist'
    GROUP BY u.id
    ORDER BY subscribers_count DESC, artworks_count DESC
    LIMIT 5
")->fetchAll();

// Получаем работы для главной страницы
$subscriptions_works = array();
$recent_works = array();
$found_users = array();

if (!empty($search)) {
    // ПОИСК в зависимости от выбранного типа
    if ($search_type == 'artworks') {
        // Поиск ТОЛЬКО по работам
        $stmt = $pdo->prepare("
            SELECT a.*, u.username, u.full_name, u.avatar
            FROM artworks a
            JOIN users u ON a.artist_id = u.id
            WHERE a.is_available = TRUE 
            AND (
                a.title LIKE ? 
                OR a.description LIKE ? 
                OR a.tags LIKE ?
            )
            ORDER BY a.created_at DESC
        ");
        $stmt->execute(array($search_term, $search_term, $search_term));
        $recent_works = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $found_users = array(); // Не ищем пользователей
        
    } elseif ($search_type == 'users') {
        // Поиск ТОЛЬКО по художникам
        $stmt = $pdo->prepare("
            SELECT a.*, u.username, u.full_name, u.avatar
            FROM artworks a
            JOIN users u ON a.artist_id = u.id
            WHERE a.is_available = TRUE 
            AND (
                u.username LIKE ? 
                OR u.full_name LIKE ?
            )
            ORDER BY a.created_at DESC
        ");
        $stmt->execute(array($search_term, $search_term));
        $recent_works = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Получаем список найденных пользователей
        $stmt_users = $pdo->prepare("
            SELECT id, username, full_name, avatar, role
            FROM users
            WHERE (username LIKE ? OR full_name LIKE ?)
            ORDER BY full_name ASC
        ");
        $stmt_users->execute(array($search_term, $search_term));
        $found_users = $stmt_users->fetchAll(PDO::FETCH_ASSOC);
        
    } else {
        // Поиск ВЕЗДЕ (по работам и пользователям)
        $stmt = $pdo->prepare("
            SELECT a.*, u.username, u.full_name, u.avatar
            FROM artworks a
            JOIN users u ON a.artist_id = u.id
            WHERE a.is_available = TRUE 
            AND (
                a.title LIKE ? 
                OR a.description LIKE ? 
                OR a.tags LIKE ?
                OR u.username LIKE ?
                OR u.full_name LIKE ?
            )
            ORDER BY a.created_at DESC
        ");
        $stmt->execute(array($search_term, $search_term, $search_term, $search_term, $search_term));
        $recent_works = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Получаем список найденных пользователей
        $stmt_users = $pdo->prepare("
            SELECT id, username, full_name, avatar, role
            FROM users
            WHERE (username LIKE ? OR full_name LIKE ?)
            ORDER BY full_name ASC
        ");
        $stmt_users->execute(array($search_term, $search_term));
        $found_users = $stmt_users->fetchAll(PDO::FETCH_ASSOC);
    }
}
    
 else {
   // БЕЗ ПОИСКА
    
    // Если пользователь авторизован - получаем работы по подпискам
    if (isLoggedIn()) {
        $user_id = $_SESSION['user_id'];
        
        $stmt = $pdo->prepare("
            SELECT a.*, u.username, u.full_name, u.avatar
            FROM artworks a
            JOIN users u ON a.artist_id = u.id
            JOIN subscriptions s ON s.following_id = u.id
            WHERE 1=1 AND s.follower_id = ?
            ORDER BY a.created_at DESC
        ");
        $stmt->execute(array($user_id));
        $all_subscriptions_works = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if ($show_all_subscriptions) {
            $subscriptions_works = $all_subscriptions_works;
        } else {
            $subscriptions_works = array_slice($all_subscriptions_works, 0, 6);
        }
    }
    
    // Получаем последние работы
    $all_recent_works = $pdo->query("
        SELECT a.*, u.username, u.full_name, u.avatar
        FROM artworks a
        JOIN users u ON a.artist_id = u.id
        WHERE 1=1
        ORDER BY a.created_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    if ($show_all_recent) {
        $recent_works = $all_recent_works;
    } else {
        $recent_works = array_slice($all_recent_works, 0, 6);
    }
}
?>

<?php include 'includes/header.php'; ?>

<style>
.search-section {
    max-width: 1400px;
    margin: 0 auto 20px;
    padding: 0 20px;
}
.search-form-container {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
}
.search-main {
    display: flex;
    gap: 10px;
    margin-bottom: 15px;
}
.search-main input {
    flex: 1;
    padding: 12px 15px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 1rem;
}
.search-main button {
    padding: 12px 25px;
    background: #3498db;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 1rem;
}
.search-main button:hover {
    background: #2980b9;
}
.search-type {
    display: flex;
    gap: 20px;
    align-items: center;
}
.search-type label {
    display: flex;
    align-items: center;
    gap: 5px;
    cursor: pointer;
}
.search-results-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}
.clear-search {
    color: #e74c3c;
    text-decoration: none;
}
.users-list {
    background: #fff;
    border-radius: 8px;
    margin-bottom: 30px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}
.users-list h3 {
    padding: 15px 20px;
    margin: 0;
    border-bottom: 1px solid #eee;
    background: #f8f9fa;
    border-radius: 8px 8px 0 0;
}
.users-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 15px;
    padding: 20px;
}
.user-card {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 12px;
    background: #f8f9fa;
    border-radius: 8px;
    text-decoration: none;
    transition: all 0.2s;
}
.user-card:hover {
    background: #e9ecef;
    transform: translateX(5px);
}
.user-avatar-small {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    object-fit: cover;
}
.user-avatar-placeholder {
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
.user-info {
    flex: 1;
}
.user-name {
    font-weight: bold;
    color: #333;
    margin-bottom: 3px;
}
.user-username {
    font-size: 0.8rem;
    color: #666;
}
.user-role {
    font-size: 0.7rem;
    padding: 2px 6px;
    border-radius: 10px;
    background: #e0e0e0;
    display: inline-block;
}
.role-artist {
    background: #d4edda;
    color: #155724;
}
.role-client {
    background: #cce5ff;
    color: #004085;
}
.hero {
    text-align: center;
    padding: 4rem 2rem;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}
.hero h1 {
    font-size: 2.5rem;
    margin-bottom: 1rem;
}
.content-wrapper {
    display: flex;
    gap: 30px;
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
}
.main-content {
    flex: 3;
}
.sidebar {
    flex: 1;
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    align-self: start;
}
.artists-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.artist-card {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px;
    background: white;
    border-radius: 6px;
    text-decoration: none;
    transition: all 0.2s;
}
.artist-card:hover {
    background: #e9ecef;
}
.artist-card .avatar-small {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    object-fit: cover;
}
.artist-card .avatar-placeholder-small {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    font-weight: bold;
}
.artist-details {
    display: flex;
    flex-direction: column;
}
.artist-name {
    font-weight: bold;
    color: #333;
}
.artworks-count {
    font-size: 0.8rem;
    color: #666;
}

/* Сетка для работ - без горизонтальной прокрутки */
.recommendations-row {
    margin-bottom: 40px;
}
.recommendations-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}
.recommendations-header h2 {
    margin: 0;
    font-size: 1.5rem;
}
.show-more-btn {
    background: #3498db;
    color: white;
    padding: 8px 16px;
    border-radius: 4px;
    text-decoration: none;
    font-size: 0.9rem;
}
.show-more-btn:hover {
    background: #2980b9;
    text-decoration: none;
}
.artworks-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
}
.artwork-card {
    background: white;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: transform 0.3s;
}
.artwork-card:hover {
    transform: translateY(-5px);
}
.artwork-card img {
    width: 100%;
    height: 200px;
    object-fit: cover;
}
.artwork-info {
    padding: 12px;
}
.artwork-info h3 {
    margin: 0 0 5px;
    font-size: 1rem;
    white-space: normal;
    overflow: hidden;
    text-overflow: ellipsis;
}
.price {
    font-weight: bold;
    color: #2c3e50;
    margin-top: 5px;
    font-size: 0.9rem;
}
.empty-message {
    text-align: center;
    padding: 40px;
    color: #666;
    background: #f8f9fa;
    border-radius: 8px;
}
@media (max-width: 768px) {
    .content-wrapper {
        flex-direction: column;
    }
    .hero h1 {
        font-size: 2rem;
    }
    .artworks-grid {
        grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
        gap: 15px;
    }
}
</style>

<!-- БАННЕР -->
<section class="hero">
    <h1>Художественное портфолио</h1>
    <p>Платформа для художников и ценителей искусства</p>
</section>

<!-- ПОИСК -->
<div class="search-section">
    <div class="search-form-container">
        <form method="GET" action="index.php">
            <div class="search-main">
                <input type="text" name="search" placeholder="Поиск по художникам, работам, тегам..." 
                       value="<?= htmlspecialchars($search) ?>">
                <button type="submit">🔍 Найти</button>
            </div>
            <div class="search-type">
                <label><input type="radio" name="search_type" value="all" <?= $search_type == 'all' ? 'checked' : '' ?>> Везде</label>
                <label><input type="radio" name="search_type" value="artworks" <?= $search_type == 'artworks' ? 'checked' : '' ?>> По работам</label>
                <label><input type="radio" name="search_type" value="users" <?= $search_type == 'users' ? 'checked' : '' ?>> По художникам</label>
            </div>
        </form>
    </div>
</div>

<div class="content-wrapper">
    <div class="main-content">
        <?php if (!empty($search)): ?>
            <!-- РЕЗУЛЬТАТЫ ПОИСКА -->
            <div class="search-results-info">
                <p>Результаты поиска по запросу: <strong>"<?= htmlspecialchars($search) ?>"</strong></p>
                <a href="index.php" class="clear-search">× Очистить поиск</a>
            </div>
            
            <?php if (!empty($found_users)): ?>
                <div class="users-list">
                    <h3>👥 Найденные пользователи (<?= count($found_users) ?>)</h3>
                    <div class="users-grid">
                        <?php foreach ($found_users as $user_found): ?>
                            <a href="artist.php?id=<?= $user_found['id'] ?>" class="user-card">
                                <?php if (!empty($user_found['avatar']) && file_exists('assets/uploads/avatars/'.$user_found['avatar'])): ?>
                                    <img src="assets/uploads/avatars/<?= htmlspecialchars($user_found['avatar']) ?>" class="user-avatar-small">
                                <?php else: ?>
                                    <div class="user-avatar-placeholder"><?= mb_substr(($user_found['full_name'] ?: $user_found['username']), 0, 1) ?></div>
                                <?php endif; ?>
                                <div class="user-info">
                                    <div class="user-name"><?= htmlspecialchars($user_found['full_name'] ?: $user_found['username']) ?></div>
                                    <div class="user-username">@<?= htmlspecialchars($user_found['username']) ?></div>
                                    <span class="user-role <?= $user_found['role'] == 'artist' ? 'role-artist' : 'role-client' ?>"><?= $user_found['role'] == 'artist' ? '🎨 Художник' : '👤 Пользователь' ?></span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="recommendations-row">
                <div class="recommendations-header"><h2>🖼️ Найденные работы</h2></div>
                <?php if (empty($recent_works)): ?>
                    <div class="empty-message">По вашему запросу работ не найдено</div>
                <?php else: ?>
                    <div class="artworks-grid">
                        <?php foreach ($recent_works as $artwork): ?>
                            <div class="artwork-card">
                                <a href="artwork.php?id=<?= $artwork['id'] ?>" style="text-decoration: none; color: inherit;">
                                    <img src="assets/uploads/artworks/<?= htmlspecialchars($artwork['image_url']) ?>" alt="<?= htmlspecialchars($artwork['title']) ?>">
                                    <div class="artwork-info">
                                        <h3><?= htmlspecialchars($artwork['title']) ?></h3>
                                        <a href="artist.php?id=<?= $artwork['artist_id'] ?>" style="display: flex; align-items: center; gap: 5px; text-decoration: none; color: #666; font-size: 0.8rem; margin: 5px 0;">
                                            <?php if (!empty($artwork['avatar']) && file_exists('assets/uploads/avatars/'.$artwork['avatar'])): ?>
                                                <img src="assets/uploads/avatars/<?= htmlspecialchars($artwork['avatar']) ?>" style="width: 20px !important; height: 20px !important; min-width: 20px !important; max-width: 20px !important; border-radius: 50% !important; object-fit: cover !important;">
                                            <?php else: ?>
                                                <div style="width: 20px !important; height: 20px !important; min-width: 20px !important; max-width: 20px !important; border-radius: 50%; background: linear-gradient(135deg, #667eea, #764ba2); color: white; display: inline-flex; align-items: center; justify-content: center; font-size: 10px; font-weight: bold;"><?= mb_substr(($artwork['full_name'] ?: $artwork['username']), 0, 1) ?></div>
                                            <?php endif; ?>
                                            <span><?= htmlspecialchars($artwork['full_name'] ?: $artwork['username']) ?></span>
                                        </a>
                                        <?php if ($artwork['price'] && $artwork['is_for_sale']): ?>
    <p class="price"><?= number_format($artwork['price'], 0, '', ' ') ?> <?= $artwork['currency'] == 'USD' ? '$' : '₽' ?></p>
<?php endif; ?>
                                    </div>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
        <?php else: ?>
            <!-- ОСНОВНАЯ СТРАНИЦА -->
            
            <?php if (isLoggedIn() && !empty($all_subscriptions_works)): ?>
                <div class="recommendations-row">
                   <div class="recommendations-header">
    <h2>📌 Мои подписки</h2>
    <?php if (count($all_subscriptions_works) > 6 && !$show_all_subscriptions): ?>
        <a href="recommendations.php?type=subscriptions" class="show-more-btn">Смотреть дальше →</a>
    <?php endif; ?>
</div>
                    <div class="artworks-grid">
                        <?php foreach ($subscriptions_works as $artwork): ?>
                            <div class="artwork-card">
                                <a href="artwork.php?id=<?= $artwork['id'] ?>" style="text-decoration: none; color: inherit;">
                                    <img src="assets/uploads/artworks/<?= htmlspecialchars($artwork['image_url']) ?>" alt="<?= htmlspecialchars($artwork['title']) ?>">
                                    <div class="artwork-info">
                                        <h3><?= htmlspecialchars($artwork['title']) ?></h3>
                                        <a href="artist.php?id=<?= $artwork['artist_id'] ?>" style="display: flex; align-items: center; gap: 5px; text-decoration: none; color: #666; font-size: 0.8rem; margin: 5px 0;">
                                            <?php if (!empty($artwork['avatar']) && file_exists('assets/uploads/avatars/'.$artwork['avatar'])): ?>
                                                <img src="assets/uploads/avatars/<?= htmlspecialchars($artwork['avatar']) ?>" style="width: 20px !important; height: 20px !important; min-width: 20px !important; max-width: 20px !important; border-radius: 50% !important; object-fit: cover !important;">
                                            <?php else: ?>
                                                <div style="width: 20px !important; height: 20px !important; min-width: 20px !important; max-width: 20px !important; border-radius: 50%; background: linear-gradient(135deg, #667eea, #764ba2); color: white; display: inline-flex; align-items: center; justify-content: center; font-size: 10px; font-weight: bold;"><?= mb_substr(($artwork['full_name'] ?: $artwork['username']), 0, 1) ?></div>
                                            <?php endif; ?>
                                            <span><?= htmlspecialchars($artwork['full_name'] ?: $artwork['username']) ?></span>
                                        </a>
                                        <?php if ($artwork['price'] && $artwork['is_for_sale']): ?><div class="price"><?= number_format($artwork['price'], 0, '', ' ') ?> ₽</div><?php endif; ?>
                                    </div>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="recommendations-row">
                <div class="recommendations-header">
    <h2>✨ <?= isLoggedIn() && !empty($all_subscriptions_works) ? 'Другие работы' : 'Недавние работы' ?></h2>
    <?php if (count($all_recent_works) > 6 && !$show_all_recent): ?>
        <a href="recommendations.php?type=recent" class="show-more-btn">Смотреть дальше →</a>
    <?php endif; ?>
</div>
                <div class="artworks-grid">
                    <?php foreach ($recent_works as $artwork): ?>
                        <div class="artwork-card">
                            <a href="artwork.php?id=<?= $artwork['id'] ?>" style="text-decoration: none; color: inherit;">
                                <img src="assets/uploads/artworks/<?= htmlspecialchars($artwork['image_url']) ?>" alt="<?= htmlspecialchars($artwork['title']) ?>">
                                <div class="artwork-info">
                                    <h3><?= htmlspecialchars($artwork['title']) ?></h3>
                                    <a href="artist.php?id=<?= $artwork['artist_id'] ?>" style="display: flex; align-items: center; gap: 5px; text-decoration: none; color: #666; font-size: 0.8rem; margin: 5px 0;">
                                        <?php if (!empty($artwork['avatar']) && file_exists('assets/uploads/avatars/'.$artwork['avatar'])): ?>
                                            <img src="assets/uploads/avatars/<?= htmlspecialchars($artwork['avatar']) ?>" style="width: 20px !important; height: 20px !important; min-width: 20px !important; max-width: 20px !important; border-radius: 50% !important; object-fit: cover !important;">
                                        <?php else: ?>
                                            <div style="width: 20px !important; height: 20px !important; min-width: 20px !important; max-width: 20px !important; border-radius: 50%; background: linear-gradient(135deg, #667eea, #764ba2); color: white; display: inline-flex; align-items: center; justify-content: center; font-size: 10px; font-weight: bold;"><?= mb_substr(($artwork['full_name'] ?: $artwork['username']), 0, 1) ?></div>
                                        <?php endif; ?>
                                        <span><?= htmlspecialchars($artwork['full_name'] ?: $artwork['username']) ?></span>
                                    </a>
                                    <?php if ($artwork['price'] && $artwork['is_for_sale']): ?>
    <p class="price"><?= format_price($artwork['price'], $artwork['currency']) ?></p>
<?php endif; ?>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
   <aside class="sidebar">
    <h3>🎨 Популярные художники</h3>
    <div class="artists-list">
        <?php foreach ($artists as $artist): ?>
            <a href="artist.php?id=<?= $artist['id'] ?>" class="artist-card">
                <?php if (!empty($artist['avatar']) && file_exists('assets/uploads/avatars/'.$artist['avatar'])): ?>
                    <img src="assets/uploads/avatars/<?= htmlspecialchars($artist['avatar']) ?>" class="avatar-small">
                <?php else: ?>
                    <div class="avatar-placeholder-small"><?= mb_substr(($artist['full_name'] ?: $artist['username']), 0, 1) ?></div>
                <?php endif; ?>
                <div class="artist-details">
                    <span class="artist-name"><?= htmlspecialchars($artist['full_name'] ?: $artist['username']) ?></span>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</aside>
</div>

<?php include 'includes/footer.php'; ?>