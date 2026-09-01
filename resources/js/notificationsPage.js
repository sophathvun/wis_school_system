document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.notification-message-content a[href]').forEach(link => {
        link.target = '_blank';
        link.rel = 'noopener noreferrer';
    });
});
