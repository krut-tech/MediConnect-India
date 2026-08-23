/**
 * Lightweight dropdown controller.
 *
 * Markup contract:
 *   <div data-dropdown>
 *     <button data-dropdown-trigger>...</button>
 *     <div data-dropdown-panel class="hidden">...</div>
 *   </div>
 */

function closeAllDropdowns(except = null) {
    document.querySelectorAll('[data-dropdown-panel]').forEach((panel) => {
        if (panel !== except) panel.classList.add('hidden');
    });
}

document.addEventListener('click', (event) => {
    const trigger = event.target.closest('[data-dropdown-trigger]');
    if (trigger) {
        const wrapper = trigger.closest('[data-dropdown]');
        const panel = wrapper?.querySelector('[data-dropdown-panel]');
        if (!panel) return;
        const willOpen = panel.classList.contains('hidden');
        closeAllDropdowns();
        if (willOpen) panel.classList.remove('hidden');
        return;
    }

    if (!event.target.closest('[data-dropdown-panel]')) {
        closeAllDropdowns();
    }
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') closeAllDropdowns();
});
