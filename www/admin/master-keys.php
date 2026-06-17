<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../includes/config.php';

// Проверяем, что пользователь администратор (можно добавить поле is_admin в users)
// Для простоты будем считать администратором пользователя с id = 1
if (!isLoggedIn() || $_SESSION['user_id'] != 1) {
    header('Location: ../index.php');
    exit;
}

// Генерация мастер-ключа
if (isset($_POST['generate_master'])) {
    $key_code = 'MASTER-' . strtoupper(md5(uniqid(rand(), true)));
    $key_code = substr($key_code, 0, 8) . '-' . substr($key_code, 8, 4) . '-' . substr($key_code, 12, 4) . '-' . substr($key_code, 16, 4) . '-' . substr($key_code, 20, 12);
    
    $stmt = $pdo->prepare("INSERT INTO access_keys (key_code, created_by) VALUES (?, NULL)");
    $stmt->execute(array($key_code));
    
    $success = 'Мастер-ключ создан!';
}

// Получаем все ключи
$stmt = $pdo->query("
    SELECT k.*, 
           creator.username as creator_username, creator.full_name as creator_name,
           user.username as used_username, user.full_name as used_name
    FROM access_keys k
    LEFT JOIN users creator ON k.created_by = creator.id
    LEFT JOIN users user ON k.used_by = user.id
    ORDER BY k.created_at DESC
");
$keys = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Управление ключами (Админ)</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        .admin-container { max-width: 1000px; margin: 40px auto; padding: 20px; }
        .master-panel { background: #28a745; color: white; padding: 30px; border-radius: 8px; margin-bottom: 30px; text-align: center; }
        .btn { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
        .btn-primary { background: #007bff; color: white; }
        .btn-success { background: white; color: #28a745; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; background: white; border-radius: 8px; overflow: hidden; }
        th { background: #f8f9fa; padding: 15px; text-align: left; }
        td { padding: 15px; border-bottom: 1px solid #dee2e6; }
        .key-code { font-family: monospace; background: #f8f9fa; padding: 5px; border-radius: 4px; }
        .status-active { color: #28a745; font-weight: bold; }
        .status-used { color: #dc3545; }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="admin-container">
        <div class="master-panel">
            <h1>Панель администратора</h1>
            <p>Управление ключами доступа</p>
            <form method="POST">
                <button type="submit" name="generate_master" class="btn btn-success">✨ Сгенерировать мастер-ключ</button>
            </form>
        </div>
        
        <?php if (isset($success)): ?>
            <div style="background: #d4edda; color: #155724; padding: 15px; margin-bottom: 20px; border-radius: 4px;">
                <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <h2>Все ключи</h2>
        <table>
            <thead>
                <tr>
                    <th>Ключ</th>
                    <th>Создатель</th>
                    <th>Статус</th>
                    <th>Использован</th>
                    <th>Дата создания</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($keys as $key): ?>
                    <tr>
                        <td><span class="key-code"><?php echo $key['key_code']; ?></span></td>
                        <td>
                            <?php if ($key['created_by']): ?>
                                <?php echo htmlspecialchars($key['creator_name'] ?: $key['creator_username'], ENT_QUOTES, 'UTF-8'); ?>
                            <?php else: ?>
                                <span style="color: #28a745;">Мастер-ключ</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($key['used_by']): ?>
                                <span class="status-used">Использован</span>
                            <?php else: ?>
                                <span class="status-active">Активен</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($key['used_by']): ?>
                                <?php echo htmlspecialchars($key['used_name'] ?: $key['used_username'], ENT_QUOTES, 'UTF-8'); ?>
                                <br>
                                <small><?php echo date('d.m.Y H:i', strtotime($key['used_at'])); ?></small>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td><?php echo date('d.m.Y H:i', strtotime($key['created_at'])); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <?php include '../includes/footer.php'; ?>
</body>
</html>