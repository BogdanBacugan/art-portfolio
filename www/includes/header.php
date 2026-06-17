<?php
if (!function_exists('isLoggedIn')) {
    require_once 'config.php';
}

// Определяем текущую страницу для подсветки меню
$current_page = basename($_SERVER['PHP_SELF']);

// Получаем данные пользователя из БД, если он авторизован
$user_avatar = '/assets/img/default-avatar.png'; // аватар по умолчанию
$user_display_name = 'Пользователь';
$first_letter = '?';
$unread_notifications = 0;
$unread_orders = 0;

if (isLoggedIn()) {
    $user_id = $_SESSION['user_id'];
    $user_role = getUserRole();
    
    $stmt = $pdo->prepare("SELECT full_name, username, avatar FROM users WHERE id = ?");
    $stmt->execute(array($user_id));
    $user_data = $stmt->fetch();
    
    if ($user_data) {
        if (!empty($user_data['full_name'])) {
            $user_display_name = $user_data['full_name'];
        } elseif (!empty($user_data['username'])) {
            $user_display_name = $user_data['username'];
        }
        
        $first_letter = strtoupper(mb_substr($user_display_name, 0, 1));
        
        if (!empty($user_data['avatar'])) {
            $user_avatar = '/assets/uploads/avatars/' . $user_data['avatar'];
        }
    }
    
    $stmt_notify = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = FALSE");
    $stmt_notify->execute(array($user_id));
    $unread_notifications = $stmt_notify->fetchColumn();
    
    if ($user_role == 'artist') {
        $stmt_orders = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE artist_id = ? AND status = 'new'");
        $stmt_orders->execute(array($user_id));
        $unread_orders = $stmt_orders->fetchColumn();
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Художественное портфолио</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 2rem;
            background: #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .logo a {
            font-size: 1.5rem;
            font-weight: bold;
            text-decoration: none;
            color: #333;
        }
        .nav-menu {
            display: flex;
            list-style: none;
            gap: 2rem;
            align-items: center;
            margin: 0;
            padding: 0;
        }
        .nav-menu li {
            list-style: none;
        }
        .nav-menu a {
            text-decoration: none;
            color: #666;
            transition: color 0.3s;
        }
        .nav-menu a:hover {
            color: #000;
        }
        .nav-menu a.active {
            color: #007bff;
            font-weight: bold;
        }
        .user-menu {
            display: flex;
            align-items: center;
        }
        .user-menu a {
            display: flex;
            align-items: center;
            gap: 10px;
            color: inherit;
            text-decoration: none;
        }
        .user-menu a:hover {
            color: #007bff;
        }
        .user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            object-fit: cover;
            background: #007bff;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        .user-avatar-img {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            object-fit: cover;
        }
        .notifications-link, .orders-link {
            position: relative;
        }
        .notifications-badge, .orders-badge {
            position: absolute;
            top: -8px;
            right: -12px;
            background: #e74c3c;
            color: white;
            font-size: 10px;
            font-weight: bold;
            padding: 2px 5px;
            border-radius: 10px;
            min-width: 18px;
            text-align: center;
        }
    </style>
</head>
<body>
    <header>
        <nav class="navbar">
            <div class="logo">
                <a href="/">ArtPortfolio</a>
            </div>
            <ul class="nav-menu">
                <li><a href="/" class="<?php echo ($current_page == 'index.php') ? 'active' : ''; ?>">Главная</a></li>
                
                <?php if (isLoggedIn() && getUserRole() == 'artist'): ?>
                    <li><a href="/portfolio.php" class="<?php echo ($current_page == 'portfolio.php') ? 'active' : ''; ?>">🎨 Мое портфолио</a></li>
                <?php endif; ?>
                
                <?php if (isLoggedIn() && getUserRole() == 'artist'): ?>
                    <li><a href="my-price-list.php">💰 Мой прайс-лист</a></li>
                <?php endif; ?>
                
                <?php if (isLoggedIn()): ?>
                    <li class="orders-link">
                        <a href="/orders.php">
                            📦 Заказы
                            <?php if ($unread_orders > 0): ?>
                                <span class="orders-badge"><?php echo $unread_orders; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    
                    <li class="notifications-link">
                        <a href="/notifications.php">
                            🔔 Уведомления
                            <?php if ($unread_notifications > 0): ?>
                                <span class="notifications-badge"><?php echo $unread_notifications; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    
                    <li class="user-menu">
                        <a href="/profile.php">
                            <?php if ($user_avatar != '/assets/img/default-avatar.png'): ?>
                                <img src="<?php echo $user_avatar; ?>" alt="Avatar" class="user-avatar-img">
                            <?php else: ?>
                                <span class="user-avatar">
                                    <?php echo $first_letter; ?>
                                </span>
                            <?php endif; ?>
                            <span><?php echo htmlspecialchars($user_display_name, ENT_QUOTES, 'UTF-8'); ?></span>
                        </a>
                    </li>
                    <li><a href="/logout.php">Выйти</a></li>
                <?php else: ?>
                    <li><a href="/login.php">Вход</a></li>
                    <li><a href="/register.php">Регистрация</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </header>
    <main>