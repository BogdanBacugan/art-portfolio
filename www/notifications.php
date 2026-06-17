<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Проверяем авторизацию
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Удаление уведомления
if (isset($_GET['delete'])) {
    $notification_id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
    $stmt->execute(array($notification_id, $user_id));
    header('Location: notifications.php');
    exit;
}

// Удаление всех прочитанных
if (isset($_GET['clear_all'])) {
    $stmt = $pdo->prepare("DELETE FROM notifications WHERE user_id = ? AND is_read = TRUE");
    $stmt->execute(array($user_id));
    header('Location: notifications.php');
    exit;
}

// Отметить все как прочитанные
if (isset($_GET['mark_all_read'])) {
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = TRUE WHERE user_id = ?");
    $stmt->execute(array($user_id));
    header('Location: notifications.php');
    exit;
}

// Получаем уведомления
$stmt = $pdo->prepare("
    SELECT n.*, 
           u.username as source_username, 
           u.full_name as source_full_name,
           u.avatar as source_avatar
    FROM notifications n
    JOIN users u ON n.source_user_id = u.id
    WHERE n.user_id = ?
    ORDER BY n.created_at DESC
");
$stmt->execute(array($user_id));
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Количество непрочитанных
$stmt_unread = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = FALSE");
$stmt_unread->execute(array($user_id));
$unread_count = $stmt_unread->fetchColumn();
?>

<?php include 'includes/header.php'; ?>

<style>
.notifications-container {
    max-width: 800px;
    margin: 40px auto;
    padding: 20px;
}
.notifications-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    overflow: hidden;
}
.notifications-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    border-bottom: 1px solid #eee;
    background: #f8f9fa;
}
.notifications-header h1 {
    margin: 0;
    font-size: 1.5rem;
}
.notifications-header .badge {
    background: #e74c3c;
    color: white;
    padding: 2px 8px;
    border-radius: 20px;
    font-size: 0.8rem;
    margin-left: 10px;
}
.notifications-actions {
    display: flex;
    gap: 10px;
}
.btn-small {
    padding: 5px 12px;
    font-size: 0.8rem;
    background: #f0f0f0;
    color: #333;
    text-decoration: none;
    border-radius: 4px;
}
.btn-small:hover {
    background: #e0e0e0;
}
.notifications-list {
    list-style: none;
    margin: 0;
    padding: 0;
}
.notification-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 15px 20px;
    border-bottom: 1px solid #f0f0f0;
    transition: background 0.2s;
}
.notification-item.unread {
    background: #fff9e6;
}
.notification-item:hover {
    background: #fafafa;
}
.notification-content {
    display: flex;
    align-items: center;
    gap: 15px;
    flex: 1;
}
.notification-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
}
.notification-avatar-placeholder {
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
.notification-text {
    flex: 1;
}
.notification-text .message {
    margin: 0 0 5px 0;
    color: #333;
}
.notification-text .time {
    font-size: 0.7rem;
    color: #999;
}
.notification-icon {
    font-size: 1.2rem;
    margin-right: 5px;
}
.notification-delete {
    background: none;
    border: none;
    font-size: 1.2rem;
    cursor: pointer;
    color: #999;
    padding: 5px;
}
.notification-delete:hover {
    color: #e74c3c;
}
.empty-message {
    text-align: center;
    padding: 60px;
    color: #999;
}
.empty-message .icon {
    font-size: 3rem;
    margin-bottom: 15px;
}
@media (max-width: 768px) {
    .notifications-container {
        padding: 10px;
    }
    .notifications-header {
        flex-direction: column;
        gap: 10px;
        text-align: center;
    }
}
</style>

<div class="notifications-container">
    <div class="notifications-card">
        <div class="notifications-header">
            <div>
                <h1>🔔 Уведомления</h1>
                <?php if ($unread_count > 0): ?>
                    <span class="badge"><?= $unread_count ?> новых</span>
                <?php endif; ?>
            </div>
            <div class="notifications-actions">
                <?php if ($unread_count > 0): ?>
                    <a href="?mark_all_read=1" class="btn-small">📖 Отметить все прочитанными</a>
                <?php endif; ?>
                <?php if (!empty($notifications)): ?>
                    <a href="?clear_all=1" class="btn-small" onclick="return confirm('Удалить все прочитанные уведомления?')">🗑️ Очистить прочитанные</a>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if (empty($notifications)): ?>
            <div class="empty-message">
                <div class="icon">🔔</div>
                <p>У вас пока нет уведомлений</p>
                <p>Когда кто-то поставит лайк, оставит комментарий или подпишется, вы увидите это здесь</p>
            </div>
        <?php else: ?>
            <ul class="notifications-list">
                <?php foreach ($notifications as $notification): 
                    // Определяем иконку по типу
                    $icon = '';
                    $link = '';
                    if ($notification['type'] == 'like') {
                        $icon = '❤️';
                        $link = 'artwork.php?id=' . $notification['source_id'];
                    } elseif ($notification['type'] == 'comment') {
                        $icon = '💬';
                        $link = 'artwork.php?id=' . $notification['source_id'];
                    } elseif ($notification['type'] == 'subscribe') {
                        $icon = '👥';
                        $link = 'artist.php?id=' . $notification['source_user_id'];
                    }
                    
                    // Получаем имя пользователя
                    $source_name = !empty($notification['source_full_name']) ? $notification['source_full_name'] : $notification['source_username'];
                    $first_letter = mb_substr($source_name, 0, 1);
                    
                    // Отмечаем как прочитанное при клике
                    if (!$notification['is_read']) {
                        $stmt = $pdo->prepare("UPDATE notifications SET is_read = TRUE WHERE id = ?");
                        $stmt->execute(array($notification['id']));
                    }
                ?>
                    <li class="notification-item <?= !$notification['is_read'] ? 'unread' : '' ?>">
                        <div class="notification-content">
                            <a href="<?= $link ?>">
                                <?php if (!empty($notification['source_avatar']) && file_exists('assets/uploads/avatars/'.$notification['source_avatar'])): ?>
                                    <img src="assets/uploads/avatars/<?= htmlspecialchars($notification['source_avatar']) ?>" class="notification-avatar">
                                <?php else: ?>
                                    <div class="notification-avatar-placeholder">
                                        <?= $first_letter ?>
                                    </div>
                                <?php endif; ?>
                            </a>
                            <div class="notification-text">
                                <div class="message">
                                    <span class="notification-icon"><?= $icon ?></span>
                                    <strong><?= htmlspecialchars($source_name) ?></strong>
                                    <?= htmlspecialchars($notification['message']) ?>
                                </div>
                                <div class="time">
                                    <?= date('d.m.Y H:i', strtotime($notification['created_at'])) ?>
                                </div>
                            </div>
                        </div>
                        <a href="?delete=<?= $notification['id'] ?>" class="notification-delete" onclick="return confirm('Удалить уведомление?')">✕</a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>