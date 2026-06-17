<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'includes/config.php';
require_once 'includes/functions.php';

// Проверяем авторизацию
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Функция для хэширования пароля
function custom_password_hash($password) {
    $salt = '$2a$07$' . substr(str_replace('+', '.', base64_encode(md5(mt_rand(), true))), 0, 22);
    return crypt($password, $salt);
}

function custom_password_verify($password, $hash) {
    return crypt($password, $hash) === $hash;
}

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Получаем данные пользователя
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute(array($user_id));
$user = $stmt->fetch();

$is_artist = ($user['role'] == 'artist');

// Получаем количество подписчиков (кто подписан на меня)
$stmt = $pdo->prepare("SELECT COUNT(*) FROM subscriptions WHERE following_id = ?");
$stmt->execute(array($user_id));
$subscribers_count = $stmt->fetchColumn();

// Получаем количество подписок (на кого я подписан)
$stmt = $pdo->prepare("SELECT COUNT(*) FROM subscriptions WHERE follower_id = ?");
$stmt->execute(array($user_id));
$following_count = $stmt->fetchColumn();

// Получаем количество работ (если художник)
$artworks_count = 0;
if ($is_artist) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM artworks WHERE artist_id = ?");
    $stmt->execute(array($user_id));
    $artworks_count = $stmt->fetchColumn();
}

