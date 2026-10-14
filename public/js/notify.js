// Shows a message in a Bootstrap toast, instead of the browser's alert().
// type is a Bootstrap color: 'danger', 'warning', 'success'...
const MAX_TOASTS = 3;

function notify(message, type = 'danger') {
    const container = document.getElementById('toastContainer');
    // Keep only the most recent toasts, so they don't pile up over the page
    while (container.children.length >= MAX_TOASTS) {
        container.firstElementChild.remove();
    }

    const toast = document.getElementById('toastTemplate').content.firstElementChild.cloneNode(true);
    toast.classList.add('text-bg-' + type);
    toast.querySelector('.toast-body').textContent = message;
    if (type === 'warning') {
        // Dark close button on the light yellow background
        toast.querySelector('.btn-close').classList.remove('btn-close-white');
    }
    toast.addEventListener('hidden.bs.toast', () => toast.remove());
    container.appendChild(toast);
    // Good news go away faster than problems
    bootstrap.Toast.getOrCreateInstance(toast, {delay: type === 'success' ? 2500 : 5000}).show();
}
