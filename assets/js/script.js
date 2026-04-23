// assets/js/script.js - Global JS Utilities

// ============================================================
// TOAST NOTIFICATION
// ============================================================
function showToast(message, type = 'info') {
  const toast = document.getElementById('toast');
  if (!toast) return;

  const icons = {
    success: '✅',
    error: '❌',
    warning: '⚠️',
    info: 'ℹ️'
  };

  toast.className = `toast toast-${type} show`;
  toast.innerHTML = `<span class="toast-icon">${icons[type] || '💬'}</span> <span>${message}</span>`;

  clearTimeout(toast._timeout);
  toast._timeout = setTimeout(() => {
    toast.classList.remove('show');
  }, 3500);
}
