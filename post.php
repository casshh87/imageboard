<?php

require 'db.php';

// Функция получения IP пользователя с учётом прокси
function getUserIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ipList = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ipList[0]);
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

$userIp = getUserIP();


// Проверка на бан
$stmt = $db->prepare("SELECT COUNT(*) FROM banned_ips WHERE ip_adress = ?");
$stmt->execute([$userIp]);
if ($stmt->fetchColumn() > 0) {
    die("Вы забанены.");
}

// Ограничение по времени между постами (30 сек)

if (isset($_SESSION['last_post_time'])) {
    $elapsedTime = time() - $_SESSION['last_post_time'];
    if ($elapsedTime < 5) {
        $_SESSION['error'] = 'Подождите ' . (5 - $elapsedTime) . ' секунд перед отправкой нового сообщения';
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Получение и базовая очистка данных
$userName = ($_POST['name'] ?? 'аноним');

function sanitizeName(string $input): string {
    $name = strip_tags($input);
    $name = htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    return trim($name);
}


$nameMaxLength = 25;

if (mb_strlen($userName) > $nameMaxLength) {
    $_SESSION['error'] = "Имя не должно превышать $nameMaxLength символов.";
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

$text = trim($_POST['text'] ?? '');

// Функция безопасной очистки текста (чтобы убрать опасные теги и JS)
function sanitizeText(string $input): string {
    // Удаляем теги, кроме базовых, например <b>, <i>, <u> можно разрешить, если хочешь
    $allowedTags = '<b><i><u><br><p><a><ul><ol><li><strong><em>';
    $text = strip_tags($input, $allowedTags);

    // Удаляем javascript: и обработчики событий (onclick и т.п.)
    $text = preg_replace('#(<[^>]+?[\x00-\x20"\'])(on|src|href|style)[^>]*>#i', '>', $text);
    $text = preg_replace('#javascript:#i', '', $text);
    return trim($text);
}

$text = sanitizeText($text);
// Проверка длины текста

$maxLength = 14989; // максимальная длина



if (mb_strlen($text) > $maxLength) {
    $_SESSION['error'] = "Сообщение не должно превышать $maxLength символов.";
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Проверка что есть текст или файлы
$files = $_FILES['media'] ?? null;
$hasValidFile = false;
$filePaths = [];

if ($files && isset($files['error']) && is_array($files['error'])) {
    foreach ($files['error'] as $err) {
        if ($err === UPLOAD_ERR_OK) {
            $hasValidFile = true;
            break;
        }
    }
}

if (empty($text) && !$hasValidFile) {
    $_SESSION['error'] = "Введите сообщение или прикрепите файл.";
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Проверка количества файлов
$validFileCount = 0;
if ($files) {
    foreach ($files['error'] as $err) {
        if ($err === UPLOAD_ERR_OK) {
            $validFileCount++;
        }
    }
    if ($validFileCount > 6) {
        $_SESSION['error'] = "Вы можете загрузить не более 6 файлов.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Загрузка файлов
if ($hasValidFile) {
    $allowedImageTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
   $allowedVideoTypes = [
    'video/mp4', 'application/mp4', 'video/quicktime',
    'video/webm', 'application/octet-stream',
    'video/ogg',
    'video/x-msvideo', 'video/avi',
    'video/x-matroska', // Основной MIME для MKV
    'application/x-matroska', // Альтернативный MIME
    'video/mkv' // Еще один вариант
];
    $allowedExtensions = ['jpg','jpeg','png','gif','webp','mp4','webm','ogg','avi','mkv']; 

    $allowedTypes = array_merge($allowedImageTypes, $allowedVideoTypes);

    $uploadDir = __DIR__ . '/uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);

    for ($i = 0; $i < count($files['name']); $i++) {
        if ($files['error'][$i] === UPLOAD_ERR_OK) {
            $tmpName = $files['tmp_name'][$i];
            $origName = $files['name'][$i];
            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            $type = finfo_file($finfo, $tmpName);

            // Проверка расширения
            if (!in_array($ext, $allowedExtensions)) {
                $_SESSION['error'] = "Недопустимое расширение файла: .$ext ($origName)";
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
            }

            // Проверка MIME-типа
            if (!in_array($type, $allowedTypes)) {
                $_SESSION['error'] = "Неверный тип файла (MIME: $type) — $origName";
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
            }

            // Ограничение размера (100 МБ)
            if ($files['size'][$i] > 100 * 1024 * 1024) {
                $_SESSION['error'] = "Файл слишком большой: $origName";
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
            }

            // Генерация нового имени
            $newName = bin2hex(random_bytes(16)) . '.' . $ext;
            if (move_uploaded_file($tmpName, $uploadDir . $newName)) {
                $filePaths[] = $newName;
            }
        }
    }

    finfo_close($finfo);
}


// Сохранение в БД
if (empty($_SESSION['error'])) {
    $stmt = $db->prepare("INSERT INTO posts (name, text, ip_address) VALUES (?, ?, ?)");
    $stmt->execute([$userName, $text, $userIp]);

    $postId = $db->lastInsertId();

    if (!empty($filePaths)) {
        $stmtFiles = $db->prepare("INSERT INTO post_files (post_id, file_name) VALUES (?, ?)");
        foreach ($filePaths as $file) {
            $stmtFiles->execute([$postId, $file]);
        }
    }

    $_SESSION['last_post_time'] = time();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}
