document.addEventListener('DOMContentLoaded', function() {
    console.log('CyberShield System Loaded');
});

function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = 'toast show';
    toast.innerHTML = `<span class="toast-icon">${type === 'success' ? '✅' : '⚠️'}</span><span>${message}</span>`;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
}