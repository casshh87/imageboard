<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

include 'db.php';

function formatQuotedText($text) {
    // Сначала экранируем HTML
    $text = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    
    // Затем обрабатываем BB-коды
    $text = preg_replace('/\[b\](.*?)\[\/b\]/s', '<b>$1</b>', $text);
    $text = preg_replace('/\[i\](.*?)\[\/i\]/s', '<i>$1</i>', $text);
    $text = preg_replace('/\[spoiler\](.*?)\[\/spoiler\]/s', '<span class="spoiler hidden">$1</span>', $text);
    
    // Обработка ссылок на посты
    $text = preg_replace(
        '/&gt;&gt;(\d+)/',
        '<a href="#post-$1" class="post-link" data-post-id="$1">&gt;&gt;$1</a>',
        $text
    );

    // Обработка цитат (должна быть после экранирования)
    $text = preg_replace('/^(&gt;)(.*)$/m', '<span class="quote">$1$2</span>', $text);
    
    // Преобразование переносов строк
    $text = nl2br($text);

    return $text;
}

$lastId = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;

// Отладочная информация
error_log("get_posts.php called with last_id: " . $lastId);

// Получаем новые посты
$stmt = $db->prepare("SELECT * FROM posts WHERE id > ? ORDER BY id ASC LIMIT 100");
$stmt->execute([$lastId]);
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Отладочная информация
error_log("Found " . count($posts) . " new posts");

// Если постов нет, возвращаем пустой массив
if (empty($posts)) {
    header('Content-Type: application/json');
    echo json_encode([]);
    exit;
}

// Файлы
$postIds = array_column($posts, 'id');
$files = [];
if ($postIds) {
    $in = str_repeat('?,', count($postIds) - 1) . '?';
    $f = $db->prepare("SELECT post_id, file_name FROM post_files WHERE post_id IN ($in)");
    $f->execute($postIds);
    $files = $f->fetchAll(PDO::FETCH_GROUP|PDO::FETCH_COLUMN);
}

// Ответы (обратные ссылки)
$repliesMap = [];
foreach ($posts as $p) {
    preg_match_all('/>>(\d+)/', $p['text'], $matches);
    foreach ($matches[1] as $targetId) {
        $targetId = (int)$targetId;
        $repliesMap[$targetId][] = $p['id'];
    }
}

// Собираем JSON
$out = [];
foreach ($posts as $p) {
    $out[] = [
        'id'       => (int)$p['id'],
        'text'     => formatQuotedText($p['text']),
        'created_at' => date('d.m.Y H:i', strtotime($p['created_at'])),
        'files'    => $files[$p['id']] ?? [],
        'replies'  => $repliesMap[$p['id']] ?? [],
    ];
}

header('Content-Type: application/json');
echo json_encode($out);
?>