

   <script>
       
       function initLazyLoad() {
    const lazyMedia = document.querySelectorAll('.lazy-media[data-src]');
    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver(entries => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const media = entry.target;
                    if (media.tagName === 'IMG') {
                        media.src = media.dataset.src;
                    } else if (media.tagName === 'VIDEO') {
                        const source = media.querySelector('source');
                        if (source && source.dataset.src) {
                            source.src = source.dataset.src;
                            media.load();
                        }
                    }
                    observer.unobserve(media);
                }
            });
        });
        lazyMedia.forEach(m => observer.observe(m));
    } else {
        lazyMedia.forEach(m => {
            if (m.tagName === 'IMG') m.src = m.dataset.src;
            if (m.tagName === 'VIDEO') {
                const source = m.querySelector('source');
                if (source && source.dataset.src) {
                    source.src = source.dataset.src;
                    m.load();
                }
            }
        });
    }
}

document.addEventListener("DOMContentLoaded", initLazyLoad);
   let selectedFiles = [];

document.addEventListener('DOMContentLoaded', function () {
    const input = document.querySelector('input[type="file"]');
    const form = document.querySelector('form');
    
    input.addEventListener('change', (e) => {
        const newFiles = Array.from(e.target.files);
        if ((selectedFiles.length + newFiles.length) > 8) {
            alert('Можно выбрать не более 8 файлов');
            return input.value = '';
        }

        selectedFiles.push(...newFiles);
        updateFileList();
        input.value = ''; // сброс input, чтобы можно было повторно выбрать тот же файл
    });

    form.addEventListener('submit', (e) => {
        const dataTransfer = new DataTransfer();
        selectedFiles.forEach(file => dataTransfer.items.add(file));
        input.files = dataTransfer.files;
    });
// --- Модальное окно с масштабированием и перетаскиванием ---
let currentScale = 1;
let isDragging = false;
let startX, startY;
let translateX = 0, translateY = 0;

const modal = document.getElementById("imageModal");
const modalImg = document.getElementById("modalImage");

// Открыть модалку с картинкой
function openModal(img) {
    currentScale = 1;
    translateX = 0;
    translateY = 0;

    modalImg.src = img.src;

    // Сброс стилей
    modalImg.style.transform = 'translate(-50%, -50%) scale(1)';
    modalImg.style.left = '50%';
    modalImg.style.top = '50%';

    modal.style.display = "block";

    if (modalImg.complete) {
        centerImage();
    } else {
        modalImg.onload = centerImage;
    }
}

// Закрыть модалку
function closeModal() {
    modal.style.display = "none";
}

// --- Закрытие только по клику на фон ---
let backdropMouseDown = false;
let backdropDownX = 0, backdropDownY = 0;

modal.addEventListener('mousedown', (e) => {
    if (e.target === modal) {
        backdropMouseDown = true;
        backdropDownX = e.clientX;
        backdropDownY = e.clientY;
    } else {
        backdropMouseDown = false;
    }
});

modal.addEventListener('mouseup', (e) => {
    if (backdropMouseDown && e.target === modal) {
        const dx = e.clientX - backdropDownX;
        const dy = e.clientY - backdropDownY;
        if (Math.hypot(dx, dy) < 5) {
            closeModal();
        }
    }
    backdropMouseDown = false;
});

// Чтобы клик по картинке не закрывал модалку
modalImg.addEventListener('click', (e) => e.stopPropagation());

// --- Масштабирование колесиком ---
modalImg.addEventListener('wheel', function(e) {
    e.preventDefault();

    const rect = modalImg.getBoundingClientRect();
    const mouseX = e.clientX - rect.left;
    const mouseY = e.clientY - rect.top;

    const scalePointX = (mouseX - rect.width/2) / currentScale;
    const scalePointY = (mouseY - rect.height/2) / currentScale;

    const delta = Math.sign(e.deltaY);
    const oldScale = currentScale;

    if (delta > 0) {
        currentScale = Math.max(0.5, currentScale - 0.1);
    } else {
        currentScale = Math.min(3, currentScale + 0.1);
    }

    translateX += scalePointX * (oldScale - currentScale);
    translateY += scalePointY * (oldScale - currentScale);

    applyTransform();
});

// --- Перетаскивание ---
modalImg.addEventListener('dragstart', (e) => e.preventDefault());

modalImg.addEventListener('mousedown', function(e) {
    if (e.button === 0) {
        isDragging = true;
        startX = e.clientX - translateX;
        startY = e.clientY - translateY;
        modalImg.style.cursor = 'grabbing';
        e.preventDefault();
    }
});

document.addEventListener('mousemove', function(e) {
    if (!isDragging) return;

    translateX = e.clientX - startX;
    translateY = e.clientY - startY;

    applyTransform();
});

document.addEventListener('mouseup', function() {
    if (isDragging) {
        isDragging = false;
        modalImg.style.cursor = 'grab';
    }
});

// --- Сброс по двойному клику ---
modalImg.addEventListener('dblclick', function() {
    currentScale = 1;
    translateX = 0;
    translateY = 0;
    applyTransform();
});

// --- Применение трансформации ---
function applyTransform() {
    modalImg.style.transform = `translate(calc(-50% + ${translateX}px), calc(-50% + ${translateY}px)) scale(${currentScale})`;
}

function centerImage() {
    modalImg.style.position = 'absolute';
    modalImg.style.left = '50%';
    modalImg.style.top = '50%';
    applyTransform();
}

// --- Делегирование: открытие по клику на картинку поста ---
document.addEventListener('click', function(e) {
    const img = e.target.closest('.post img');
    if (img) {
        openModal(img);
    }
});


    // Таймер 
    const timerMsg = document.getElementById('timer-message');
    if (timerMsg) {
        const seconds = parseInt(timerMsg.dataset.seconds);
        updateTimer(seconds);
    }

    function updateTimer(seconds) {
        const timerElement = document.getElementById('timer');
        if (!timerElement) return;

        timerElement.textContent = seconds;

        if (seconds > 0) {
            setTimeout(() => updateTimer(seconds - 1), 1000);
        } else {
            document.getElementById('timer-message').style.display = 'none';
        }
    }
});

