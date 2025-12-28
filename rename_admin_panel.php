<?php
session_start();

// Проверка авторизации администратора
if (!isset($_SESSION['is_admin'])) {
    if ($_POST['password'] ?? '' === '') {
        $_SESSION['is_admin'] = true;
        header('Location: rename_admin_panel.php');
        exit;
    }
    ?>
<!DOCTYPE html>
<html>
<head>
    <title>Мой сайт</title>
    <style>
    body {
        font-family: sans-serif;
        margin: 0;
        padding: 0;
        background: #f5f5f5;
        color: #333;
    }

    .login-container, .admin-container {
        max-width: 100%;
        margin: 0 auto;
        padding: 10px;
    }

    .login-form {
        display: flex;
        gap: 5px;
    }

    .login-form input, 
    .login-form button {
        flex: 1;
        padding: 8px;
        font-size: 14px;
    }

  
</style>

</head>
<body>
<div class="login-container">
    <form method="post" class="login-form">
        <input type="password" name="password" placeholder="Пароль администратора">
        <button type="submit">Войти</button>
    </form>
</div>
</body>
</html>
<?php
    exit;
}

// Дальнейший код для авторизованных пользователей
require 'db.php';

// Удаление поста
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    $db->prepare("DELETE FROM post_files WHERE post_id = ?")->execute([$id]);
    $db->prepare("DELETE FROM posts WHERE id = ?")->execute([$id]);
    header("Location: rename_admin_panel.php");
    exit;
}

// Бан пользователя и удаление всех его сообщений
if (isset($_GET['ban'])) {
    $ip = $_GET['ban'];
    
    // Сначала получаем все ID постов этого пользователя
    $stmt = $db->prepare("SELECT id FROM posts WHERE ip_address = ?");
    $stmt->execute([$ip]);
    $postsToDelete = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Удаляем все файлы, связанные с этими постами
    if (!empty($postsToDelete)) {
        $placeholders = rtrim(str_repeat('?,', count($postsToDelete)), ',');
        $db->prepare("DELETE FROM post_files WHERE post_id IN ($placeholders)")->execute($postsToDelete);
    }
    
    // Удаляем все посты пользователя
    $db->prepare("DELETE FROM posts WHERE ip_address = ?")->execute([$ip]);
    
    // Добавляем IP в бан лист
    $stmt = $db->prepare("INSERT IGNORE INTO banned_ips (ip_address) VALUES (?)");
    $stmt->execute([$ip]);
    
    header("Location: rehbwfufkcner.php");
    exit;
}

// Получение последних 500 постов (ВНЕ блока ban!)
$posts = $db->query("SELECT * FROM posts ORDER BY id DESC LIMIT 500")->fetchAll();
?>


<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админка</title>
    <style>
<style> body { font-family: sans-serif; margin: 0; padding: 0; background: #f5f5f5; color: #333; } .login-container, .admin-container { max-width: 100%; margin: 0 auto; padding: 10px; } .login-form { display: flex; gap: 5px; } .login-form input, .login-form button { flex: 1; padding: 8px; font-size: 14px; } h2 { font-size: 18px; margin: 10px 0; text-align: center; } .table-container { overflow-x: auto; } table { width: 100%; border-collapse: collapse; font-size: 13px; } th, td { border: 1px solid #ddd; padding: 6px; text-align: left; vertical-align: top; max-width: 120px; /* ограничение ширины */ overflow: hidden; text-overflow: ellipsis; white-space: nowrap; } th { background: #eee; } .actions { white-space: nowrap; } .actions a { color: #007bff; text-decoration: none; font-size: 13px; } .actions a:hover { text-decoration: underline; } .separator { margin: 0 3px; color: #aaa; } @media (max-width: 480px) { table, th, td { font-size: 12px; padding: 4px; } h2 { font-size: 16px; } } </style>
    </style>
</head>
<body>
    <div class="admin-container">
        <h2>Админка</h2>
        <div class="table-container">
            <table>
                <tr>
                    <th>ID</th>
                    <th>Текст</th>
                    <th>IP</th>
                    <th>Действия</th>
                </tr>
                <?php foreach ($posts as $post): ?>
                <tr>
                    <td><?= $post['id'] ?></td>
                    <td class="text-cell" title="<?= htmlspecialchars($post['text']) ?>">
                        <?= htmlspecialchars($post['text']) ?>
                    </td>
                    <td title="<?= $post['ip_address'] ?>"><?= $post['ip_address'] ?></td>
                    <td class="actions">
                        <a href="?delete=<?= $post['id'] ?>" onclick="return confirm('Удалить пост?')">Удалить</a>
                        <span class="separator">|</span>
                        <a href="?ban=<?= $post['ip_address'] ?>" onclick="return confirm('Забанить IP?')">Бан</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>
</body>
</html>