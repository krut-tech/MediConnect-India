/**
 * Mobile navigation drawer controller.
 *
 * Markup contract:
 *   <button data-mobile-nav-toggle>...</button>
 *   <div data-mobile-nav-panel class="hidden">...</div>
 */

document.addEventListener('click', (event) => {
    const toggle = event.target.closest('[data-mobile-nav-toggle]');
    if (toggle) {
        const panel = document.querySelector('[data-mobile-nav-panel]');
        panel?.classList.toggle('hidden');
        const expanded = !panel?.classList.contains('hidden');
        toggle.setAttribute('aria-expanded', String(expanded));
        return;
    }

    const closeTrigger = event.target.closest('[data-mobile-nav-close]');
    if (closeTrigger) {
        document.querySelector('[data-mobile-nav-panel]')?.classList.add('hidden');
    }
});
