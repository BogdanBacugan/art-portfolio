<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

$type = isset($_GET['type']) ? $_GET['type'] : 'recent'; // recent или subscriptions
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 12;
$offset = ($page - 1) * $limit;

// Получаем работы
$works = array();
$total = 0;

if ($type == 'subscriptions' && isLoggedIn()) {
    $user_id = $_SESSION['user_id'];
    
    // Подсчет общего количества
    $stmt_count = $pdo->prepare("
        SELECT COUNT(*) 
        FROM artworks a
        JOIN users u ON a.artist_id = u.id
        JOIN subscriptions s ON s.following_id = u.id
        WHERE a.is_available = TRUE AND s.follower_id = ?
    ");
    $stmt_count->execute(array($user_id));
    $total = $stmt_count->fetchColumn();
    
    // Получаем работы - используем конкатенацию для LIMIT
    $sql = "
        SELECT a.*, u.username, u.full_name, u.avatar
        FROM artworks a
        JOIN users u ON a.artist_id = u.id
        JOIN subscriptions s ON s.following_id = u.id
        WHERE  s.follower_id = ?
        ORDER BY a.created_at DESC
        LIMIT " . $limit . " OFFSET " . $offset;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array($user_id));
    $works = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $title = "Мои подписки";
    $back_link = "index.php";
    
} else {
    // Недавние работы
    $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM artworks ");
    $stmt_count->execute();
    $total = $stmt_count->fetchColumn();
    
    // Получаем работы - используем конкатенацию для LIMIT
    $sql = "
        SELECT a.*, u.username, u.full_name, u.avatar
        FROM artworks a
        JOIN users u ON a.artist_id = u.id
        
        ORDER BY a.created_at DESC
        LIMIT " . $limit . " OFFSET " . $offset;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $works = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $title = "Недавние работы";
    $back_link = "index.php";
}

$total_pages = ceil($total / $limit);
?>

<?php include 'includes/header.php'; ?>

<style>
.recommendations-page {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    flex-wrap: wrap;
    gap: 15px;
}
.page-header h1 {
    margin: 0;
    font-size: 1.8rem;
}
.back-link {
    display: inline-block;
    padding: 8px 16px;
    background: #f0f0f0;
    color: #333;
    text-decoration: none;
    border-radius: 4px;
}
.back-link:hover {
    background: #e0e0e0;
}
.artworks-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
    margin-bottom: 40px;
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
}
.artist-link {
    display: flex;
    align-items: center;
    gap: 5px;
    text-decoration: none;
    color: #666;
    font-size: 0.8rem;
    margin: 5px 0;
}
.artist-avatar {
    width: 20px !important;
    height: 20px !important;
    border-radius: 50%;
    object-fit: cover;
}
.price {
    font-weight: bold;
    color: #2c3e50;
    margin-top: 5px;
    font-size: 0.9rem;
}
.empty-message {
    text-align: center;
    padding: 60px;
    background: #f8f9fa;
    border-radius: 8px;
    color: #666;
}
.empty-message .btn {
    display: inline-block;
    margin-top: 15px;
    padding: 10px 20px;
    background: #3498db;
    color: white;
    text-decoration: none;
    border-radius: 4px;
}
.pagination {
    display: flex;
    justify-content: center;
    gap: 10px;
    margin-top: 30px;
    flex-wrap: wrap;
}
.pagination a, .pagination span {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    text-decoration: none;
    color: #333;
}
.pagination a:hover {
    background: #f0f0f0;
}
.pagination .current {
    background: #3498db;
    color: white;
    border-color: #3498db;
}
@media (max-width: 768px) {
    .artworks-grid {
        grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
        gap: 15px;
    }
    .page-header {
        flex-direction: column;
        text-align: center;
    }
}
</style>

<div class="recommendations-page">
    <div class="page-header">
        <h1><?= $title ?></h1>
        <a href="<?= $back_link ?>" class="back-link">← На главную</a>
    </div>
    
    <?php if (empty($works)): ?>
        <div class="empty-message">
            <p>😔 Здесь пока ничего нет</p>
            <?php if ($type == 'subscriptions'): ?>
                <p>Подпишитесь на художников, чтобы видеть их работы здесь</p>
                <a href="index.php" class="btn">Найти художников</a>
            <?php else: ?>
                <p>Пока нет ни одной работы</p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="artworks-grid">
            <?php foreach ($works as $work): ?>
                <div class="artwork-card">
                    <a href="artwork.php?id=<?= $work['id'] ?>" style="text-decoration: none; color: inherit;">
                        <img src="assets/uploads/artworks/<?= htmlspecialchars($work['image_url']) ?>" 
                             alt="<?= htmlspecialchars($work['title']) ?>">
                        <div class="artwork-info">
                            <h3><?= htmlspecialchars($work['title']) ?></h3>
                            <a href="artist.php?id=<?= $work['artist_id'] ?>" class="artist-link">
                                <?php if (!empty($work['avatar']) && file_exists('assets/uploads/avatars/'.$work['avatar'])): ?>
                                    <img src="assets/uploads/avatars/<?= htmlspecialchars($work['avatar']) ?>" class="artist-avatar">
                                <?php else: ?>
                                    <div style="width: 20px; height: 20px; border-radius: 50%; background: linear-gradient(135deg, #667eea, #764ba2); color: white; display: inline-flex; align-items: center; justify-content: center; font-size: 10px; font-weight: bold;">
                                        <?= mb_substr(($work['full_name'] ?: $work['username']), 0, 1) ?>
                                    </div>
                                <?php endif; ?>
                                <span><?= htmlspecialchars($work['full_name'] ?: $work['username']) ?></span>
                            </a>
                            <?php if ($work['price'] && $work['is_for_sale']): ?>
    <div class="price"><?= number_format($work['price'], 0, '', ' ') ?> <?= $work['currency'] == 'USD' ? '$' : '₽' ?></div>
<?php endif; ?>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Пагинация -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?type=<?= $type ?>&page=<?= $page - 1 ?>">← Предыдущая</a>
                <?php endif; ?>
                
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                
                if ($start_page > 1): ?>
                    <a href="?type=<?= $type ?>&page=1">1</a>
                    <?php if ($start_page > 2): ?><span>...</span><?php endif; ?>
                <?php endif; ?>
                
                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="current"><?= $i ?></span>
                    <?php else: ?>
                        <a href="?type=<?= $type ?>&page=<?= $i ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($end_page < $total_pages): ?>
                    <?php if ($end_page < $total_pages - 1): ?><span>...</span><?php endif; ?>
                    <a href="?type=<?= $type ?>&page=<?= $total_pages ?>"><?= $total_pages ?></a>
                <?php endif; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?type=<?= $type ?>&page=<?= $page + 1 ?>">Следующая →</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>