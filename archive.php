<?php
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

// Определяем тему ДО любого вывода
$theme = $_COOKIE['theme'] ?? 'light';
$isDarkTheme = $theme === 'dark';


include 'db.php';

function formatQuotedText($text) {
    // Сначала экранируем HTML
    $text = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    
    // Затем обрабатываем BB-коды
    $text = preg_replace('/\[b\](.*?)\[\/b\]/s', '<b>$1</b>', $text);
    $text = preg_replace('/\[i\](.*?)\[\/i\]/s', '<i>$1</i>', $text);
    $text = preg_replace('/\[s\](.*?)\[\/s\]/s', '<s>$1</s>', $text); // Добавьте эту строку
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

// === Пагинация ===
$totalPosts = $db->query("SELECT COUNT(*) FROM posts")->fetchColumn();
$perPage = 500; // на страницу
$currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$totalPages = ceil($totalPosts / $perPage);
$offset = ($currentPage - 1) * $perPage;

// Получаем посты для текущей страницы
$statement = $db->query("SELECT * FROM posts ORDER BY id DESC LIMIT $perPage OFFSET $offset");
$posts = $statement->fetchAll(PDO::FETCH_ASSOC);

// Получаем файлы для всех постов одним запросом
$stmt = $db->query("SELECT post_id, file_name FROM post_files");
$filesData = $stmt->fetchAll(PDO::FETCH_GROUP|PDO::FETCH_COLUMN);

// Находим ссылки-ответы
$repliesMap = [];
foreach ($posts as $p) {
    preg_match_all('/>>(\d+)/', $p['text'], $matches);
    foreach ($matches[1] as $targetId) {
        $targetId = (int)$targetId;
        $repliesMap[$targetId][] = $p['id'];
    }
}

// Добавляем файлы и ответы к постам
foreach ($posts as &$post) {
    $post['files'] = $filesData[$post['id']] ?? [];
    $post['replies'] = $repliesMap[$post['id']] ?? [];
}
unset($post);




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



$theme = $_COOKIE['theme'] ?? 'light'; // берём куку, если есть, иначе светлая
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/archive.css?v=1">
<!-- Подключаем CSS для темной темы сразу с правильным media -->
    <link rel="stylesheet" href="css/dark-theme.css?v=1" id="theme-style" media="<?= $isDarkTheme ? 'all' : 'none' ?>">
    <link rel="shortcut icon" href="icon.png" />
   </head>
<body class="<?= $theme === 'dark' ? 'dark-theme' : 'light-theme' ?>">
    <!-- Переключатель темы -->
    <div class="theme-switcher" id="themeToggle">
        <?= $theme === 'dark' ? '🌞 Светлая тема' : '🌙 Темная тема' ?>
    </div>
  <script>
    // Функция для переключения темы
 function toggleTheme() {
        const body = document.body;
        const themeStyle = document.getElementById('theme-style');
        const themeToggle = document.getElementById('themeToggle');
        
        if (body.classList.contains('dark-theme')) {
            // Переключаем на светлую тему
            body.classList.remove('dark-theme');
            body.classList.add('light-theme');
            themeStyle.setAttribute('media', 'none');
            themeToggle.textContent = '🌙 Темная тема';
            document.cookie = "theme=light;path=/;max-age=" + 60*60*24*365;
            localStorage.setItem('theme', 'light');
        } else {
            // Переключаем на темную тему
            body.classList.remove('light-theme');
            body.classList.add('dark-theme');
            themeStyle.setAttribute('media', 'all');
            themeToggle.textContent = '🌞 Светлая тема';
            document.cookie = "theme=dark;path=/;max-age=" + 60*60*24*365;
            localStorage.setItem('theme', 'dark');
        }
    }

    // Инициализация темы при загрузке (для синхронизации с localStorage)
    document.addEventListener('DOMContentLoaded', function() {
        const savedTheme = localStorage.getItem('theme');
        const currentTheme = '<?= $theme ?>';
        
        // Если в localStorage другая тема, синхронизируем
        if (savedTheme && savedTheme !== currentTheme) {
            toggleTheme();
        }

        // Обработчик клика на переключатель
        document.getElementById('themeToggle').addEventListener('click', toggleTheme);
    });
    </script>
    
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
    <div class="archive-notice">
        
       <a href="index.php" class="my-link">Главная</a>
    </div>

      <div class="archive-info">
        <p>Всего сообщений: <?= $totalPosts ?></p>
        <?php if (!empty($posts)): ?>
            <p>Диапазон ID: от <?= min(array_column($posts, 'id')) ?> до <?= max(array_column($posts, 'id')) ?></p>
        <?php endif; ?>
    </div>
<!-- Навигация сверху -->
<!-- Навигация -->
<div class="pagination">
    <?php if ($currentPage > 1): ?>
      
        <a href="?page=<?= $currentPage - 1 ?>">← Предыдущая</a>
    <?php endif; ?>

    <span>Страница <?= $currentPage ?> из <?= $totalPages ?></span>

   

    <?php if ($currentPage < $totalPages): ?>
        <a href="?page=<?= $currentPage + 1 ?>">Следующая →</a>
        
    <?php endif; ?>
     <form method="get" action="" class="page-jump">
        <input type="number" name="page" min="1" max="<?= $totalPages ?>" value="<?= $currentPage ?>" class="page-input">
        <button type="submit" class="page-btn">Перейти</button>
    </form>
</div>


<?php include 'function.php'; ?>


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
                        <img class="lazy" data-src="uploads/<?= htmlspecialchars($file) ?>" alt="image" onclick="openModal(this)" data-post-id="<?= (int)$post['id'] ?>">
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

    // собираем все картинки из поста
    const post = img.closest(".post");
    currentImages = Array.from(post.querySelectorAll(".media-files img"));
    currentIndex = currentImages.indexOf(img);

    modal.style.display = "block";
    modalImg.src = img.dataset.src || img.src;
}

