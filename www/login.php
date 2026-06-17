<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Функция для проверки пароля
function custom_password_verify($password, $hash) {
    return crypt($password, $hash) === $hash;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
    $stmt->execute(array($username, $username));
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && custom_password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_name'] = $user['full_name'];
        
        // Перенаправляем на главную страницу
        header('Location: index.php');
        exit;
    } else {
        $error = 'Неверное имя пользователя или пароль';
    }
}
?>

<?php include 'includes/header.php'; ?>

<div class="container">
    <div class="form-container">
        <h1>Вход на сайт</h1>
        
        <?php if ($error): ?>
            <div class="alert error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="username">Имя пользователя или Email:</label>
                <input type="text" id="username" name="username" required>
            </div>
            
            <div class="form-group">
                <label for="password">Пароль:</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit" class="btn btn-primary">Войти</button>
        </form>
        
        <p class="text-center">
            Нет аккаунта? <a href="register.php">Зарегистрируйтесь</a>
        </p>
    </div>
</div>

<?php include 'includes/footer.php'; ?>