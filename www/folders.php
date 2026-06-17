<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

if (!isLoggedIn() || getUserRole() != 'artist') {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Создание папки
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_folder'])) {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $sort_order = intval($_POST['sort_order']);
    
    if (empty($name)) {
        $error = 'Введите название папки';
    } else {
        // Проверяем, сколько уже папок (максимум 5)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM folders WHERE user_id = ?");
        $stmt->execute(array($user_id));
        $folder_count = $stmt->fetchColumn();
        
        if ($folder_count >= 5) {
            $error = 'Вы можете создать не более 5 папок';
        } else {
            $stmt = $pdo->prepare("INSERT INTO folders (user_id, name, description, sort_order) VALUES (?, ?, ?, ?)");
            if ($stmt->execute(array($user_id, $name, $description, $sort_order))) {
                $success = 'Папка создана!';
            } else {
                $error = 'Ошибка при создании папки';
            }
        }
    }
}

// Редактирование папки
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_folder'])) {
    $folder_id = intval($_POST['folder_id']);
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $sort_order = intval($_POST['sort_order']);
    
    $stmt = $pdo->prepare("UPDATE folders SET name = ?, description = ?, sort_order = ? WHERE id = ? AND user_id = ?");
    if ($stmt->execute(array($name, $description, $sort_order, $folder_id, $user_id))) {
        $success = 'Папка обновлена!';
    } else {
        $error = 'Ошибка при обновлении';
    }
}

// Удаление папки
if (isset($_GET['delete_folder'])) {
    $folder_id = intval($_GET['delete_folder']);
    
    // Сначала убираем привязку работ к этой папке
    $stmt = $pdo->prepare("UPDATE artworks SET folder_id = NULL WHERE folder_id = ? AND artist_id = ?");
    $stmt->execute(array($folder_id, $user_id));
    
    // Затем удаляем папку
    $stmt = $pdo->prepare("DELETE FROM folders WHERE id = ? AND user_id = ?");
    if ($stmt->execute(array($folder_id, $user_id))) {
        $success = 'Папка удалена';
    } else {
        $error = 'Ошибка при удалении';
    }
}

// Получаем все папки пользователя
$stmt = $pdo->prepare("SELECT * FROM folders WHERE user_id = ? ORDER BY sort_order ASC, created_at ASC");
$stmt->execute(array($user_id));
$folders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php include 'includes/header.php'; ?>

<style>
.folders-container {
    max-width: 1200px;
    margin: 40px auto;
    padding: 20px;
}
.folders-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    flex-wrap: wrap;
    gap: 15px;
}
.folders-header h1 {
    margin: 0;
    font-size: 1.8rem;
}
.folders-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 40px;
}
.folder-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    overflow: hidden;
}
.folder-header {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    padding: 15px;
}
.folder-header h3 {
    margin: 0;
    font-size: 1.2rem;
}
.folder-body {
    padding: 15px;
}
.folder-name {
    font-size: 1.1rem;
    font-weight: bold;
    margin-bottom: 5px;
}
.folder-description {
    color: #666;
    font-size: 0.9rem;
    margin-bottom: 10px;
}
.folder-actions {
    display: flex;
    gap: 10px;
    margin-top: 15px;
}
.btn {
    padding: 8px 16px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    text-decoration: none;
    font-size: 0.9rem;
}
.btn-primary {
    background: #3498db;
    color: white;
}
.btn-primary:hover {
    background: #2980b9;
}
.btn-danger {
    background: #e74c3c;
    color: white;
}
.btn-danger:hover {
    background: #c0392b;
}
.btn-secondary {
    background: #95a5a6;
    color: white;
}
.form-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
    justify-content: center;
    align-items: center;
}
.form-modal.active {
    display: flex;
}
.modal-content {
    background: white;
    padding: 25px;
    border-radius: 12px;
    max-width: 500px;
    width: 90%;
}
.modal-content h3 {
    margin: 0 0 20px 0;
}
.modal-content .form-group {
    margin-bottom: 15px;
}
.modal-content label {
    display: block;
    margin-bottom: 5px;
    font-weight: bold;
}
.modal-content input,
.modal-content textarea {
    width: 100%;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
}
.modal-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    margin-top: 20px;
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
.empty-message {
    text-align: center;
    padding: 40px;
    background: #f8f9fa;
    border-radius: 8px;
    color: #666;
}
.limit-info {
    background: #e8f4fd;
    padding: 10px;
    border-radius: 6px;
    margin-bottom: 20px;
    font-size: 0.9rem;
}
</style>

