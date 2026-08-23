@props(['href', 'active' => false])

<a
    href="{{ $href }}"
    @class([
        'flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors',
        'bg-primary-50 text-primary-700' => $active,
        'text-ink-muted hover:bg-surface-muted hover:text-ink' => ! $active,
    ])
>
    {{ $slot }}
</a>
