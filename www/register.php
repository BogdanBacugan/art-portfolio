<?php
require_once 'includes/config.php';

$error = '';
$success = '';

// Функция для генерации соли
function generateSalt() {
    $chars = './ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    $salt = '';
    for ($i = 0; $i < 22; $i++) {
        $salt .= $chars[rand(0, 63)];
    }
    return $salt;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login']);           // логин для входа
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $display_name = trim($_POST['display_name']);  // имя для отображения
    $access_key = isset($_POST['access_key']) ? trim($_POST['access_key']) : '';
    
    if ($password !== $confirm_password) {
        $error = 'Пароли не совпадают';
    } elseif (strlen($password) < 6) {
        $error = 'Пароль должен быть не менее 6 символов';
    } else {
        // Проверяем уникальность логина и email
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute(array($login, $email));
        if ($stmt->fetch()) {
            $error = 'Пользователь с таким логином или email уже существует';
        } else {
            // Определяем роль на основе ключа доступа
            $role = 'client'; // по умолчанию клиент
            $valid_key = false;
            $key_data = null;
            
            if (!empty($access_key)) {
                // Проверяем, существует ли такой ключ в базе
                $stmt = $pdo->prepare("SELECT * FROM access_keys WHERE key_code = ? AND (is_active = 1 OR is_active IS NULL)");
                $stmt->execute(array($access_key));
                $key_data = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($key_data) {
                    // Проверяем, не использован ли уже ключ
                    if (empty($key_data['used_by']) && empty($key_data['used_at'])) {
                        $role = 'artist'; // ключ верный и не использован - даем роль художника
                        $valid_key = true;
                    } else {
                        $error = 'Этот ключ уже был использован';
                    }
                } else {
                    // Если ключ указан, но неверный - показываем ошибку
                    $error = 'Неверный ключ доступа';
                }
            }
            
            // Если нет ошибок, создаем пользователя
            if (empty($error)) {
                // Хешируем пароль
                $salt = '$2y$10$' . generateSalt();
                $password_hash = crypt($password, $salt);
                
                $stmt = $pdo->prepare("
                    INSERT INTO users (username, email, password_hash, role, full_name) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                
                if ($stmt->execute(array($login, $email, $password_hash, $role, $display_name))) {
                    // Получаем ID только что созданного пользователя
                    $user_id = $pdo->lastInsertId();
                    
                    // Если ключ был использован, помечаем его
                    if ($valid_key && $key_data) {
                        $stmt = $pdo->prepare("UPDATE access_keys SET used_by = ?, used_at = NOW() WHERE key_code = ?");
                        $stmt->execute(array($user_id, $access_key));
                    }
                    
                    $success = 'Регистрация успешна! <a href="login.php">Войти</a>';
                } else {
                    $error = 'Ошибка при регистрации';
                }
            }
        }
    }
}
?>

<?php include 'includes/header.php'; ?>

<div class="container">
    <div class="form-container">
        <h1>Регистрация</h1>
        
        <?php if ($error): ?>
            <div class="alert error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert success"><?= $success ?></div>
        <?php else: ?>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="display_name">Имя (как вас будут видеть другие):</label>
                    <input type="text" id="display_name" name="display_name" required>
                    <small>Это имя будут видеть другие пользователи</small>
                </div>
                
                <div class="form-group">
                    <label for="login">Логин (для входа):</label>
                    <input type="text" id="login" name="login" required>
                    <small>Используйте этот логин для входа на сайт</small>
                </div>
                
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Пароль:</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Подтвердите пароль:</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                
                <div class="form-group">
                    <label for="access_key">🎨 Ключ доступа (если есть):</label>
                    <input type="text" id="access_key" name="access_key" placeholder="Введите ключ для регистрации как художник">
                    <small>Если у вас есть ключ доступа, вы станете художником. Иначе - обычный пользователь.</small>
                </div>
                
                <button type="submit" class="btn btn-primary">Зарегистрироваться</button>
            </form>
            
            <p class="text-center">
                Уже есть аккаунт? <a href="login.php">Войдите</a>
            </p>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>