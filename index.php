<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
// Принудительное перенаправление на HTTPS
/*
if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
    $redirect_url = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    header('HTTP/1.1 301 Moved Permanently');
    header('Location: ' . $redirect_url);
    exit();
}

// Безопасные настройки сессии
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => $_SERVER['HTTP_HOST'],
    'secure' => true,    // Только HTTPS
    'httponly' => true,  // Не доступно через JavaScript
    'samesite' => 'Lax'  // Защита от CSRF
]);
*/

session_start();

/*
// Обновите установку куки темы
if (isset($_POST['theme'])) {
    setcookie('theme', $_POST['theme'], [
        'expires' => time() + 60 * 60 * 24 * 365,
        'path' => '/',
        'domain' => $_SERVER['HTTP_HOST'],
        'secure' => true,    // Только HTTPS
        'httponly' => true,  // Не доступно через JavaScript
        'samesite' => 'Lax'  // Защита от CSRF
    ]);
}
*/


include 'db.php';

function formatQuotedText($text)
{
    // Сначала экранируем HTML
    $text = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    
    // Затем обрабатываем BB-коды
    $text = preg_replace('/\[b\](.*?)\[\/b\]/s', '<b>$1</b>', $text);
    $text = preg_replace('/\[i\](.*?)\[\/i\]/s', '<i>$1</i>', $text);
    $text = preg_replace('/\[spoiler\](.*?)\[\/spoiler\]/s', '<span class="spoiler hidden">$1</span>', $text);
    $text = preg_replace('/\[s\](.*?)\[\/s\]/s', '<s>$1</s>', $text);
    $text = preg_replace('/\[u\](.*?)\[\/u\]/s', '<u>$1</u>', $text);
    
    // Обработка ссылок на посты
    $text = preg_replace(
        '/&gt;&gt;(\d+)/',
        '<a href="#post-$1" class="post-link" data-post-id="$1">&gt;&gt;$1</a>',
        $text
    );
    
    // Обработка обычных URL-ссылок
    $text = preg_replace(
        '/(https?:\/\/[^\s<>]+)/',
        '<a href="$1" target="_blank" rel="nofollow" class="url-link">$1</a>',
        $text
    );
    
    // Обработка цитат (должна быть после экранирования)
    $text = preg_replace('/^(&gt;)(.*)$/m', '<span class="quote">$1$2</span>', $text);
    
    // Преобразование переносов строк
    $text = nl2br($text);
    
    return $text;
}

// Отправка формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'post.php';
}

// Получаем посты
$statement = $db->query("SELECT * FROM posts ORDER BY id DESC LIMIT 500");
$posts = $statement->fetchAll(PDO::FETCH_ASSOC);

// Получаем файлы
$stmt = $db->query("SELECT post_id, file_name FROM post_files");
$filesData = $stmt->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_COLUMN);

// Находим ответы
$repliesMap = [];
foreach ($posts as $p) {
    preg_match_all('/>>(\d+)/', $p['text'], $matches);
    foreach ($matches[1] as $targetId) {
        $targetId = (int)$targetId;
        if (!isset($repliesMap[$targetId])) {
            $repliesMap[$targetId] = [];
        }
        $repliesMap[$targetId][] = $p['id'];
    }
}

// Добавляем файлы и ответы к постам
foreach ($posts as &$post) {
    $post['files'] = $filesData[$post['id']] ?? [];
    $post['replies'] = $repliesMap[$post['id']] ?? [];
}
unset($post);

$specialPost = null;
foreach ($posts as $p) {
    if (preg_match('/(\d)\1\1$/', $p['id'])) { // пост оканчивается на 111,222,333...
        if (!empty($p['files'])) {
            $specialPost = $p;
            break; // берём первый подходящий
        }
    }
}

$theme = $_COOKIE['theme'] ?? 'light'; // берём куку, если есть, иначе светлая

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/index.css">
    <link rel="shortcut icon" href="icon.png" />
    <script>
        // Мгновенная установка темы до рендера страницы
        document.addEventListener('DOMContentLoaded', function() {
            const theme = localStorage.getItem('theme') || 'light';
            const body = document.body;
            body.classList.add(theme + '-theme');
            
            // Меняем картинку в header
            const headerImg = document.getElementById('headerImage');
            if (headerImg) {
                headerImg.src = theme === 'dark' ? 'autumn.png' : 'header.png';
            }
        });
    </script>
