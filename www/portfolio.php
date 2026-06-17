<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'includes/config.php';
require_once 'includes/functions.php';

// Проверяем, что пользователь авторизован и является художником
if (!isLoggedIn() || getUserRole() != 'artist') {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Обработка создания папки
$folder_error = '';
$folder_success = '';
$show_create_form = isset($_GET['create_folder']) ? true : false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_folder'])) {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    
    if (empty($name)) {
        $folder_error = 'Введите название папки';
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM folders WHERE user_id = ?");
        $stmt->execute(array($user_id));
        $folder_count = $stmt->fetchColumn();
        
        if ($folder_count >= 5) {
            $folder_error = 'Вы можете создать не более 5 папок';
        } else {
            $stmt = $pdo->prepare("INSERT INTO folders (user_id, name, description, sort_order) VALUES (?, ?, ?, ?)");
            if ($stmt->execute(array($user_id, $name, $description, 0))) {
                $folder_success = 'Папка создана!';
                $show_create_form = false;
                header('Location: portfolio.php');
                exit;
            } else {
                $folder_error = 'Ошибка при создании папки';
            }
        }
    }
}

// Обработка удаления папки
if (isset($_GET['delete_folder'])) {
    $folder_id = intval($_GET['delete_folder']);
    
    $stmt = $pdo->prepare("DELETE FROM artwork_folders WHERE folder_id = ?");
    $stmt->execute(array($folder_id));
    
    $stmt = $pdo->prepare("DELETE FROM folders WHERE id = ? AND user_id = ?");
    $stmt->execute(array($folder_id, $user_id));
    
    header('Location: portfolio.php');
    exit;
}