// Получаем список подписок (на кого я подписан)
$following_list = array();
$stmt = $pdo->prepare("
    SELECT u.id, u.username, u.full_name, u.avatar, u.role
    FROM subscriptions s
    JOIN users u ON s.following_id = u.id
    WHERE s.follower_id = ?
    ORDER BY s.created_at DESC
");
$stmt->execute(array($user_id));
$following_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Получаем список подписчиков (кто подписан на меня)
$subscribers_list = array();
$stmt = $pdo->prepare("
    SELECT u.id, u.username, u.full_name, u.avatar, u.role
    FROM subscriptions s
    JOIN users u ON s.follower_id = u.id
    WHERE s.following_id = ?
    ORDER BY s.created_at DESC
");
$stmt->execute(array($user_id));
$subscribers_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Получаем ключи пользователя (если он художник)
$keys = array();
if ($is_artist) {
    $stmt = $pdo->prepare("
        SELECT k.*, u.username as used_by_username, u.full_name as used_by_name
        FROM access_keys k
        LEFT JOIN users u ON k.used_by = u.id
        WHERE k.created_by = ?
        ORDER BY k.created_at DESC
    ");
    $stmt->execute(array($user_id));
    $keys = $stmt->fetchAll();
}

// Обработка форм
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Обновление информации
    if (isset($_POST['update_info'])) {
        $full_name = trim($_POST['full_name']);
        $bio = trim($_POST['bio']);
        $email = trim($_POST['email']);
        
        $errors = array();
        
        if (empty($full_name)) {
            $errors[] = 'Введите имя';
        }
        
        if (empty($email)) {
            $errors[] = 'Введите email';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Введите корректный email';
        }
        
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute(array($email, $user_id));
        if ($stmt->fetch()) {
            $errors[] = 'Этот email уже используется другим пользователем';
        }
        
        if (empty($errors)) {
            $stmt = $pdo->prepare("UPDATE users SET full_name = ?, bio = ?, email = ? WHERE id = ?");
            $stmt->execute(array($full_name, $bio, $email, $user_id));
            $message = 'Информация обновлена';
            
            $_SESSION['user_name'] = $full_name;
            
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute(array($user_id));
            $user = $stmt->fetch();
        } else {
            $error = implode('<br>', $errors);
        }
    }
    
    // Смена пароля
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        $errors = array();
        
        if (empty($current_password)) {
            $errors[] = 'Введите текущий пароль';
        } elseif (!custom_password_verify($current_password, $user['password_hash'])) {
            $errors[] = 'Неверный текущий пароль';
        }
        
        if (empty($new_password)) {
            $errors[] = 'Введите новый пароль';
        } elseif (strlen($new_password) < 6) {
            $errors[] = 'Новый пароль должен быть не менее 6 символов';
        }
        
        if ($new_password !== $confirm_password) {
            $errors[] = 'Новый пароль и подтверждение не совпадают';
        }
        
        if (empty($errors)) {
            $new_hash = custom_password_hash($new_password);
            $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->execute(array($new_hash, $user_id));
            $message = 'Пароль успешно изменен';
        } else {
            $error = implode('<br>', $errors);
        }
    }
    
    // Загрузка аватара
    if (isset($_POST['upload_avatar'])) {
        if (!empty($_FILES['avatar']['name'])) {
            $target_dir = $_SERVER['DOCUMENT_ROOT'] . "/assets/uploads/avatars/";
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            
            $ext = pathinfo($_FILES["avatar"]["name"], PATHINFO_EXTENSION);
            $avatar_name = 'avatar_' . $user_id . '_' . time() . '.' . $ext;
            $target_file = $target_dir . $avatar_name;
            
            $check = getimagesize($_FILES["avatar"]["tmp_name"]);
            if ($check === false) {
                $error = 'Файл не является изображением';
            } elseif ($_FILES["avatar"]["size"] > 5 * 1024 * 1024) {
                $error = 'Файл слишком большой (макс. 5MB)';
            } elseif (move_uploaded_file($_FILES["avatar"]["tmp_name"], $target_file)) {
                if (!empty($user['avatar']) && file_exists($target_dir . $user['avatar'])) {
                    unlink($target_dir . $user['avatar']);
                }
                
                $stmt = $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?");
                $stmt->execute(array($avatar_name, $user_id));
                
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute(array($user_id));
                $user = $stmt->fetch();
                
                $message = 'Аватар успешно загружен';
            } else {
                $error = 'Ошибка при загрузке файла';
            }
        } else {
            $error = 'Выберите файл';
        }
    }
    
    // Удаление аватара
    if (isset($_POST['delete_avatar'])) {
        if (!empty($user['avatar'])) {
            $target_dir = $_SERVER['DOCUMENT_ROOT'] . "/assets/uploads/avatars/";
            if (file_exists($target_dir . $user['avatar'])) {
                unlink($target_dir . $user['avatar']);
            }
            
            $stmt = $pdo->prepare("UPDATE users SET avatar = NULL WHERE id = ?");
            $stmt->execute(array($user_id));
            
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute(array($user_id));
            $user = $stmt->fetch();
            
            $message = 'Аватар удален';
        }
    }
    
    // Генерация ключа (для художников) - всего не более 3 ключей
    if (isset($_POST['generate_key']) && $is_artist) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM access_keys WHERE created_by = ?");
        $stmt->execute(array($user_id));
        $total_keys_count = $stmt->fetchColumn();
        
        $max_keys = 3;
        
        if ($total_keys_count >= $max_keys) {
            $error = 'Вы уже создали максимальное количество ключей (' . $max_keys . '). Создание новых ключей невозможно.';
        } else {
            $key_code = strtoupper(md5(uniqid(rand(), true)));
            $key_code = substr($key_code, 0, 8) . '-' . substr($key_code, 8, 4) . '-' . 
                        substr($key_code, 12, 4) . '-' . substr($key_code, 16, 4) . '-' . 
                        substr($key_code, 20, 12);
            
            $stmt = $pdo->prepare("INSERT INTO access_keys (key_code, created_by) VALUES (?, ?)");
            $stmt->execute(array($key_code, $user_id));
            
            $message = '✨ Ключ успешно создан!';
            
            $stmt = $pdo->prepare("
                SELECT k.*, u.username as used_by_username, u.full_name as used_by_name
                FROM access_keys k
                LEFT JOIN users u ON k.used_by = u.id
                WHERE k.created_by = ?
                ORDER BY k.created_at DESC
            ");
            $stmt->execute(array($user_id));
            $keys = $stmt->fetchAll();
        }
    }
}

$avatar_path = '/assets/img/default-avatar.png';
if (!empty($user['avatar'])) {
    $avatar_path = '/assets/uploads/avatars/' . $user['avatar'];
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Профиль пользователя</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        .profile-container {
            max-width: 1000px;
            margin: 40px auto;
            padding: 20px;
        }
        .profile-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 8px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 30px;
        }
        .avatar-large {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid white;
            background: white;
        }
        .profile-title h1 {
            margin: 0 0 10px;
            font-size: 2em;
        }
        .profile-title p {
            margin: 0;
            opacity: 0.9;
        }
        .profile-stats {
            display: flex;
            gap: 30px;
            margin-top: 15px;
        }
        .stat-item {
            text-align: center;
            background: rgba(255,255,255,0.2);
            padding: 8px 15px;
            border-radius: 8px;
        }
        .stat-number {
            font-size: 1.3rem;
            font-weight: bold;
        }
        .stat-label {
            font-size: 0.7rem;
            opacity: 0.8;
        }
        .profile-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 10px;
            flex-wrap: wrap;
        }
        .tab-btn {
            padding: 10px 20px;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1em;
            color: #666;
            border-radius: 4px 4px 0 0;
        }
        .tab-btn:hover {
            color: #007bff;
        }
        .tab-btn.active {
            color: #007bff;
            border-bottom: 2px solid #007bff;
            font-weight: bold;
        }
        .tab-content {
            display: none;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        .tab-content.active {
            display: block;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #555;
        }
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1em;
        }
        .form-group textarea {
            height: 100px;
            resize: vertical;
        }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
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
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1em;
        }
        .btn-primary {
            background: #007bff;
            color: white;
        }
        .btn-primary:hover {
            background: #0056b3;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        .avatar-container {
            text-align: center;
            margin-bottom: 20px;
        }
        .current-avatar {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 15px;
            border: 3px solid #007bff;
        }
        .avatar-help {
            font-size: 0.9em;
            color: #666;
            margin-top: 5px;
        }
        .subscriptions-section {
            margin-top: 20px;
            background: white;
            border-radius: 8px;
            padding: 15px;
        }
        .subscriptions-section h3 {
            margin: 0 0 15px 0;
            font-size: 1.1rem;
        }
        .subscriptions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 10px;
        }
        .subscription-card {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.2s;
        }
        .subscription-card:hover {
            background: #e9ecef;
            transform: translateX(5px);
        }
        .subscription-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }
        .subscription-avatar-placeholder {
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
        .subscription-info {
            flex: 1;
        }
        .subscription-name {
            font-weight: bold;
            color: #333;
            margin-bottom: 2px;
        }
        .subscription-username {
            font-size: 0.7rem;
            color: #666;
        }
        .subscription-role {
            font-size: 0.6rem;
            padding: 2px 6px;
            border-radius: 10px;
            background: #e0e0e0;
            display: inline-block;
            margin-top: 2px;
        }
        .empty-list {
            text-align: center;
            padding: 20px;
            color: #999;
        }
        .keys-info {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .keys-count {
            font-size: 2em;
            font-weight: bold;
            color: #28a745;
        }
        .keys-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 8px;
            overflow: hidden;
        }
        .keys-table th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
        }
        .keys-table td {
            padding: 12px;
            border-bottom: 1px solid #dee2e6;
        }
        .key-code {
            font-family: monospace;
            background: #f8f9fa;
            padding: 3px 6px;
            border-radius: 4px;
        }
        .status-active {
            color: #28a745;
            font-weight: bold;
        }
        .status-used {
            color: #dc3545;
        }
        .copy-btn {
            background: none;
            border: 1px solid #007bff;
            color: #007bff;
            padding: 3px 8px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9em;
        }
        .copy-btn:hover {
            background: #007bff;
            color: white;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="profile-container">
        <div class="profile-header">
            <img src="<?php echo $avatar_path; ?>" alt="Avatar" class="avatar-large" 
                 onerror="this.src='/assets/img/default-avatar.png';">
            <div class="profile-title">
                <h1><?php echo htmlspecialchars($user['full_name'], ENT_QUOTES, 'UTF-8'); ?></h1>
                <p>@<?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?></p>
                <p>Роль: <?php echo $user['role'] == 'artist' ? '🎨 Художник' : '👤 Клиент'; ?></p>
                
                <div class="profile-stats">
                    <?php if ($is_artist): ?>
                        <div class="stat-item">
                            <div class="stat-number"><?php echo $artworks_count; ?></div>
                            <div class="stat-label">работ</div>
                        </div>
                    <?php endif; ?>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $subscribers_count; ?></div>
                        <div class="stat-label">подписчиков</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $following_count; ?></div>
                        <div class="stat-label">подписок</div>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if (!empty($message)): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="profile-tabs">
            <button class="tab-btn active" onclick="showTab('info')">📋 Основная информация</button>
            <button class="tab-btn" onclick="showTab('avatar')">🖼️ Аватар</button>
            <button class="tab-btn" onclick="showTab('password')">🔒 Сменить пароль</button>
            <button class="tab-btn" onclick="showTab('subscriptions')">📌 Мои подписки</button>
            <button class="tab-btn" onclick="showTab('subscribers')">👥 Мои подписчики</button>
            <?php if ($user['role'] == 'artist'): ?>
                <button class="tab-btn" onclick="showTab('keys')">🔑 Ключи доступа</button>
            <?php endif; ?>
        </div>
        
        <!-- Вкладка с основной информацией -->
        <div id="tab-info" class="tab-content active">
            <h2>Основная информация</h2>
            <form method="POST">
                <div class="form-group">
                    <label for="full_name">Ваше имя</label>
                    <input type="text" id="full_name" name="full_name" 
                           value="<?php echo htmlspecialchars($user['full_name'], ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" 
                           value="<?php echo htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="bio">О себе</label>
                    <textarea id="bio" name="bio"><?php echo htmlspecialchars(isset($user['bio']) ? $user['bio'] : '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
                
                <button type="submit" name="update_info" class="btn btn-primary">Сохранить изменения</button>
            </form>
        </div>
        
        <!-- Вкладка с аватаром -->
        <div id="tab-avatar" class="tab-content">
            <h2>Управление аватаром</h2>
            <div class="avatar-container">
                <img src="<?php echo $avatar_path; ?>" alt="Current avatar" class="current-avatar"
                     onerror="this.src='/assets/img/default-avatar.png';">
                
                <form method="POST" enctype="multipart/form-data" style="margin-top: 20px;">
                    <div class="form-group">
                        <label for="avatar">Выберите новое изображение</label>
                        <input type="file" id="avatar" name="avatar" accept="image/*">
                        <div class="avatar-help">Максимальный размер: 5MB. Поддерживаются JPG, PNG, GIF</div>
                    </div>
                    
                    <button type="submit" name="upload_avatar" class="btn btn-primary">Загрузить аватар</button>
                    
                    <?php if (!empty($user['avatar'])): ?>
                        <button type="submit" name="delete_avatar" class="btn btn-danger" 
                                onclick="return confirm('Удалить аватар?')">Удалить аватар</button>
                    <?php endif; ?>
                </form>
            </div>
        </div>
        
        <!-- Вкладка смены пароля -->
        <div id="tab-password" class="tab-content">
            <h2>Смена пароля</h2>
            <form method="POST">
                <div class="form-group">
                    <label for="current_password">Текущий пароль</label>
                    <input type="password" id="current_password" name="current_password" required>
                </div>
                
                <div class="form-group">
                    <label for="new_password">Новый пароль</label>
                    <input type="password" id="new_password" name="new_password" required>
                    <small>Минимум 6 символов</small>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Подтвердите новый пароль</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                
                <button type="submit" name="change_password" class="btn btn-primary">Сменить пароль</button>
            </form>
        </div>
        
        <!-- Вкладка "Мои подписки" -->
        <div id="tab-subscriptions" class="tab-content">
            <h2>📌 Мои подписки (<?php echo $following_count; ?>)</h2>
            <?php if (empty($following_list)): ?>
                <div class="empty-list">
                    <p>Вы еще ни на кого не подписаны</p>
                    <p><a href="artists.php" class="btn btn-primary" style="margin-top:10px;">Найти художников</a></p>
                </div>
            <?php else: ?>
                <div class="subscriptions-grid">
                    <?php foreach ($following_list as $follow): ?>
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
        
        <!-- Вкладка "Мои подписчики" -->
        <div id="tab-subscribers" class="tab-content">
            <h2>👥 Мои подписчики (<?php echo $subscribers_count; ?>)</h2>
            <?php if (empty($subscribers_list)): ?>
                <div class="empty-list">
                    <p>На вас пока никто не подписан</p>
                </div>
            <?php else: ?>
                <div class="subscriptions-grid">
                    <?php foreach ($subscribers_list as $subscriber): ?>
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
        
        <?php if ($user['role'] == 'artist'): ?>
        <!-- Вкладка с ключами (только для художников) -->
        <div id="tab-keys" class="tab-content">
            <h2>Управление ключами доступа</h2>
            
            <div class="keys-info">
                <?php
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM access_keys WHERE created_by = ?");
                $stmt->execute(array($user_id));
                $total_keys_count = $stmt->fetchColumn();
                $max_keys = 3;
                $available_keys = $max_keys - $total_keys_count;
                ?>
                <h3>Доступно ключей: <span class="keys-count"><?php echo max(0, $available_keys); ?></span> из <?php echo $max_keys; ?></h3>
                <p>Вы можете создать не более <?php echo $max_keys; ?> ключей. Каждый ключ можно использовать только один раз.</p>
                
                <?php if ($total_keys_count < $max_keys): ?>
                    <form method="POST">
                        <button type="submit" name="generate_key" class="btn btn-primary">✨ Сгенерировать новый ключ</button>
                    </form>
                <?php else: ?>
                    <p style="color: #dc3545;">❌ Вы уже создали максимальное количество ключей (<?php echo $max_keys; ?>).</p>
                    <p>Создание новых ключей невозможно. Используйте существующие.</p>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($keys)): ?>
                <h3>Ваши ключи</h3>
                <table class="keys-table">
                    <thead>
                        <tr>
                            <th>Ключ</th>
                            <th>Статус</th>
                            <th>Использован</th>
                            <th>Дата создания</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($keys as $key): 
                            $used_by_name = '';
                            if (isset($key['used_by_name']) && !empty($key['used_by_name'])) {
                                $used_by_name = $key['used_by_name'];
                            } elseif (isset($key['used_by_username']) && !empty($key['used_by_username'])) {
                                $used_by_name = $key['used_by_username'];
                            }
                        ?>
                            <tr>
                                <td><span class="key-code"><?php echo htmlspecialchars($key['key_code']); ?></span></td>
                                <td>
                                    <?php if (isset($key['used_by']) && !empty($key['used_by'])): ?>
                                        <span class="status-used"> Использован</span>
                                    <?php else: ?>
                                        <span class="status-active"> Активен</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (isset($key['used_by']) && !empty($key['used_by'])): ?>
                                        <?php echo htmlspecialchars($used_by_name); ?>
                                        <br>
                                        <small><?php echo isset($key['used_at']) ? date('d.m.Y', strtotime($key['used_at'])) : ''; ?></small>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td><?php echo isset($key['created_at']) ? date('d.m.Y', strtotime($key['created_at'])) : ''; ?></td>
                                <td>
                                    <?php if (!isset($key['used_by']) || empty($key['used_by'])): ?>
                                        <button class="copy-btn" onclick="copyKey('<?php echo htmlspecialchars($key['key_code']); ?>')">📋 Копировать</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="text-align: center; padding: 40px; color: #666;">
                    У вас пока нет ключей. Сгенерируйте первый ключ, чтобы пригласить друзей!
                </p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <script>
    function showTab(tabName) {
        var tabs = document.querySelectorAll('.tab-content');
        for (var i = 0; i < tabs.length; i++) {
            tabs[i].classList.remove('active');
        }
        
        var btns = document.querySelectorAll('.tab-btn');
        for (var i = 0; i < btns.length; i++) {
            btns[i].classList.remove('active');
        }
        
        var activeTab = document.getElementById('tab-' + tabName);
        if (activeTab) {
            activeTab.classList.add('active');
        }
        
        var btn = event.target;
        btn.classList.add('active');
    }
    
    function copyKey(key) {
        var tempInput = document.createElement('input');
        tempInput.value = key;
        document.body.appendChild(tempInput);
        tempInput.select();
        document.execCommand('copy');
        document.body.removeChild(tempInput);
        alert('Ключ скопирован в буфер обмена!');
    }
    </script>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html>