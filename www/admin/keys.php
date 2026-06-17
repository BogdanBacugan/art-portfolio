<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../includes/config.php';

if (!isLoggedIn() || getUserRole() != 'artist') {
    header('Location: ../login.php');
    exit;
}

// Получаем информацию о количестве ключей художника
$stmt = $pdo->prepare("SELECT keys_used FROM users WHERE id = ?");
$stmt->execute(array($_SESSION['user_id']));
$user = $stmt->fetch();
$keys_used = $user ? $user['keys_used'] : 0;
$max_keys = 3;
$available_keys = $max_keys - $keys_used;

// Генерация нового ключа
if (isset($_POST['generate']) && $available_keys > 0) {
    // Генерируем уникальный ключ
    $key_code = strtoupper(md5(uniqid(rand(), true)));
    $key_code = substr($key_code, 0, 8) . '-' . substr($key_code, 8, 4) . '-' . substr($key_code, 12, 4) . '-' . substr($key_code, 16, 4) . '-' . substr($key_code, 20, 12);
    
    $stmt = $pdo->prepare("INSERT INTO access_keys (key_code, created_by) VALUES (?, ?)");
    $stmt->execute(array($key_code, $_SESSION['user_id']));
    
    $success = 'Ключ успешно создан!';
}

// Получаем список ключей художника
$stmt = $pdo->prepare("
    SELECT k.*, u.username as used_by_username, u.full_name as used_by_name
    FROM access_keys k
    LEFT JOIN users u ON k.used_by = u.id
    WHERE k.created_by = ?
    ORDER BY k.created_at DESC
");
$stmt->execute(array($_SESSION['user_id']));
$keys = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Управление ключами</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        .admin-container {
            max-width: 800px;
            margin: 40px auto;
            padding: 20px;
        }
        
        .keys-info {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 8px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .keys-info h2 {
            margin: 0 0 10px;
            font-size: 2em;
        }
        
        .keys-count {
            font-size: 3em;
            font-weight: bold;
            margin: 20px 0;
        }
        
        .keys-available {
            font-size: 1.2em;
            opacity: 0.9;
        }
        
        .generate-btn {
            background: white;
            color: #667eea;
            border: none;
            padding: 15px 30px;
            font-size: 1.1em;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            transition: transform 0.2s;
        }
        
        .generate-btn:hover {
            transform: scale(1.05);
        }
        
        .generate-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .keys-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .keys-table th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
        }
        
        .keys-table td {
            padding: 15px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .key-code {
            font-family: monospace;
            font-size: 1.1em;
            background: #f8f9fa;
            padding: 5px 10px;
            border-radius: 4px;
        }
        
        .key-status {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.9em;
        }
        
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        
        .status-used {
            background: #f8d7da;
            color: #721c24;
        }
        
        .copy-btn {
            background: none;
            border: 1px solid #007bff;
            color: #007bff;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9em;
        }
        
        .copy-btn:hover {
            background: #007bff;
            color: white;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="admin-container">
        <div class="keys-info">
            <h2>Пригласительные ключи</h2>
            <p>Поделитесь ключами с друзьями, чтобы они могли стать художниками</p>
            <div class="keys-count"><?php echo $available_keys; ?> / <?php echo $max_keys; ?></div>
            <div class="keys-available">доступно ключей</div>
            
            <form method="POST" style="margin-top: 20px;">
                <button type="submit" name="generate" class="generate-btn" <?php echo $available_keys <= 0 ? 'disabled' : ''; ?>>
                    ✨ Сгенерировать новый ключ
                </button>
            </form>
        </div>
        
        <?php if (isset($success)): ?>
            <div class="alert"><?php echo $success; ?></div>
        <?php endif; ?>
        
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
                    <?php foreach ($keys as $key): ?>
                        <tr>
                            <td>
                                <span class="key-code"><?php echo $key['key_code']; ?></span>
                            </td>
                            <td>
                                <?php if ($key['used_by']): ?>
                                    <span class="key-status status-used">Использован</span>
                                <?php else: ?>
                                    <span class="key-status status-active">Активен</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($key['used_by']): ?>
                                    <?php echo htmlspecialchars($key['used_by_name'] ?: $key['used_by_username'], ENT_QUOTES, 'UTF-8'); ?>
                                    <br>
                                    <small><?php echo date('d.m.Y', strtotime($key['used_at'])); ?></small>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('d.m.Y', strtotime($key['created_at'])); ?></td>
                            <td>
                                <?php if (!$key['used_by']): ?>
                                    <button class="copy-btn" onclick="copyKey('<?php echo $key['key_code']; ?>')">📋 Копировать</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p style="text-align: center; color: #666; padding: 40px;">
                У вас пока нет ключей. Сгенерируйте первый ключ, чтобы пригласить друзей!
            </p>
        <?php endif; ?>
    </div>
    
    <script>
    function copyKey(key) {
        // Создаем временное поле для копирования
        var tempInput = document.createElement('input');
        tempInput.value = key;
        document.body.appendChild(tempInput);
        tempInput.select();
        document.execCommand('copy');
        document.body.removeChild(tempInput);
        
        // Показываем уведомление
        alert('Ключ скопирован в буфер обмена!');
    }
    </script>
    
    <?php include '../includes/footer.php'; ?>
</body>
</html>