// Получаем папки художника
$stmt = $pdo->prepare("SELECT * FROM folders WHERE user_id = ? ORDER BY sort_order ASC, name ASC");
$stmt->execute(array($user_id));
$artist_folders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Для каждой папки получаем работы (уникальные, без дублей)
foreach ($artist_folders as $key => $folder) {
    $stmt = $pdo->prepare("
        SELECT DISTINCT a.*, c.name as category_name
        FROM artworks a
        LEFT JOIN categories c ON a.category_id = c.id
        JOIN artwork_folders af ON a.id = af.artwork_id
        WHERE af.folder_id = ? AND a.artist_id = ?
        ORDER BY a.created_at DESC
    ");
    $stmt->execute(array($folder['id'], $user_id));
    $artist_folders[$key]['works'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Получаем работы без папки
$stmt = $pdo->prepare("
    SELECT a.*, c.name as category_name
    FROM artworks a
    LEFT JOIN categories c ON a.category_id = c.id
    WHERE a.artist_id = ? AND NOT EXISTS (
        SELECT 1 FROM artwork_folders af WHERE af.artwork_id = a.id
    )
    ORDER BY a.created_at DESC
");
$stmt->execute(array($user_id));
$works_without_folder = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Выбранная папка для фильтрации
$selected_folder = isset($_GET['folder']) ? (int)$_GET['folder'] : 0;
$filtered_works = array();

if ($selected_folder > 0) {
    foreach ($artist_folders as $folder) {
        if ($folder['id'] == $selected_folder) {
            $filtered_works = $folder['works'];
            break;
        }
    }
} else {
    // Собираем все работы (уникальные, без дублей)
    $all_works = array();
    foreach ($artist_folders as $folder) {
        foreach ($folder['works'] as $work) {
            $all_works[$work['id']] = $work;
        }
    }
    foreach ($works_without_folder as $work) {
        $all_works[$work['id']] = $work;
    }
    $filtered_works = array_values($all_works);
}
?>

<?php include 'includes/header.php'; ?>

<style>
.portfolio-container {
    max-width: 1200px;
    margin: 40px auto;
    padding: 20px;
}
.portfolio-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 30px;
    border-radius: 8px;
    margin-bottom: 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
}
.portfolio-header h1 {
    margin: 0 0 5px;
    font-size: 2.5em;
}
.portfolio-header p {
    margin: 0;
    opacity: 0.9;
}
.btn-add-work-main {
    background: white;
    color: #667eea;
    padding: 10px 24px;
    border-radius: 30px;
    text-decoration: none;
    font-weight: bold;
    transition: all 0.3s;
}
.btn-add-work-main:hover {
    background: rgba(255,255,255,0.9);
    transform: scale(1.02);
}
.folders-nav {
    margin: 30px 0 20px;
    padding: 0;
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
    border-bottom: 1px solid #eee;
    padding-bottom: 15px;
}
.folder-tab {
    display: inline-block;
    padding: 8px 20px;
    background: #f0f0f0;
    color: #666;
    text-decoration: none;
    border-radius: 30px;
    transition: all 0.2s;
    font-size: 0.9rem;
}
.folder-tab:hover {
    background: #e0e0e0;
}
.folder-tab.active {
    background: #3498db;
    color: white;
}
.create-folder-btn {
    background: #28a745;
    color: white;
}
.create-folder-btn:hover {
    background: #218838;
}
.delete-folder-btn {
    background: #dc3545;
    color: white;
    font-size: 0.7rem;
    padding: 4px 8px;
    margin-left: 8px;
    border-radius: 20px;
    text-decoration: none;
}
.delete-folder-btn:hover {
    background: #c82333;
}
.create-folder-form {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
}
.create-folder-form input,
.create-folder-form textarea {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    margin-bottom: 10px;
}
.form-actions {
    display: flex;
    gap: 10px;
}
.gallery-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
}
.gallery-item {
    background: white;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: transform 0.3s;
}
.gallery-item:hover {
    transform: translateY(-5px);
}
.gallery-item img {
    width: 100%;
    height: 200px;
    object-fit: cover;
}
.gallery-info {
    padding: 15px;
}
.gallery-info h3 {
    margin: 0 0 5px;
    font-size: 1.1rem;
}
.price {
    font-weight: bold;
    color: #2c3e50;
    margin-top: 8px;
}
.empty-message {
    text-align: center;
    padding: 60px;
    background: #f8f9fa;
    border-radius: 8px;
    color: #666;
}
.btn {
    display: inline-block;
    padding: 10px 20px;
    background: #3498db;
    color: white;
    text-decoration: none;
    border-radius: 4px;
    border: none;
    cursor: pointer;
}
.btn-secondary {
    background: #6c757d;
}
.card-actions {
    margin-top: 10px;
    display: flex;
    gap: 15px;
}
.btn-edit {
    color: #666;
    text-decoration: none;
    font-size: 0.8rem;
    background: none;
    border: none;
    padding: 0;
}
.btn-edit:hover {
    text-decoration: underline;
    color: #333;
}
.btn-delete {
    color: #999;
    text-decoration: none;
    font-size: 0.8rem;
    background: none;
    border: none;
    padding: 0;
}
.btn-delete:hover {
    text-decoration: underline;
    color: #dc3545;
}
.alert {
    padding: 12px;
    margin-bottom: 20px;
    border-radius: 6px;
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
@media (max-width: 768px) {
    .gallery-grid {
        grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
    }
    .portfolio-header {
        flex-direction: column;
        text-align: center;
    }
}
</style>

<div class="portfolio-container">
    <div class="portfolio-header">
        <div>
            <h1>🎨 Мое портфолио</h1>
            <p>Здесь собраны все ваши работы</p>
        </div>
        <a href="/admin/artworks/create.php" class="btn-add-work-main">➕ Добавить работу</a>
    </div>
    
    <!-- Навигация по папкам -->
    <div class="folders-nav">
        <a href="portfolio.php" class="folder-tab <?= $selected_folder == 0 ? 'active' : '' ?>">
            📁 Все работы
        </a>
        <?php foreach ($artist_folders as $folder): ?>
            <div style="display: inline-flex; align-items: center;">
                <a href="?folder=<?= $folder['id'] ?>" class="folder-tab <?= $selected_folder == $folder['id'] ? 'active' : '' ?>">
                    📂 <?= htmlspecialchars($folder['name']) ?>
                    <span style="font-size: 0.7rem;">(<?= count($folder['works']) ?>)</span>
                </a>
                <a href="?delete_folder=<?= $folder['id'] ?>" class="delete-folder-btn" onclick="return confirm('Удалить папку? Работы не удалятся')">✕</a>
            </div>
        <?php endforeach; ?>
        
        <?php if (count($artist_folders) < 5 && !$show_create_form): ?>
            <a href="?create_folder=1" class="folder-tab create-folder-btn">➕ Создать папку</a>
        <?php endif; ?>
    </div>
    
    <!-- Форма создания папки -->
    <?php if ($show_create_form && count($artist_folders) < 5): ?>
        <div class="create-folder-form">
            <h4>📁 Создать новую папку</h4>
            <?php if ($folder_error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($folder_error) ?></div>
            <?php endif; ?>
            <?php if ($folder_success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($folder_success) ?></div>
            <?php endif; ?>
            <form method="POST" action="?create_folder=1">
                <input type="text" name="name" placeholder="Название папки" required>
                <textarea name="description" rows="2" placeholder="Описание (необязательно)"></textarea>
                <div class="form-actions">
                    <button type="submit" name="create_folder" class="btn">Создать</button>
                    <a href="portfolio.php" class="btn btn-secondary">Отмена</a>
                </div>
            </form>
        </div>
    <?php endif; ?>
    
    <!-- Галерея работ -->
    <?php if (empty($filtered_works)): ?>
        <div class="empty-message">
            <p>😔 В этой папке пока нет работ</p>
            <p><a href="/admin/artworks/create.php" class="btn" style="margin-top: 10px;">➕ Добавить работу</a></p>
        </div>
    <?php else: ?>
        <div class="gallery-grid">
            <?php foreach ($filtered_works as $work): ?>
                <div class="gallery-item">
                    <a href="artwork.php?id=<?= $work['id'] ?>" style="text-decoration: none; color: inherit;">
                        <img src="assets/uploads/artworks/<?= htmlspecialchars($work['image_url']) ?>" 
                             alt="<?= htmlspecialchars($work['title']) ?>">
                        <div class="gallery-info">
                            <h3><?= htmlspecialchars($work['title']) ?></h3>
                            <?php if ($work['category_name']): ?>
                                <p><?= htmlspecialchars($work['category_name']) ?></p>
                            <?php endif; ?>
                            <?php if ($work['price'] && $work['is_for_sale'] && !$work['is_sold']): ?>
                                <p class="price"><?= number_format($work['price'], 0, '', ' ') ?> <?= $work['currency'] == 'USD' ? '$' : '₽' ?></p>
                            <?php elseif ($work['is_sold']): ?>
                                <p class="price" style="text-decoration: line-through; color: #999;"><?= number_format($work['price'], 0, '', ' ') ?> <?= $work['currency'] == 'USD' ? '$' : '₽' ?> <span style="background:#dc3545; color:white; padding:2px 6px; border-radius:4px; font-size:0.7rem;">Продано</span></p>
                            <?php endif; ?>
                            <div class="card-actions">
                                <a href="/admin/artworks/edit.php?id=<?= $work['id'] ?>" class="btn-edit">✏️ Редактировать</a>
                                <a href="/admin/artworks/delete.php?id=<?= $work['id'] ?>" class="btn-delete" onclick="return confirm('Удалить работу?')">🗑️ Удалить</a>
                            </div>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>