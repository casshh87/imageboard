<?php
include 'db.php';

session_start();
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errorMessage = '';
$remainingTime = 0;

include 'post.php';

// Получение всех постов
$statement = $db->query("SELECT * FROM posts ORDER BY id DESC");
$posts = $statement->fetchAll();

// Получение файлов к каждому посту
foreach ($posts as &$post) {
    $stmt = $db->prepare("SELECT file_name FROM post_files WHERE post_id = ?");
    $stmt->execute([$post['id']]);
    $post['files'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Червач</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    

    <!-- Форма -->
<div class="form-wrapper">
    <form method="post" enctype="multipart/form-data" class="message-form">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

        <?php if (!empty($errorMessage)): ?>
            <div class="timer-message" id="timer-message" data-seconds="<?= $remainingTime ?>">
                <?= htmlspecialchars($errorMessage) ?> 
                <?php if ($remainingTime > 0): ?> (Осталось <span id="timer"><?= $remainingTime ?></span> сек.) <?php endif; ?>
            </div>
        <?php endif; ?>

        <input type="text" name="name" placeholder="Имя (опционально)"><br><br>
        <textarea name="text" placeholder="Текст сообщения"></textarea><br><br>
        <input type="file" name="media[]" accept="image/*,video/*" multiple onchange="if(this.files.length > 5){ alert('Можно выбрать не более 5 файлов'); this.value = ''; }">

        <button type="submit">Отправить</button>
    </form>
</div>

<!-- Список постов -->
    <?php foreach ($posts as $post): ?>
        <div class="post">
            <b><?= htmlspecialchars($post['name']) ?></b>
            <small style="color:gray"><?= date('d.m.Y H:i', strtotime($post['created_at'])) ?></small>
            <p><?= nl2br(htmlspecialchars($post['text'])) ?></p>

            <?php if (!empty($post['files'])): ?>
                <?php foreach ($post['files'] as $file): ?>
                    <?php
                        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                        $isVideo = in_array($ext, ['mp4', 'webm', 'ogg']);
                    ?>
                    <?php if ($isVideo): ?>
                        <video controls width="300">
                            <source src="uploads/<?= htmlspecialchars($file) ?>" type="video/<?= $ext ?>">
                            Ваш браузер не поддерживает воспроизведение видео.
                        </video>
                    <?php else: ?>
                        <img src="uploads/<?= htmlspecialchars($file) ?>" 
                             alt="image" 
                             onclick="openModal(this)"
                             data-post-id="<?= $post['id'] ?>">
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

     <!-- Модальное окно -->
  <div id="imageModal" class="modal" onclick="closeModal()">
    <img id="modalImage" class="modal-content" onclick="event.stopPropagation(); closeModal();">
  </div>
  <script src="script.js"></script>
</body>
</html>