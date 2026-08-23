/**
 * Minimal toast notification system for client-side feedback
 * (form submission results, async action confirmations, etc).
 * Server-rendered flash messages use the <x-alert> Blade component instead —
 * this module is only for JS-triggered, ephemeral messages.
 */

function ensureContainer() {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.className = 'fixed top-4 right-4 z-50 flex flex-col gap-2';
        container.setAttribute('aria-live', 'polite');
        document.body.appendChild(container);
    }
    return container;
}

const VARIANT_CLASSES = {
    info: 'alert-info',
    success: 'alert-success',
    warning: 'alert-warning',
    danger: 'alert-danger',
};

export function notify(message, variant = 'info', timeoutMs = 4000) {
    const container = ensureContainer();
    const toast = document.createElement('div');
    toast.className = `${VARIANT_CLASSES[variant] ?? VARIANT_CLASSES.info} shadow-popover w-80`;
    toast.textContent = message;
    container.appendChild(toast);

    setTimeout(() => {
        toast.remove();
    }, timeoutMs);
}

window.mciNotify = notify;
