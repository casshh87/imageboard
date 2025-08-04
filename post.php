<?php
// Обработка отправки поста
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Неверный CSRF-токен");
    }

    // Проверка временного ограничения (30 секунд)
    if (isset($_SESSION['last_post_time'])) {
        $remainingTime = 30 - (time() - $_SESSION['last_post_time']);
        if ($remainingTime > 0) {
            $errorMessage = 'Подождите перед отправкой нового сообщения';
        }
    }

    if (empty($errorMessage)) {
        $_SESSION['last_post_time'] = time();
        
        $name = $_POST['name'] ?? 'Аноним';
        $text = $_POST['text'] ?? '';
        $image = $_FILES['image'] ?? null;
        $imagePath = null;

        // Загрузка изображения
        if ($image && $image['error'] === UPLOAD_ERR_OK) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $image['tmp_name']);
            
            if (!in_array($mime, $allowedTypes)) {
                $errorMessage = "Разрешены только изображения JPEG, PNG и GIF";
            } else {
                $ext = strtolower(pathinfo($image['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                    $errorMessage = "Недопустимое расширение файла";
                } elseif ($image['size'] > 5 * 1024 * 1024) {
                    $errorMessage = "Файл слишком большой (максимум 5MB)";
                } else {
                    $imagePath = bin2hex(random_bytes(16)) . '.' . $ext;
                    $uploadPath = __DIR__ . '/uploads/' . $imagePath;
                    move_uploaded_file($image['tmp_name'], $uploadPath);
                }
            }
        }

        if (empty($errorMessage)) {
            $stmt = $db->prepare("INSERT INTO posts (name, text, image) VALUES (?, ?, ?)");
            $stmt->execute([$name, $text, $imagePath]);
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
    }
}