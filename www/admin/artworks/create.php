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

if (!isLoggedIn() || getUserRole() != 'artist') {
    header('Location: ../../login.php');
    exit;
}

$artist_id = $_SESSION['user_id'];
$error = '';
$success = '';

$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Получаем папки пользователя
$stmt = $pdo->prepare("SELECT * FROM folders WHERE user_id = ? ORDER BY sort_order ASC, name ASC");
$stmt->execute(array($artist_id));
$folders = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $technique = trim($_POST['technique']);
    $price = !empty($_POST['price']) ? floatval($_POST['price']) : null;
    $currency = isset($_POST['currency']) ? $_POST['currency'] : 'RUB';
    $is_for_sale = isset($_POST['is_for_sale']) ? 1 : 0;
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
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
        $slug = trim($slug, '-');
        if (empty($slug)) {
            $slug = 'work';
        }
        
        $counter = 1;
        $original_slug = $slug;
        while (true) {
            $stmt_check = $pdo->prepare("SELECT id FROM artworks WHERE slug = ?");
            $stmt_check->execute(array($slug));
            if (!$stmt_check->fetch()) {
                break;
            }
            $slug = $original_slug . '-' . $counter;
            $counter++;
        }
        
        $target_dir = "../../assets/uploads/artworks/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $image_name = '';
        if (!empty($_FILES['image']['name'])) {
            // Транслитерируем имя файла
            $image_name = time() . '_' . transliterate(basename($_FILES["image"]["name"]));
            $target_file = $target_dir . $image_name;
            
            $check = getimagesize($_FILES["image"]["tmp_name"]);
            if ($check === false) {
                $error = "Файл не является изображением";
            } elseif (!move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
                $error = "Ошибка при загрузке файла";
            }
        } else {
            $error = "Выберите изображение";
        }
        
        if (empty($error)) {
            try {
                $pdo->beginTransaction();
                
                // Вставляем работу
                $stmt = $pdo->prepare("
                    INSERT INTO artworks (artist_id, title, slug, description, category_id,
                                        technique, price, currency, is_for_sale, image_url, tags) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute(array(
                    $artist_id,
                    $title,
                    $slug,
                    $description,
                    $category_id,
                    $technique,
                    $price,
                    $currency,
                    $is_for_sale,
                    $image_name,
                    $tags
                ));
                
                $artwork_id = $pdo->lastInsertId();
                
                // Добавляем связи с папками
                if (!empty($selected_folders)) {
                    $stmt_folder = $pdo->prepare("INSERT INTO artwork_folders (artwork_id, folder_id) VALUES (?, ?)");
                    foreach ($selected_folders as $folder_id) {
                        $stmt_folder->execute(array($artwork_id, $folder_id));
                    }
                }
                
                $pdo->commit();
                
                $_SESSION['success'] = "Работа успешно добавлена!";
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

<?php include '../../includes/header.php'; ?>

<div style="max-width: 800px; margin: 40px auto; padding: 0 20px;">
    <div style="background: white; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); padding: 30px;">
        <h1 style="margin: 0 0 5px 0; font-size: 1.8rem;">➕ Добавить новую работу</h1>
        <p style="color: #666; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 1px solid #eee;">Заполните информацию о вашем новом произведении</p>
        
        <?php if ($error): ?>
            <div style="background: #f8d7da; color: #721c24; padding: 12px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #f5c6cb;"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <form method="POST" action="" enctype="multipart/form-data">
            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 500; color: #555;">Название работы *</label>
                <input type="text" name="title" required style="width: 100%; padding: 10px 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 1rem;">
                <small style="color: #666; font-size: 0.75rem; display: block; margin-top: 5px;">По названию будет создан уникальный URL работы</small>
            </div>
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 500; color: #555;">Категория</label>
                <select name="category_id" style="width: 100%; padding: 10px 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 1rem;">
                    <option value="">-- Выберите категорию --</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= $category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 500; color: #555;">Описание</label>
                <textarea name="description" rows="5" style="width: 100%; padding: 10px 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 1rem; resize: vertical;"></textarea>
            </div>
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 500; color: #555;">Техника исполнения</label>
                <input type="text" name="technique" placeholder="например: масло, холст" style="width: 100%; padding: 10px 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 1rem;">
            </div>
            
            <div style="display: flex; gap: 20px; margin-bottom: 20px;">
                <div style="flex: 1;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 500; color: #555;">Цена</label>
                    <input type="number" name="price" min="0" step="any" style="width: 100%; padding: 10px 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 1rem;">
                </div>
                <div style="flex: 1;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 500; color: #555;">Валюта</label>
                    <select name="currency" style="width: 100%; padding: 10px 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 1rem;">
                        <option value="RUB">₽ Рубль</option>
                        <option value="USD">$ Доллар</option>
                    </select>
                </div>
            </div>
            
            <div style="margin-bottom: 20px;">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" name="is_for_sale" checked>
                    <span>Доступно для продажи</span>
                </label>
            </div>
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 500; color: #555;">Изображение работы *</label>
                <input type="file" name="image" accept="image/*" required style="width: 100%; padding: 8px;">
                <small style="color: #666; font-size: 0.75rem; display: block; margin-top: 5px;">Допустимые форматы: JPG, PNG, WebP. Максимальный размер: 10 МБ</small>
            </div>
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 500; color: #555;">Теги</label>
                <input type="text" name="tags" placeholder="например: пейзаж, море, лето, закат" style="width: 100%; padding: 10px 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 1rem;">
                <small style="color: #666; font-size: 0.75rem; display: block; margin-top: 5px;">Введите теги через запятую. По ним можно будет искать работы</small>
            </div>
            
            <!-- ВЫБОР ПАПОК (несколько) -->
            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 500; color: #555;">Папки</label>
                <div style="display: flex; flex-wrap: wrap; gap: 15px; margin-top: 10px;">
                    <?php if (empty($folders)): ?>
                        <p style="color: #666;">У вас пока нет папок. <a href="/portfolio.php?create_folder=1" style="color: #3498db;">Создать папку</a></p>
                    <?php else: ?>
                        <?php foreach ($folders as $folder): ?>
                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;">
                                <input type="checkbox" name="folder_ids[]" value="<?= $folder['id'] ?>">
                                📂 <?= htmlspecialchars($folder['name']) ?>
                            </label>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <small style="color: #666; font-size: 0.75rem; display: block; margin-top: 5px;">Выберите папки, в которые хотите добавить эту работу (можно несколько)</small>
            </div>
            
            <div style="display: flex; gap: 15px; margin-top: 25px;">
                <button type="submit" style="background: #3498db; color: white; padding: 10px 24px; border: none; border-radius: 6px; cursor: pointer; font-size: 0.95rem;">💾 Сохранить работу</button>
                <a href="index.php" style="background: #95a5a6; color: white; padding: 10px 24px; border: none; border-radius: 6px; cursor: pointer; font-size: 0.95rem; text-decoration: none; display: inline-block;">❌ Отмена</a>
            </div>
        </form>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>