</head>
<body class="<?= $theme ?>-theme">
    <!-- Узкий заголовок с переключателем темы -->
    <div id="theme-bar">
        <button class="bar-btn" id="scrollTop">▲ Наверх</button>
        <button class="bar-btn" id="scrollBottom">▼ Вниз</button>
        <button id="themeToggle">🌙 Темная тема</button>
    </div>
    
    <script>
        // Скролл вверх
        document.getElementById('scrollTop').addEventListener('click', () => {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
        
        // Скролл вниз
        document.getElementById('scrollBottom').addEventListener('click', () => {
            window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });
        });
    </script>

    <!-- Основной header -->
    <header style="text-align:center; margin-bottom:10px;">
        <img id="headerImage" src="header.png" alt="Header" style="height:100px; width:auto;">
        <nav style="margin-top:10px;">
            <ul style="list-style:none; padding:0; margin:0; display:flex; justify-content:center; gap:20px;">
            <li><a href="archive.php">Архив</a></li>
            </ul>
        </nav>
        
        <?php if ($specialPost): ?>
        <div class="header-gallery" style="margin-top:10px;">
            <?php 
            $images = array_filter($specialPost['files'], function($file) {
                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                return !in_array($ext, ['mp4','webm','ogg']); // только картинки
            });
            $images = array_slice($images, 0, 4); // берём только 4 первые
            ?>
            <?php foreach ($images as $file): ?>
                <img src="uploads/<?= htmlspecialchars($file) ?>" style="max-height:200px; margin:3px;">
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- === Твой текстовый блок в шапке === -->
        <?php
        
        $headerText = '';

        ?>
        
        <div class="header-text <?= mb_strlen(strip_tags($headerText)) > 500 ? 'collapsed' : '' ?>" style="margin:15px auto; max-width:800px; text-align:left; line-height:1.5;">
            <?= $headerText ?>
        </div>
        
        <?php if (mb_strlen(strip_tags($headerText)) > 500): ?>
            <button class="toggle-btn">Раскрыть ▼</button>
        <?php endif; ?>
    </header>

    <?php include 'function.php'; ?>
    


    <form method="post" enctype="multipart/form-data" class="message-form">
        <?php if (!empty($_SESSION['error'])): ?>
        <div class="timer-message" id="timer-message" data-seconds="<?= $_SESSION['remaining_time'] ?? 0 ?>">
            <?= htmlspecialchars($_SESSION['error'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
            <?php if (($_SESSION['remaining_time'] ?? 0) > 0): ?>
                (Осталось <span id="timer"><?= (int)$_SESSION['remaining_time'] ?></span> сек.)
            <?php endif; ?>
        </div>
        <?php unset($_SESSION['error'], $_SESSION['remaining_time']); ?>
        <?php endif; ?>

        <div class="toolbar" style="margin-bottom: 5px;">
            <button type="button" onclick="wrapQuote('>')">Цитата</button>
            <button type="button" onclick="formatBold()" title="Жирный"><b>B</b></button>
            <button type="button" onclick="formatItalic()" title="Курсив"><i>I</i></button>
            <button type="button" onclick="formatUnderline()" title="Подчеркнутый"><u>U</u></button>
            <button type="button" onclick="formatStrike()" title="Зачеркнутый"><s>S</s></button>
            <button type="button" onclick="formatSpoiler()" title="Спойлер"><spoiler>Spoiler</spoiler></button>
        </div>
        
        <textarea name="text" placeholder="Текст сообщения"><?= htmlspecialchars($_POST['text'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></textarea>
        
        <div class="form-buttons">
            <div class="file-input-container">
                <span class="custom-file-button">Выбрать файлы</span>
                <input type="file" name="media[]" accept="image/*,video/*" multiple 
                       onchange="if(this.files.length > 8){ alert('Можно выбрать не более 8 файлов'); this.value = ''; }">
            </div>
            <button type="submit">Чесать</button>
        </div>
        
        <div id="file-list" style="margin-bottom: 10px; color: gray;"></div>
    </form>

    <div id="posts-container">
        <?php foreach ($posts as $post): ?>
        <div class="post" id="post-<?= (int)$post['id'] ?>">
            <?php if (!empty($post['replies'])): ?>
            <div class="post-replies" style="font-size:0.8em; color:gray; margin-bottom:3px;">
                Ответы: 
                <?php foreach ($post['replies'] as $replyId): ?>
                    <a href="#post-<?= (int)$replyId ?>" class="post-link" data-post-id="<?= (int)$replyId ?>">>><?= (int)$replyId ?></a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <div class="post-header">
                <small style="color:gray">№<?= (int)$post['id'] ?></small>
                <small style="color:gray"><?= date('d.m.Y H:i', strtotime($post['created_at'])) ?></small>
                <span class="reply-text" onclick="replyToPost(<?= (int)$post['id'] ?>)">Ответить</span>
            </div>
            
            <?php $text = formatQuotedText($post['text']); ?>
            <div class="post-text <?= mb_strlen(strip_tags($text)) > 500 ? 'collapsed' : '' ?>">
                <?= $text ?>
            </div>
            
            <?php if (mb_strlen(strip_tags($text)) > 500): ?>
                <button class="toggle-btn">Раскрыть ▼</button>
            <?php endif; ?>
            
            <?php if (!empty($post['files'])): ?>
            <div class="media-files">
                <?php foreach ($post['files'] as $file): 
                    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                    $isVideo = in_array($ext, ['mp4', 'webm', 'ogg']);
                ?>
                    <?php if ($isVideo): ?>
                        <video class="lazy" controls preload="none" data-src="uploads/<?= htmlspecialchars($file) ?>">
                            <source data-src="uploads/<?= htmlspecialchars($file) ?>" type="video/<?= $ext ?>">
                        </video>
                    <?php else: ?>
                        <img class="lazy" data-src="uploads/<?= htmlspecialchars($file) ?>" alt="image" 
                             onclick="openModal(this)" data-post-id="<?= (int)$post['id'] ?>">
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Модальное окно -->
    <div id="imageModal" class="modal">
        <span class="prev">&#10094;</span>
        <img id="modalImage" class="modal-content">
        <span class="next">&#10095;</span>
    </div>

    <script>
        let currentImages = [];
        let currentIndex = 0;

        function openModal(img) {
            const modal = document.getElementById("imageModal");
            const modalImg = document.getElementById("modalImage");
            const prevBtn = modal.querySelector(".prev");
            const nextBtn = modal.querySelector(".next");

            // собираем все картинки из поста
            const post = img.closest(".post");
            currentImages = Array.from(post.querySelectorAll(".media-files img"));
            currentIndex = currentImages.indexOf(img);

            modal.style.display = "block";
            modalImg.src = img.dataset.src || img.src;

            // если в посте одна картинка → скрываем стрелки
            if (currentImages.length <= 1) {
                prevBtn.style.display = "none";
                nextBtn.style.display = "none";
            } else {
                prevBtn.style.display = "block";
                nextBtn.style.display = "block";
            }
        }

        function showImage(index) {
            if (!currentImages.length) return;
            if (index < 0) index = currentImages.length - 1;
            if (index >= currentImages.length) index = 0;
            
            currentIndex = index;
            const modalImg = document.getElementById("modalImage");
            const target = currentImages[currentIndex];
            modalImg.src = target.dataset.src || target.src;
        }

        // Навигация по кнопкам
        document.querySelector("#imageModal .prev").onclick = () => showImage(currentIndex - 1);
        document.querySelector("#imageModal .next").onclick = () => showImage(currentIndex + 1);

        // Закрытие по клику на фон
        document.getElementById("imageModal").onclick = (e) => {
            if (e.target.id === "imageModal") e.target.style.display = "none";
        };

        // Закрытие/листание по клавишам
        document.addEventListener("keydown", function(e) {
            const modal = document.getElementById("imageModal");
            if (modal.style.display !== "block") return;
            
            if (e.key === "ArrowLeft" && currentImages.length > 1) showImage(currentIndex - 1);
            if (e.key === "ArrowRight" && currentImages.length > 1) showImage(currentIndex + 1);
            if (e.key === "Escape") modal.style.display = "none";
        });

        // Функция для прокрутки к посту с мягкой подсветкой
        function scrollToPost(postId) {
            const targetElement = document.getElementById('post-' + postId);
            if (targetElement) {
                // Плавная прокрутка
                targetElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
                
                // Удаляем предыдущие классы подсветки
                targetElement.classList.remove('post-highlight');
                targetElement.classList.remove('post-highlight-fade');
                
                // Принудительный reflow для анимации
                void targetElement.offsetWidth;
                
                // Мягкая подсветка на 0.5 секунды
                targetElement.classList.add('post-highlight');
                setTimeout(() => {
                    targetElement.classList.remove('post-highlight');
                    targetElement.classList.add('post-highlight-fade');
                    
                    // Полное удаление классов через 0.5 секунды
                    setTimeout(() => {
                        targetElement.classList.remove('post-highlight-fade');
                    }, 500);
                }, 500); // Подсветка длится 0.5 секунды
            }
        }

        // Обработчик кликов по ссылкам
        document.addEventListener('click', function(e) {
            if (e.target.matches('.post-link')) {
                e.preventDefault();
                const postId = e.target.dataset.postId;
                scrollToPost(postId);
            }
        });

        // Обработка якоря при загрузке страницы
        document.addEventListener('DOMContentLoaded', function() {
            if (window.location.hash && window.location.hash.startsWith('#post-')) {
                const postId = window.location.hash.substring(6);
                // Небольшая задержка для полной загрузки контента
                setTimeout(() => {
                    scrollToPost(postId);
                    // Убираем якорь из URL
                    history.replaceState(null, null, window.location.pathname + window.location.search);
                }, 300);
            }
            
            // Если нет якоря в URL
            if (!window.location.hash || !window.location.hash.startsWith('#post-')) {
                setTimeout(() => {
                    // Находим первый пост (самый новый)
                    const firstPost = document.querySelector('.post');
                    if (firstPost) {
                        firstPost.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    } else {
                        // Если постов нет, просто наверх
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    }
                }, 100);
            }
        });

        // Делегирование
        document.addEventListener("click", function(e) {
            if (e.target.matches(".toggle-btn")) {
                const textBlock = e.target.previousElementSibling;
                textBlock.classList.toggle("collapsed");
                e.target.textContent = textBlock.classList.contains("collapsed") ? "Раскрыть ▼" : "Свернуть ▲";
            }
            
            if (e.target.matches(".reply-btn")) {
                const postId = e.target.dataset.postId;
                replyToPost(postId);
            }
            
            if (e.target.matches(".spoiler")) {
                e.target.classList.toggle("hidden");
                e.target.classList.toggle("visible");
            }
        });

        // Глобальный observer
        let lazyObserver;
        
        function initLazyLoad(container = document) {
            const lazyElements = container.querySelectorAll('.lazy');
            
            if ('IntersectionObserver' in window) {
                if (!lazyObserver) {
                    lazyObserver = new IntersectionObserver((entries, observer) => {
                        entries.forEach(entry => {
                            if (entry.isIntersecting) {
                                const el = entry.target;
                                if (el.tagName === 'IMG') {
                                    el.src = el.dataset.src;
                                }
                                if (el.tagName === 'VIDEO') {
                                    el.src = el.dataset.src;
                                    const sources = el.querySelectorAll('source');
                                    sources.forEach(s => s.src = s.dataset.src);
                                    el.load();
                                }
                                el.classList.remove('lazy');
                                observer.unobserve(el);
                            }
                        });
                    });
                }
                lazyElements.forEach(el => lazyObserver.observe(el));
            } else {
                // fallback
                lazyElements.forEach(el => {
                    if (el.tagName === 'IMG') el.src = el.dataset.src;
                    if (el.tagName === 'VIDEO') {
                        el.src = el.dataset.src;
                        const sources = el.querySelectorAll('source');
                        sources.forEach(s => s.src = s.dataset.src);
                        el.load();
                    }
                });
            }
        }

        // Инициализация для старых постов
        document.addEventListener("DOMContentLoaded", () => initLazyLoad());

        // AJAX подгрузка
        let lastPostId = <?= (int)($posts[0]['id'] ?? 0) ?>;
        let isLoading = false;
        let unreadCount = 0;
        let originalTitle = document.title;
        let windowFocused = true;

        // Следим за фокусом окна
        window.addEventListener("focus", () => {
            windowFocused = true;
            unreadCount = 0;
            document.title = originalTitle;
        });
        
        window.addEventListener("blur", () => {
            windowFocused = false;
        });

        function notifyNewPosts(newPostsCount) {
            if (!windowFocused && newPostsCount > 0) {
                unreadCount += newPostsCount;
                document.title = `(${unreadCount}) Новые сообщения`;
            }
        }

        function loadNewPosts() {
            if (isLoading) return;
            isLoading = true;
            
            const postsContainer = document.getElementById("posts-container");
            
            fetch('get_posts.php?last_id=' + lastPostId)
                .then(r => r.json())
                .then(data => {
                    if (data.length > 0) {
                        // Обновляем lastPostId на самый новый ID из полученных постов
                        lastPostId = Math.max(...data.map(post => post.id));
                        
                        data.forEach(post => {
                            // Проверяем, не существует ли уже такой пост
                            if (!document.getElementById("post-" + post.id)) {
                                const el = renderPost(post);
                                postsContainer.prepend(el);
                                initLazyLoad(el);
                                
                                // === ДОБАВЛЕНИЕ ОБРАТНЫХ ССЫЛОК У СТАРЫХ ПОСТОВ ===
                                if (post.replies && post.replies.length > 0) {
                                    post.replies.forEach(targetId => {
                                        const targetEl = document.querySelector("#post-" + targetId);
                                        if (targetEl) {
                                            let repliesDiv = targetEl.querySelector(".post-replies");
                                            if (!repliesDiv) {
                                                repliesDiv = document.createElement("div");
                                                repliesDiv.className = "post-replies";
                                                repliesDiv.style = "font-size:0.8em; color:gray; margin-bottom:3px;";
                                                repliesDiv.textContent = "Ответы: ";
                                                // Вставляем перед header поста
                                                const header = targetEl.querySelector(".post-header");
                                                targetEl.insertBefore(repliesDiv, header);
                                            }
                                            
                                            // проверим, нет ли уже ссылки (чтобы не дублировать)
                                            if (!repliesDiv.querySelector(`a[href="#post-${post.id}"]`)) {
                                                const link = document.createElement("a");
                                                link.href = "#post-" + post.id;
                                                link.className = "post-link";
                                                link.dataset.postId = post.id;
                                                link.textContent = ">>" + post.id;
                                                repliesDiv.append(" ", link);
                                            }
                                        }
                                    });
                                }
                            }
                        });
                        
                        // добавляем уведомление
                        notifyNewPosts(data.length)
                    }
                    isLoading = false;
                })
                .catch(err => {
                    console.error(err);
                    isLoading = false;
                });
        }
        
        setInterval(loadNewPosts, 5000);
        
        // Отладочная информация
        console.log('Initial lastPostId:', lastPostId);
    </script>

    <script>
        function renderPost(post) {
            const div = document.createElement("div");
            div.className = "post";
            div.id = "post-" + post.id;
            
            // replies block (добавляем БЛОК ОТВЕТОВ в начало)
            let repliesHTML = "";
            if (post.replies && post.replies.length > 0) {
                repliesHTML = `<div class="post-replies" style="font-size:0.8em; color:gray; margin-bottom:3px;">Ответы: `;
                post.replies.forEach(replyId => {
                    repliesHTML += `<a href="#post-${replyId}" class="post-link" data-post-id="${replyId}">>>${replyId}</a> `;
                });
                repliesHTML += `</div>`;
            }
            
            // header
            const header = `<div class="post-header">
                <small style="color:gray">№${post.id}</small>
                <small style="color:gray">${post.created_at}</small>
                <span class="reply-text" onclick="replyToPost(${post.id})">Ответить</span>
            </div>`;
            
            // text
            const textBlock = `<div class="post-text">${post.text}</div>`;
            
            // files
            let filesHTML = "";
            if (post.files && post.files.length > 0) {
                filesHTML = `<div class="media-files">`;
                post.files.forEach(file => {
                    const ext = file.split('.').pop().toLowerCase();
                    if (["mp4","webm","ogg"].includes(ext)) {
                        filesHTML += `<video class="lazy" controls preload="none" data-src="uploads/${file}">
                            <source data-src="uploads/${file}" type="video/${ext}">
                        </video>`;
                    } else {
                        filesHTML += `<img class="lazy" data-src="uploads/${file}" alt="image" onclick="openModal(this)" data-post-id="${post.id}">`;
                    }
                });
                filesHTML += `</div>`;
            }
            
            // Собираем всё вместе: сначала ответы, потом header, потом текст, потом файлы
            div.innerHTML = repliesHTML + header + textBlock + filesHTML;
            return div;
        }
    </script>

    <script>
        function replyToPost(postId) {
            const textarea = document.querySelector('textarea[name="text"]');
            const selection = window.getSelection().toString().trim();
            let quote = ">>" + postId + "\n";
            
            if (selection) {
                // Каждую строку выделенного текста начинаем с ">"
                quote += selection.split("\n").map(line => ">" + line).join("\n") + "\n";
            }
            
            // Вставляем в textarea (с переносом строки, если там уже есть текст)
            textarea.value += (textarea.value ? "\n" : "") + quote;
            textarea.focus();
        }
    </script>

    <script>
        (function() {
            const body = document.body;
            const button = document.getElementById('themeToggle');
            const headerImg = document.getElementById('headerImage');
            
            function updateButtonText(theme) {
                button.textContent = theme === 'dark' ? '🌞 Светлая тема' : '🌙 Темная тема';
            }
            
            function updateHeaderImage(theme) {
                headerImg.src = theme === 'dark' ? 'autumn.png' : 'header.png';
            }
            
            // начальная установка
            const initialTheme = body.classList.contains('dark-theme') ? 'dark' : 'light';
            updateButtonText(initialTheme);
            updateHeaderImage(initialTheme);
            
            button.addEventListener('click', () => {
                body.classList.toggle('light-theme');
                body.classList.toggle('dark-theme');
                
                const theme = body.classList.contains('dark-theme') ? 'dark' : 'light';
                localStorage.setItem('theme', theme);
                document.cookie = "theme=" + theme + ";path=/;max-age=" + 60*60*24*365;
                
                updateButtonText(theme);
                updateHeaderImage(theme);
            });
        })();
    </script>

    <script>
        // --- Quote-on-select: сохраняем выделение до клика по "Ответить" ---
        let _cachedSelection = '';
        
        function getSelectedTextIn(container) {
            const sel = window.getSelection && window.getSelection();
            if (!sel || sel.isCollapsed || !sel.rangeCount) return '';
            
            const a = sel.anchorNode, f = sel.focusNode;
            // учитываем только выделение внутри конкретного поста
            if (!container.contains(a) && !container.contains(f)) return '';
            
            return sel.toString().trim();
        }
        
        // Ловим выделение РАНЬШЕ, чем произойдёт click (мышь и тач)
        function captureSelForReply(e) {
            const btn = e.target.closest('.reply-text, .reply-btn');
            if (!btn) return;
            
            const postEl = btn.closest('.post');
            _cachedSelection = getSelectedTextIn(postEl);
        }
        
        // useCapture=true, чтобы обработчик сработал до остальных
        document.addEventListener('mousedown', captureSelForReply, true);
        document.addEventListener('pointerdown', captureSelForReply, true);
        
        // Переопределяем (или оставляем твою) replyToPost: используем кэш
        function replyToPost(postId) {
            const textarea = document.querySelector('textarea[name="text"]');
            const selection = (_cachedSelection || '').trim();
            _cachedSelection = ''; // сбрасываем кэш
            
            let quote = '>>' + postId + '\n';
            if (selection) {
                quote += selection.split(/\r?\n/).map(line => '>' + line).join('\n') + '\n';
            }
            
            textarea.value += (textarea.value ? '\n' : '') + quote;
            textarea.focus();
        }
    </script>
</body>
</html>