<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'db.php';

session_start();
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errorMessage = '';
$remainingTime = 0;

include 'post.php';

// Получение всех постов
$posts = $db->query("SELECT * FROM posts ORDER BY id DESC")->fetchAll();
$statement = $db->query("SELECT * FROM posts ORDER BY id DESC");
if (!$statement) {
    die("Ошибка запроса SELECT");
}
$posts = $statement->fetchAll();

?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Червач</title>
    <style>
        body { font-family: Arial; max-width: 800px; margin: 0 auto; }
        .post { border: 1px solid #ddd; padding: 10px; margin: 10px 0; }
        img { max-width: 200px; }
        .timer-message {
            color: #d32f2f;
            background: #ffebee;
            padding: 8px 12px;
            border-radius: 4px;
            margin-bottom: 12px;
            border-left: 3px solid #f44336;
        }
    </style>
</head>
<body>
    <h1>Червач</h1>
    
    <!-- Форма отправки -->
    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        
        <?php if (!empty($errorMessage)): ?>
            <div class="timer-message" id="timer-message" data-seconds="<?= $remainingTime ?>">
                <?= htmlspecialchars($errorMessage) ?> (<span id="timer"><?= $remainingTime ?></span> сек.)
            </div>
        <?php endif; ?>
        
        <input type="text" name="name" placeholder="Имя (опционально)"><br>
        <textarea name="text" placeholder="Текст" required></textarea><br>
        <input type="file" name="image" accept="image/*"><br>
        <button type="submit">Отправить</button>
    </form>

    <!-- Список постов -->
    <?php foreach ($posts as $post): ?>
        <div class="post">
            <b><?= htmlspecialchars($post['name']) ?></b>
            <small style="color:gray"><?= date('d.m.Y H:i', strtotime($post['created_at'])) ?></small>
            <p><?= nl2br(htmlspecialchars($post['text'])) ?></p>
            <?php if ($post['image']): ?>
                <img src="uploads/<?= htmlspecialchars($post['image']) ?>" alt="Image">
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

    <script>
    src="./script.js"
        
    </script>
</body>
</html>