function updateFileList() {
    const listContainer = document.getElementById('file-list');
    listContainer.innerHTML = '';

    selectedFiles.forEach((file, index) => {
        const item = document.createElement('div');
        item.className = 'file-item';

        const nameSpan = document.createElement('span');
        nameSpan.textContent = `${index + 1}. ${file.name || '(без имени)'} (${(file.size / 1024).toFixed(1)} КБ)`;
        nameSpan.className = 'file-name';

        const removeBtn = document.createElement('button');
        removeBtn.textContent = '✕';
        removeBtn.className = 'remove-file-btn';
        removeBtn.onclick = () => {
            selectedFiles.splice(index, 1);
            updateFileList();
        };

        item.appendChild(nameSpan);
        item.appendChild(removeBtn);
        listContainer.appendChild(item);
    });

    if (selectedFiles.length === 0) {
        const emptyMsg = document.createElement('div');
        emptyMsg.textContent = '';
        emptyMsg.style.color = 'gray';
        emptyMsg.style.fontStyle = 'italic';
        listContainer.appendChild(emptyMsg);
    }
}


function replyToPost(postId) {
    const textarea = document.querySelector('textarea[name="text"]');
    const currentText = textarea.value;
    const cursorPosition = textarea.selectionStart;
    
    // Определяем, куда вставлять ответ
    let newText, newCursorPosition;
    
    if (cursorPosition === 0 && currentText === '') {
        // Если текстовое поле пустое
        newText = `>>${postId}\n`;
        newCursorPosition = newText.length;
    } else {
        // Разделяем текст на части до и после курсора
        const textBefore = currentText.substring(0, cursorPosition);
        const textAfter = currentText.substring(cursorPosition);
        
        // Вставляем ссылку с переносами
        newText = textBefore + `\n>>${postId}\n` + textAfter;
        newCursorPosition = cursorPosition + `\n>>${postId}\n`.length;
    }
    
    textarea.value = newText;
    textarea.focus();
    
    // Устанавливаем курсор после добавленной ссылки
    textarea.selectionStart = newCursorPosition;
    textarea.selectionEnd = newCursorPosition;
    
    // Прокрутка к текстовому полю
    textarea.scrollIntoView({ behavior: 'smooth' });
}

const btn = document.getElementById('toggleExpandBtn');
const text = document.getElementById('expandableText');

