@props(['name', 'title' => null])

<div
    data-modal="{{ $name }}"
    class="hidden fixed inset-0 z-50 flex items-center justify-center p-4"
    role="dialog"
    aria-modal="true"
    aria-hidden="true"
>
    <div class="absolute inset-0 bg-ink/40"></div>

    <div class="relative w-full max-w-lg rounded-xl bg-white shadow-popover">
        <div class="flex items-center justify-between px-6 py-4 border-b border-surface-muted">
            @if($title)
                <h2 class="text-base font-semibold text-ink">{{ $title }}</h2>
            @endif
            <button type="button" data-modal-close="{{ $name }}" class="p-1.5 rounded-lg text-ink-muted hover:bg-surface-muted">
                <span class="sr-only">Close</span>
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <div class="px-6 py-4">
            {{ $slot }}
        </div>

        @isset($footer)
            <div class="flex items-center justify-end gap-2 px-6 py-4 border-t border-surface-muted">
                {{ $footer }}
            </div>
        @endisset
    </div>
</div>