// Листание
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

    if (e.key === "ArrowLeft") showImage(currentIndex - 1);
    if (e.key === "ArrowRight") showImage(currentIndex + 1);
    if (e.key === "Escape") modal.style.display = "none";
});

// Обработчик кликов по ссылкам
document.addEventListener('click', function(e) {
    if (e.target.matches('.post-link')) {
        e.preventDefault();
        
        const postId = e.target.dataset.postId;
        scrollToPost(postId);
    }
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
    // Добавьте в начало скрипта получение элементов
const closeFormBtn = document.getElementById('closeFormBtn');
const slideFormPanel = document.getElementById('slide-form-panel');
const floatingButton = document.getElementById('floating-form-button');

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
    
    // Автоматически открываем форму
    openForm();
    
    // Фокусируемся на текстовом поле
    textarea.focus();
}

// Функция для открытия формы
function openForm() {
    const slideFormPanel = document.getElementById('slide-form-panel');
    const floatingButton = document.getElementById('floating-form-button');
    
    slideFormPanel.classList.add('open');
    floatingButton.style.display = 'none';
    
    // Фокусируемся на текстовом поле при открытии
    setTimeout(() => {
        document.getElementById('messageText').focus();
    }, 100);
}

// Закрытие формы
if (closeFormBtn) {
    closeFormBtn.addEventListener('click', function() {
        slideFormPanel.classList.remove('open');
        setTimeout(() => {
            floatingButton.style.display = 'block';
        }, 400);
    });
}
// Открытие формы через плавающую кнопку
floatingButton.addEventListener('click', openForm);

// Закрытие формы
closeFormBtn.addEventListener('click', closeForm);

// Закрытие формы при клике вне ее
document.addEventListener('click', function(e) {
    const slideFormPanel = document.getElementById('slide-form-panel');
    const floatingButton = document.getElementById('floating-form-button');
    
    if (slideFormPanel.classList.contains('open') &&
        !slideFormPanel.contains(e.target) &&
        !floatingButton.contains(e.target)) {
        closeForm();
    }
});

// Закрытие формы по ESC
document.addEventListener('keydown', function(e) {
    const slideFormPanel = document.getElementById('slide-form-panel');
    
    if (e.key === 'Escape' && slideFormPanel.classList.contains('open')) {
        closeForm();
    }
});
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
        headerImg.src = theme === 'dark' ? 'header-dark.png' : 'header.png';
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

</script>
<script>
// Функция для показа сообщений
function showMessage(type, text, duration = 5000) {
    const messagesContainer = document.getElementById('form-messages');
    const messageDiv = document.createElement('div');
    messageDiv.className = `message ${type}`;
    messageDiv.textContent = text;
    
    messagesContainer.appendChild(messageDiv);
    
    if (duration > 0) {
        setTimeout(() => {
            messageDiv.remove();
        }, duration);
    }
    
    return messageDiv;
}

// AJAX отправка формы
document.getElementById('messageForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const submitBtn = document.getElementById('submitBtn');
    const originalText = submitBtn.textContent;
    submitBtn.textContent = 'Отправка...';
    submitBtn.disabled = true;
    
    const formData = new FormData(this);
    
    try {
        const response = await fetch('post2.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            showMessage('success', result.message || 'Сообщение успешно отправлено!', 3000);
            
            // Загружаем новые посты
            loadNewPosts();
            
        } else {
            showMessage('error', result.error || 'Произошла ошибка', 5000);
        }
        
    } catch (error) {
        showMessage('error', 'Ошибка сети: ' + error.message, 5000);
    } finally {
        submitBtn.textContent = originalText;
        submitBtn.disabled = false;
    }
});  



// Закрытие формы
closeFormBtn.addEventListener('click', function() {
    slideFormPanel.classList.remove('open');
    setTimeout(() => {
        floatingButton.style.display = 'block';
    }, 400);
});

// Закрытие формы при клике вне ее
document.addEventListener('click', function(e) {
    if (slideFormPanel.classList.contains('open') &&
        !slideFormPanel.contains(e.target) &&
        !floatingButton.contains(e.target)) {
        slideFormPanel.classList.remove('open');
        setTimeout(() => {
            floatingButton.style.display = 'block';
        }, 400);
    }
});

// Предотвращаем закрытие при клике внутри формы
slideFormPanel.addEventListener('click', function(e) {
    e.stopPropagation();
});

// Закрытие формы по ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && slideFormPanel.classList.contains('open')) {
        slideFormPanel.classList.remove('open');
        setTimeout(() => {
            floatingButton.style.display = 'block';
        }, 400);
    }
});

// При успешной отправке формы - закрываем ее
function showMessage(type, text, duration = 5000) {
    const messagesContainer = document.getElementById('form-messages');
    const messageDiv = document.createElement('div');
    messageDiv.className = `message ${type}`;
    messageDiv.textContent = text;
    
    messagesContainer.appendChild(messageDiv);
    
    if (duration > 0) {
        setTimeout(() => {
            messageDiv.remove();
        }, duration);
    }
    
    // Если успешно отправлено - закрываем форму через 2 секунды
    if (type === 'success') {
        setTimeout(() => {
            closeForm();
            
            // Очищаем форму
            document.getElementById('messageText').value = '';
            document.getElementById('mediaFiles').value = '';
            document.getElementById('file-list').textContent = '';
            messagesContainer.innerHTML = '';
            
        }, 2000);
    }
    
    return messageDiv;
}
</script>
</body>
</html>