if (btn && text) {
    btn.addEventListener('click', () => {
        if (text.classList.contains('collapsed')) {
            text.classList.remove('collapsed');
            btn.textContent = 'Свернуть';
        } else {
            text.classList.add('collapsed');
            btn.textContent = 'Показать больше';
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    const threadHeader = document.getElementById('threadHeader');
    const expandBtn = document.getElementById('expandBtn');
    
    if (threadHeader && expandBtn) {
        expandBtn.addEventListener('click', function() {
            if (threadHeader.classList.contains('collapsed')) {
                threadHeader.classList.remove('collapsed');
                threadHeader.classList.add('expanded');
                expandBtn.textContent = 'Свернуть ▲';
            } else {
                threadHeader.classList.remove('expanded');
                threadHeader.classList.add('collapsed');
                expandBtn.textContent = 'Развернуть ▼';
            }
        });
    }
    // Автопрокрутка вниз
    //window.scrollTo(0, document.body.scrollHeight);
        // Автопрокрутка вверх
    window.scrollTo({
        top: 0,
        behavior: 'smooth'
    });
});

document.addEventListener("DOMContentLoaded", () => {
    const previews = new Map(); // хранит {link: previewBox}

    function createPreviewBox() {
        const box = document.createElement("div");
        box.className = "post-preview"; // CSS из style.css
        box.style.display = "none";
        document.body.appendChild(box);
        return box;
    }

    function showPreview(link) {
        const postId = link.dataset.postId;
        const targetPost = document.getElementById("post-" + postId);
        if (!targetPost) return;

        // Если превью уже есть для этой ссылки, используем его
        let box = previews.get(link);
        if (!box) {
            box = createPreviewBox();
            previews.set(link, box);
        }

        box.innerHTML = targetPost.innerHTML;

        const rect = link.getBoundingClientRect();
        box.style.top = (window.scrollY + rect.bottom + 5) + "px";
        box.style.left = (window.scrollX + rect.left) + "px";
        box.style.display = "block";

        // Таймер скрытия для каждой превью отдельный
        let hideTimeout = null;

        function scheduleHide() {
            if (hideTimeout) clearTimeout(hideTimeout);
            hideTimeout = setTimeout(() => {
                box.style.display = "none";
            }, 500);
        }

        // Сбрасываем таймер при наведении на ссылку или превью
        link.addEventListener("mouseenter", () => {
            if (hideTimeout) clearTimeout(hideTimeout);
        });
        box.addEventListener("mouseenter", () => {
            if (hideTimeout) clearTimeout(hideTimeout);
        });

        // Запускаем таймер при уходе с ссылки или превью
        link.addEventListener("mouseleave", scheduleHide);
        box.addEventListener("mouseleave", scheduleHide);
    }

    // Наведение на ссылку на пост
    document.addEventListener("mouseover", e => {
        if (e.target.classList.contains("post-link")) {
            showPreview(e.target);
        }
    });

    // Наведение на ссылки внутри превью (рекурсивно)
    document.addEventListener("mouseover", e => {
        previews.forEach((box, link) => {
            if (box.contains(e.target) && e.target.classList.contains("post-link")) {
                showPreview(e.target);
            }
        });
    });
});

// Общая функция для оборачивания текста с установкой курсора между тегами
function wrapText(before, after = before) {
    const textarea = document.querySelector('textarea[name="text"]');
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const selected = textarea.value.substring(start, end);
    
    // Вставляем текст с тегами
    textarea.setRangeText(before + selected + after, start, end, 'select');
    
    // Устанавливаем курсор между тегами, если нет выделенного текста
    if (selected.length === 0) {
        const newCursorPos = start + before.length;
        textarea.selectionStart = newCursorPos;
        textarea.selectionEnd = newCursorPos;
    }
    
    textarea.focus();
}

// Функции форматирования
function formatBold() {
    wrapText('[b]', '[/b]');
}

function formatItalic() {
    wrapText('[i]', '[/i]');
}

function formatSpoiler() {
    wrapText('[spoiler]', '[/spoiler]');
}

function formatStrike() {
    wrapText('[s]', '[/s]');
}

function formatUnderline() {
    wrapText('[u]', '[/u]');
}
// Специальная функция для цитирования
function wrapQuote() {
    const textarea = document.querySelector('textarea[name="text"]');
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const selected = textarea.value.substring(start, end);
    
    if (selected.includes('\n')) {
        // Для многострочного выделения - добавляем > перед каждой строкой
        const quoted = selected.split('\n').map(line => '> ' + line).join('\n');
        textarea.setRangeText(quoted, start, end, 'end');
    } else {
        // Для однострочного - просто добавляем >
        textarea.setRangeText(selected ? '> ' + selected : '> ', start, end, 'end');
    }
    textarea.focus();
}

// Функция для вставки текста без оборачивания
function insertText(text) {
    const textarea = document.querySelector('textarea[name="text"]');
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    
    textarea.setRangeText(text, start, end, 'end');
    textarea.focus();
}
</script>