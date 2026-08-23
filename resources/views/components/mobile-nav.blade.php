<div
    id="mobile-nav-panel"
    data-mobile-nav-panel
    class="hidden fixed inset-0 z-40 lg:hidden"
    role="dialog"
    aria-modal="true"
>
    <div class="absolute inset-0 bg-ink/40" data-mobile-nav-close></div>

    <div class="absolute inset-y-0 left-0 w-72 max-w-[80%] bg-white shadow-popover flex flex-col">
        <div class="flex items-center justify-between px-4 py-4 border-b border-surface-muted">
            <div class="flex items-center gap-2">
                <div class="h-8 w-8 rounded-lg bg-primary-600 flex items-center justify-center text-white text-sm font-semibold">
                    M
                </div>
                <span class="text-sm font-semibold text-ink">MediConnect India</span>
            </div>
            <button type="button" data-mobile-nav-close class="p-2 rounded-lg text-ink-muted hover:bg-surface-muted">
                <span class="sr-only">Close navigation</span>
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-1">
            <x-sidebar-link href="{{ route('dashboard') }}" :active="request()->routeIs('dashboard')">
                Dashboard
            </x-sidebar-link>
        </nav>
    </div>
</div>
