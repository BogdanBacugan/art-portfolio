<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

// Фильтры
$status_filter = '';
if ($filter == 'active') {
    $status_filter = "AND o.status IN ('new', 'in_progress')";
} elseif ($filter == 'completed') {
    $status_filter = "AND o.status = 'completed'";
} elseif ($filter == 'cancelled') {
    $status_filter = "AND o.status = 'cancelled'";
} else {
    $status_filter = "";
}

// Получаем заказы с фильтром
$stmt = $pdo->prepare("
    SELECT o.*, 
           c.username as client_username, c.full_name as client_full_name, c.avatar as client_avatar,
           a.username as artist_username, a.full_name as artist_full_name, a.avatar as artist_avatar
    FROM orders o
    JOIN users c ON o.client_id = c.id
    JOIN users a ON o.artist_id = a.id
    WHERE (o.client_id = ? OR o.artist_id = ?) $status_filter
    ORDER BY 
        CASE 
            WHEN o.status = 'new' THEN 1
            WHEN o.status = 'in_progress' THEN 2
            WHEN o.status = 'completed' THEN 3
            ELSE 4
        END,
        o.created_at DESC
");
$stmt->execute(array($user_id, $user_id));
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Подсчет статистики для фильтров
$stmt_all = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE client_id = ? OR artist_id = ?");
$stmt_all->execute(array($user_id, $user_id));
$total_all = $stmt_all->fetchColumn();

$stmt_active = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE (client_id = ? OR artist_id = ?) AND status IN ('new', 'in_progress')");
$stmt_active->execute(array($user_id, $user_id));
$total_active = $stmt_active->fetchColumn();

$stmt_completed = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE (client_id = ? OR artist_id = ?) AND status = 'completed'");
$stmt_completed->execute(array($user_id, $user_id));
$total_completed = $stmt_completed->fetchColumn();

$stmt_cancelled = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE (client_id = ? OR artist_id = ?) AND status = 'cancelled'");
$stmt_cancelled->execute(array($user_id, $user_id));
$total_cancelled = $stmt_cancelled->fetchColumn();
?>

<?php include 'includes/header.php'; ?>

<style>
.orders-container {
    max-width: 1000px;
    margin: 40px auto;
    padding: 20px;
}
.orders-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    overflow: hidden;
}
.orders-header {
    padding: 20px;
    border-bottom: 1px solid #eee;
    background: #f8f9fa;
}
.orders-header h1 {
    margin: 0;
    font-size: 1.5rem;
}
.filter-bar {
    display: flex;
    gap: 10px;
    padding: 15px 20px;
    background: white;
    border-bottom: 1px solid #eee;
    flex-wrap: wrap;
}
.filter-btn {
    padding: 6px 16px;
    border-radius: 20px;
    text-decoration: none;
    font-size: 0.85rem;
    background: #f0f0f0;
    color: #666;
    transition: all 0.2s;
}
.filter-btn:hover {
    background: #e0e0e0;
}
.filter-btn.active {
    background: #3498db;
    color: white;
}
.filter-btn .count {
    background: rgba(0,0,0,0.1);
    border-radius: 10px;
    padding: 0 6px;
    margin-left: 5px;
    font-size: 0.7rem;
}
.filter-btn.active .count {
    background: rgba(255,255,255,0.2);
}
.orders-list {
    list-style: none;
    margin: 0;
    padding: 0;
}
.order-item {
    padding: 20px;
    border-bottom: 1px solid #f0f0f0;
    transition: background 0.2s;
}
.order-item:hover {
    background: #fafafa;
}
.order-link {
    text-decoration: none;
    color: inherit;
    display: block;
}
.order-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
    flex-wrap: wrap;
    gap: 10px;
}
.order-title {
    font-size: 1.1rem;
    font-weight: bold;
    color: #333;
}
.order-status {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: bold;
}
.status-new { background: #fff3cd; color: #856404; }
.status-in_progress { background: #cce5ff; color: #004085; }
.status-completed { background: #d4edda; color: #155724; }
.status-cancelled { background: #f8d7da; color: #721c24; }
.order-info {
    color: #666;
    font-size: 0.85rem;
    margin-top: 5px;
}
.order-price {
    font-weight: bold;
    color: #2c3e50;
}
.empty-message {
    text-align: center;
    padding: 60px;
    color: #999;
}
.role-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 0.7rem;
    margin-left: 8px;
}
.client-badge {
    background: #cce5ff;
    color: #004085;
}
.artist-badge {
    background: #d4edda;
    color: #155724;
}
</style>

<div class="orders-container">
    <div class="orders-card">
        <div class="orders-header">
            <h1>📦 Мои заказы</h1>
        </div>
        
        <div class="filter-bar">
            <a href="?filter=all" class="filter-btn <?= $filter == 'all' ? 'active' : '' ?>">
                Все заказы <span class="count"><?= $total_all ?></span>
            </a>
            <a href="?filter=active" class="filter-btn <?= $filter == 'active' ? 'active' : '' ?>">
                🟡 Активные <span class="count"><?= $total_active ?></span>
            </a>
            <a href="?filter=completed" class="filter-btn <?= $filter == 'completed' ? 'active' : '' ?>">
                ✅ Выполненные <span class="count"><?= $total_completed ?></span>
            </a>
            <a href="?filter=cancelled" class="filter-btn <?= $filter == 'cancelled' ? 'active' : '' ?>">
                ❌ Отмененные <span class="count"><?= $total_cancelled ?></span>
            </a>
        </div>
        
        <?php if (empty($orders)): ?>
            <div class="empty-message">
                <p>😔 У вас пока нет заказов</p>
                <?php if ($filter == 'active'): ?>
                    <p>Нет активных заказов</p>
                <?php elseif ($filter == 'completed'): ?>
                    <p>Нет выполненных заказов</p>
                <?php elseif ($filter == 'cancelled'): ?>
                    <p>Нет отмененных заказов</p>
                <?php else: ?>
                    <p>Посмотрите прайс-листы художников и сделайте заказ</p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <ul class="orders-list">
                <?php foreach ($orders as $order): ?>
                    <li class="order-item">
                        <a href="order_chat.php?id=<?= $order['id'] ?>" class="order-link">
                            <div class="order-header">
                                <span class="order-title">
                                    <?= htmlspecialchars($order['title']) ?>
                                    <?php if ($order['client_id'] == $user_id): ?>
                                        <span class="role-badge client-badge">Вы заказчик</span>
                                    <?php else: ?>
                                        <span class="role-badge artist-badge">Вы исполнитель</span>
                                    <?php endif; ?>
                                </span>
                                <span class="order-status status-<?= $order['status'] ?>">
                                    <?php 
                                        $status_names = array(
                                            'new' => '🆕 Новый',
                                            'in_progress' => '🔄 В работе',
                                            'completed' => '✅ Выполнен',
                                            'cancelled' => '❌ Отменен'
                                        );
                                        echo $status_names[$order['status']];
                                    ?>
                                </span>
                            </div>
                            <div class="order-info">
                                <?php if ($order['client_id'] == $user_id): ?>
                                    Художник: <strong><?= htmlspecialchars($order['artist_full_name'] ?: $order['artist_username']) ?></strong><br>
                                <?php else: ?>
                                    Заказчик: <strong><?= htmlspecialchars($order['client_full_name'] ?: $order['client_username']) ?></strong><br>
                                <?php endif; ?>
                                Сумма: <span class="order-price"><?= number_format($order['price'], 0, '', ' ') ?> <?= $order['currency'] == 'USD' ? '$' : '₽' ?></span><br>
                                Дата: <?= date('d.m.Y H:i', strtotime($order['created_at'])) ?>
                            </div>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>