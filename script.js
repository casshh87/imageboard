document.addEventListener('DOMContentLoaded', function () {
    function openModal(img) {
        const modal = document.getElementById("imageModal");
        const modalImg = document.getElementById("modalImage");
        modal.style.display = "block";
        modalImg.src = img.src;
    }

    function closeModal() {
        document.getElementById("imageModal").style.display = "none";
    }

    // Назначаем обработчики на картинки
    document.querySelectorAll('.post img').forEach(img => {
        img.addEventListener('click', () => openModal(img));
    });

    // Закрытие модального окна по клику на фон
    document.getElementById('imageModal').addEventListener('click', () => {
        closeModal();
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
