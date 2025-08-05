
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
    
            $userName = $_POST['name'] ?? 'Аноним';
            $text = trim($_POST['text'] ?? '');
            $files = $_FILES['media'] ?? null;
            $filePaths = [];
    
            // Проверка наличия текста или файлов
            $hasValidFile = false;
            if ($files && isset($files['error']) && is_array($files['error'])) {
                foreach ($files['error'] as $err) {
                    if ($err === UPLOAD_ERR_OK) {
                        $hasValidFile = true;
                        break;
                    }
                }
            }
    
            if (empty($text) && !$hasValidFile) {
                $errorMessage = "Введите сообщение или прикрепите файл.";
            }
    
            // Проверка количества файлов
$validFileCount = 0;
foreach ($files['error'] as $err) {
    if ($err === UPLOAD_ERR_OK) {
        $validFileCount++;
    }
}

if ($validFileCount > 5) {
    $errorMessage = "Вы можете загрузить не более 5 файлов.";
}
            // Загрузка файлов
            if (empty($errorMessage) && $hasValidFile) {
                $allowedImageTypes = ['image/jpeg', 'image/png', 'image/gif'];
                $allowedVideoTypes = ['video/mp4', 'video/webm', 'video/ogg'];
                $allowedTypes = array_merge($allowedImageTypes, $allowedVideoTypes);
    
                for ($i = 0; $i < count($files['name']); $i++) {
                    if ($files['error'][$i] === UPLOAD_ERR_OK) {
                        $tmpName = $files['tmp_name'][$i];
                        $origName = $files['name'][$i];
                        $type = finfo_file(finfo_open(FILEINFO_MIME_TYPE), $tmpName);
                        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    
                        if (!in_array($type, $allowedTypes)) {
                            $errorMessage = "Неверный тип файла: $origName";
                            break;
                        }
    
                        if ($files['size'][$i] > 20 * 1024 * 1024) {
                            $errorMessage = "Файл слишком большой: $origName";
                            break;
                        }
    
                        $newName = bin2hex(random_bytes(16)) . '.' . $ext;
                        move_uploaded_file($tmpName, __DIR__ . '/uploads/' . $newName);
                        $filePaths[] = $newName;
                    }
                }
            }
    
            // Сохранение в БД
            if (empty($errorMessage)) {
                $stmt = $db->prepare("INSERT INTO posts (name, text) VALUES (?, ?)");
                $stmt->execute([$userName, $text]);
                $postId = $db->lastInsertId();
    
                if (!empty($filePaths)) {
                    $stmt = $db->prepare("INSERT INTO post_files (post_id, file_name) VALUES (?, ?)");
                    foreach ($filePaths as $file) {
                        $stmt->execute([$postId, $file]);
                    }
                }
    
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
            }
        }
    }