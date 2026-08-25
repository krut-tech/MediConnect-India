@props(['class' => ''])

<header {{ $attributes->merge(['class' => 'sticky top-0 z-30 flex items-center justify-between gap-4 border-b border-surface-muted bg-white/80 backdrop-blur px-4 py-3 lg:px-8 ' . $class]) }}>
    <button
        type="button"
        data-mobile-nav-toggle
        aria-expanded="false"
        aria-controls="mobile-nav-panel"
        class="lg:hidden inline-flex items-center justify-center rounded-lg p-2 text-ink-muted hover:bg-surface-muted"
    >
        <span class="sr-only">Open navigation</span>
        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5" />
        </svg>
    </button>

    <div class="flex-1"></div>

    <div class="flex items-center gap-3">
        <div data-dropdown class="relative">
            <button
                type="button"
                data-dropdown-trigger
                class="flex items-center gap-2 rounded-lg px-2 py-1.5 hover:bg-surface-muted"
            >
                <div class="h-8 w-8 rounded-full bg-primary-100 text-primary-700 flex items-center justify-center text-sm font-medium">
                    {{ strtoupper(substr(auth()->user()->full_name ?? 'U', 0, 1)) }}
                </div>
                <span class="hidden sm:block text-sm font-medium text-ink">
                    {{ auth()->user()->full_name ?? 'Account' }}
                </span>
            </button>

            <div data-dropdown-panel class="hidden absolute right-0 mt-2 w-48 rounded-lg bg-white shadow-popover ring-1 ring-black/5 py-1">
                <a href="#" class="block px-4 py-2 text-sm text-ink hover:bg-surface-subtle">Profile</a>
                <a href="#" class="block px-4 py-2 text-sm text-ink hover:bg-surface-subtle">Settings</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="w-full text-left block px-4 py-2 text-sm text-danger-600 hover:bg-surface-subtle">
                        Sign out
                    </button>
                </form>
            </div>
        </div>
    </div>
</header>
