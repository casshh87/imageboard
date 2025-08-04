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

document.addEventListener('DOMContentLoaded', function() {
    const timerMsg = document.getElementById('timer-message');
    if (timerMsg) {
        const seconds = parseInt(timerMsg.dataset.seconds);
        updateTimer(seconds);
    }
});