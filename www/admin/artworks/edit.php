<?php
session_start();
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Функция транслитерации (русские буквы → латиница)
function transliterate($string) {
    $trans = array(
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e',
        'ё' => 'e', 'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k',
        'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r',
        'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'c',
        'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch', 'ъ' => '', 'ы' => 'y', 'ь' => '',
        'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
        'А' => 'A', 'Б' => 'B', 'В' => 'V', 'Г' => 'G', 'Д' => 'D', 'Е' => 'E',
        'Ё' => 'E', 'Ж' => 'Zh', 'З' => 'Z', 'И' => 'I', 'Й' => 'Y', 'К' => 'K',
        'Л' => 'L', 'М' => 'M', 'Н' => 'N', 'О' => 'O', 'П' => 'P', 'Р' => 'R',
        'С' => 'S', 'Т' => 'T', 'У' => 'U', 'Ф' => 'F', 'Х' => 'H', 'Ц' => 'C',
        'Ч' => 'Ch', 'Ш' => 'Sh', 'Щ' => 'Sch', 'Ъ' => '', 'Ы' => 'Y', 'Ь' => '',
        'Э' => 'E', 'Ю' => 'Yu', 'Я' => 'Ya'
    );
    return strtr($string, $trans);
}

// Проверяем авторизацию и роль художника
if (!isLoggedIn() || getUserRole() != 'artist') {
    header('Location: ../../login.php');
    exit;
}

$artist_id = $_SESSION['user_id'];
$error = '';
$success = '';
$artwork_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Получаем данные работы
$stmt = $pdo->prepare("
    SELECT * FROM artworks 
    WHERE id = ? AND artist_id = ?
");
$stmt->execute(array($artwork_id, $artist_id));
$artwork = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$artwork) {
    header('Location: index.php');
    exit;
}

// Получаем категории
$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Получаем папки пользователя
$stmt = $pdo->prepare("SELECT * FROM folders WHERE user_id = ? ORDER BY sort_order ASC, name ASC");
$stmt->execute(array($artist_id));
$folders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Получаем текущие папки работы
$stmt = $pdo->prepare("SELECT folder_id FROM artwork_folders WHERE artwork_id = ?");
$stmt->execute(array($artwork_id));
$current_folders = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Получаем теги из строки
$selected_tags = array();
if (!empty($artwork['tags'])) {
    $selected_tags = array_map('trim', explode(',', $artwork['tags']));
    $selected_tags = array_filter($selected_tags);
}