<div class="folders-container">
    <div class="folders-header">
        <h1>📁 Управление папками</h1>
        <button onclick="openCreateModal()" class="btn btn-primary">➕ Создать папку</button>
    </div>
    
    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    
    <div class="limit-info">
        ⚡ У вас <?php echo count($folders); ?> из 5 папок. Максимум можно создать 5 папок.
    </div>
    
    <?php if (empty($folders)): ?>
        <div class="empty-message">
            <p>😔 У вас пока нет папок</p>
            <p>Создайте папки, чтобы организовать свои работы по категориям</p>
        </div>
    <?php else: ?>
        <div class="folders-grid">
            <?php foreach ($folders as $folder): ?>
                <div class="folder-card">
                    <div class="folder-header">
                        <h3><?php echo htmlspecialchars($folder['name']); ?></h3>
                    </div>
                    <div class="folder-body">
                        <div class="folder-name"><?php echo htmlspecialchars($folder['name']); ?></div>
                        <?php if ($folder['description']): ?>
                            <div class="folder-description"><?php echo nl2br(htmlspecialchars($folder['description'])); ?></div>
                        <?php endif; ?>
                        <div class="folder-actions">
                            <button onclick="openEditModal(<?php echo $folder['id']; ?>, '<?php echo addslashes($folder['name']); ?>', '<?php echo addslashes($folder['description']); ?>', <?php echo $folder['sort_order']; ?>)" class="btn btn-primary">✏️ Ред.</button>
                            <a href="?delete_folder=<?php echo $folder['id']; ?>" class="btn btn-danger" onclick="return confirm('Удалить папку? Работы не удалятся, но потеряют привязку')">🗑️ Уд.</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Модальное окно создания/редактирования папки -->
<div id="folder-modal" class="form-modal">
    <div class="modal-content">
        <h3 id="modal-title">Создать папку</h3>
        <form method="POST" action="">
            <input type="hidden" id="folder_id" name="folder_id" value="">
            <div class="form-group">
                <label for="name">Название папки *</label>
                <input type="text" id="name" name="name" required>
            </div>
            <div class="form-group">
                <label for="description">Описание</label>
                <textarea id="description" name="description" rows="3"></textarea>
            </div>
            <div class="form-group">
                <label for="sort_order">Порядок сортировки</label>
                <input type="number" id="sort_order" name="sort_order" value="0">
            </div>
            <div class="modal-actions">
                <button type="submit" id="submit-btn" name="create_folder" class="btn btn-primary">Создать</button>
                <button type="button" onclick="closeModal()" class="btn btn-secondary">Отмена</button>
            </div>
        </form>
    </div>
</div>

<script>
function openCreateModal() {
    document.getElementById('modal-title').innerText = 'Создать папку';
    document.getElementById('folder_id').value = '';
    document.getElementById('name').value = '';
    document.getElementById('description').value = '';
    document.getElementById('sort_order').value = '0';
    document.getElementById('submit-btn').name = 'create_folder';
    document.getElementById('folder-modal').classList.add('active');
}

function openEditModal(id, name, description, sort_order) {
    document.getElementById('modal-title').innerText = 'Редактировать папку';
    document.getElementById('folder_id').value = id;
    document.getElementById('name').value = name;
    document.getElementById('description').value = description;
    document.getElementById('sort_order').value = sort_order;
    document.getElementById('submit-btn').name = 'edit_folder';
    document.getElementById('folder-modal').classList.add('active');
}

function closeModal() {
    document.getElementById('folder-modal').classList.remove('active');
}

// Закрытие по клику вне модального окна
document.getElementById('folder-modal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal();
    }
});
</script>

<?php include 'includes/footer.php'; ?>