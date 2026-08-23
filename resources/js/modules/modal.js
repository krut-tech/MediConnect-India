/**
 * Lightweight modal controller.
 *
 * Markup contract (see resources/views/components/modal.blade.php):
 *   <div data-modal="example" class="hidden" ...> ... </div>
 *   <button data-modal-open="example">Open</button>
 *   <button data-modal-close="example">Close</button>
 *
 * No dependencies. Traps focus loosely (first focusable element) and closes
 * on Escape or backdrop click.
 */

function getModal(name) {
    return document.querySelector(`[data-modal="${name}"]`);
}

function openModal(name) {
    const modal = getModal(name);
    if (!modal) return;
    modal.classList.remove('hidden');
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('overflow-hidden');
    const focusable = modal.querySelector('[data-autofocus], input, button, textarea, select');
    focusable?.focus();
}

function closeModal(name) {
    const modal = getModal(name);
    if (!modal) return;
    modal.classList.add('hidden');
    modal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('overflow-hidden');
}

document.addEventListener('click', (event) => {
    const openTrigger = event.target.closest('[data-modal-open]');
    if (openTrigger) {
        openModal(openTrigger.getAttribute('data-modal-open'));
        return;
    }

    const closeTrigger = event.target.closest('[data-modal-close]');
    if (closeTrigger) {
        closeModal(closeTrigger.getAttribute('data-modal-close'));
        return;
    }

    // Backdrop click (the modal root itself, not its inner panel).
    const modalRoot = event.target.closest('[data-modal]');
    if (modalRoot && event.target === modalRoot) {
        closeModal(modalRoot.getAttribute('data-modal'));
    }
});

document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;
    const openModalEl = document.querySelector('[data-modal]:not(.hidden)');
    if (openModalEl) {
        closeModal(openModalEl.getAttribute('data-modal'));
    }
});

export { openModal, closeModal };