// Функция для генерации уникального slug
function generateUniqueSlug($pdo, $title, $exclude_id = null) {
    $base_slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
    $base_slug = trim($base_slug, '-');
    
    if (empty($base_slug)) {
        $base_slug = 'work';
    }
    
    $slug = $base_slug;
    $counter = 1;
    
    while (true) {
        if ($exclude_id) {
            $stmt = $pdo->prepare("SELECT id FROM artworks WHERE slug = ? AND id != ?");
            $stmt->execute(array($slug, $exclude_id));
        } else {
            $stmt = $pdo->prepare("SELECT id FROM artworks WHERE slug = ?");
            $stmt->execute(array($slug));
        }
        
        if (!$stmt->fetch()) {
            break;
        }
        
        $slug = $base_slug . '-' . $counter;
        $counter++;
    }
    
    return $slug;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $technique = trim($_POST['technique']);
    $year = !empty($_POST['year']) ? (int)$_POST['year'] : null;
    $price = !empty($_POST['price']) ? floatval($_POST['price']) : null;
    $currency = isset($_POST['currency']) ? $_POST['currency'] : 'RUB';
    $is_for_sale = isset($_POST['is_for_sale']) ? 1 : 0;
    $is_available = isset($_POST['is_available']) ? 1 : 0;
    $tags = isset($_POST['tags']) ? trim($_POST['tags']) : '';
    $selected_folders = isset($_POST['folder_ids']) ? $_POST['folder_ids'] : array();
    
    // Обрабатываем теги
    $tags_array = array();
    if (!empty($tags)) {
        $tags_array = array_map('trim', explode(',', $tags));
        $tags_array = array_filter($tags_array);
        $tags = implode(',', $tags_array);
    }
    
    if (empty($title)) {
        $error = 'Название работы обязательно';
    } else {
        // Генерируем уникальный slug
        $slug = generateUniqueSlug($pdo, $title, $artwork_id);
        
        // Обработка нового изображения (если загружено)
        $image_name = $artwork['image_url'];
        
        if (!empty($_FILES['image']['name'])) {
            $target_dir = "../../assets/uploads/artworks/";
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            
            // Транслитерируем имя файла
            $image_name = time() . '_' . transliterate(basename($_FILES["image"]["name"]));
            $target_file = $target_dir . $image_name;
            
            $check = getimagesize($_FILES["image"]["tmp_name"]);
            if ($check === false) {
                $error = "Файл не является изображением";
            } elseif (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
                // Удаляем старое изображение
                if ($artwork['image_url'] && file_exists($target_dir . $artwork['image_url'])) {
                    unlink($target_dir . $artwork['image_url']);
                }
            } else {
                $error = "Ошибка при загрузке файла";
            }
        }
        
        if (empty($error)) {
            try {
                $pdo->beginTransaction();
                
                // Обновляем работу
                $stmt = $pdo->prepare("
                    UPDATE artworks 
                    SET title = ?, slug = ?, description = ?, category_id = ?, 
                        technique = ?, year = ?, price = ?, currency = ?, is_for_sale = ?, 
                        is_available = ?, image_url = ?, tags = ?
                    WHERE id = ? AND artist_id = ?
                ");
                
                $stmt->execute(array(
                    $title, $slug, $description, $category_id,
                    $technique, $year, $price, $currency, $is_for_sale,
                    $is_available, $image_name, $tags, $artwork_id, $artist_id
                ));
                
                // Обновляем связи с папками
                $stmt = $pdo->prepare("DELETE FROM artwork_folders WHERE artwork_id = ?");
                $stmt->execute(array($artwork_id));
                
                if (!empty($selected_folders)) {
                    $stmt_folder = $pdo->prepare("INSERT INTO artwork_folders (artwork_id, folder_id) VALUES (?, ?)");
                    foreach ($selected_folders as $folder_id) {
                        $stmt_folder->execute(array($artwork_id, $folder_id));
                    }
                }
                
                $pdo->commit();
                
                $_SESSION['success'] = "Работа успешно обновлена!";
                header('Location: /portfolio.php');
                exit;
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Ошибка при сохранении: " . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Редактировать работу</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .admin-container {
            max-width: 800px;
            margin: 40px auto;
            padding: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
        }
        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        .form-row .form-group {
            flex: 1;
            margin-bottom: 0;
        }
        .checkbox-group {
            display: flex;
            gap: 20px;
            align-items: center;
            margin-bottom: 20px;
        }
        .checkbox-group label {
            display: flex;
            align-items: center;
            gap: 5px;
            font-weight: normal;
            cursor: pointer;
        }
        .folders-checkbox-group {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 10px;
        }
        .folders-checkbox-group label {
            display: flex;
            align-items: center;
            gap: 5px;
            cursor: pointer;
            font-weight: normal;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary {
            background: #3498db;
            color: white;
        }
        .btn-primary:hover {
            background: #2980b9;
        }
        .btn-secondary {
            background: #95a5a6;
            color: white;
        }
        .btn-secondary:hover {
            background: #7f8c8d;
        }
        .alert {
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .alert.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .current-image {
            margin-top: 10px;
        }
        .current-image img {
            max-width: 200px;
            border-radius: 4px;
        }
        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        small {
            color: #666;
            font-size: 0.8rem;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <h1>✏️ Редактировать работу</h1>
        
        <?php if ($error): ?>
            <div class="alert error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <form method="POST" action="" enctype="multipart/form-data">
            <div class="form-group">
                <label for="title">Название работы *</label>
                <input type="text" id="title" name="title" value="<?= htmlspecialchars($artwork['title']) ?>" required>
            </div>
            
            <div class="form-group">
                <label for="category_id">Категория</label>
                <select id="category_id" name="category_id">
                    <option value="">-- Выберите категорию --</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= $category['id'] ?>" <?= $artwork['category_id'] == $category['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($category['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="description">Описание</label>
                <textarea id="description" name="description" rows="5"><?= htmlspecialchars($artwork['description']) ?></textarea>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="technique">Техника исполнения</label>
                    <input type="text" id="technique" name="technique" value="<?= htmlspecialchars($artwork['technique']) ?>" 
                           placeholder="например: масло, холст">
                </div>
                
                <div class="form-group">
                    <label for="year">Год создания</label>
                    <input type="number" id="year" name="year" min="1900" max="<?= date('Y') ?>" 
                           value="<?= $artwork['year'] ?>">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="price">Цена (₽/$)</label>
                    <input type="number" id="price" name="price" min="0" step="any" value="<?= $artwork['price'] ?>">
                </div>
                <div class="form-group">
                    <label for="currency">Валюта</label>
                    <select id="currency" name="currency">
                        <option value="RUB" <?= $artwork['currency'] == 'RUB' ? 'selected' : '' ?>>₽ Рубль</option>
                        <option value="USD" <?= $artwork['currency'] == 'USD' ? 'selected' : '' ?>>$ Доллар</option>
                    </select>
                </div>
            </div>
            
            <div class="checkbox-group">
                <label>
                    <input type="checkbox" name="is_for_sale" <?= $artwork['is_for_sale'] ? 'checked' : '' ?>>
                    Доступно для продажи
                </label>
                <label>
                    <input type="checkbox" name="is_available" <?= $artwork['is_available'] ? 'checked' : '' ?>>
                    В наличии (не продано)
                </label>
            </div>
            
            <div class="form-group">
                <label for="tags">Теги</label>
                <input type="text" id="tags" name="tags" value="<?= htmlspecialchars($artwork['tags']) ?>" 
                       placeholder="например: пейзаж, море, лето" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                <small>Введите теги через запятую. Например: пейзаж, портрет, масло</small>
            </div>
            
            <!-- ВЫБОР ПАПОК (несколько) -->
            <div class="form-group">
                <label>Папки</label>
                <div class="folders-checkbox-group">
                    <?php if (empty($folders)): ?>
                        <p style="color: #666;">У вас пока нет папок. <a href="/portfolio.php?create_folder=1" style="color: #3498db;">Создать папку</a></p>
                    <?php else: ?>
                        <?php foreach ($folders as $folder): ?>
                            <label>
                                <input type="checkbox" name="folder_ids[]" value="<?= $folder['id'] ?>" <?= in_array($folder['id'], $current_folders) ? 'checked' : '' ?>>
                                📂 <?= htmlspecialchars($folder['name']) ?>
                            </label>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <small>Выберите папки для этой работы (можно несколько)</small>
            </div>
            
            <div class="form-group">
                <label for="image">Изображение работы</label>
                <input type="file" id="image" name="image" accept="image/*">
                <div class="current-image">
                    <p>Текущее изображение:</p>
                    <img src="../../assets/uploads/artworks/<?= htmlspecialchars($artwork['image_url']) ?>" 
                         alt="<?= htmlspecialchars($artwork['title']) ?>">
                </div>
                <small>Оставьте пустым, чтобы не менять изображение</small>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">💾 Сохранить изменения</button>
                <a href="index.php" class="btn btn-secondary">❌ Отмена</a>
            </div>
        </form>
    </div>
</body>
</html>