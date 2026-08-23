@props(['label', 'value', 'hint' => null, 'icon' => null])

<div {{ $attributes->merge(['class' => 'card']) }}>
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="text-sm text-ink-subtle">{{ $label }}</p>
            <p class="mt-1.5 text-2xl font-semibold text-ink tabular-nums">{{ $value }}</p>

            @if($hint)
                <p class="mt-1 text-xs text-ink-subtle">{{ $hint }}</p>
            @endif
        </div>

        @isset($icon)
            <div class="h-9 w-9 shrink-0 rounded-lg bg-primary-50 text-primary-600 flex items-center justify-center">
                {{ $icon }}
            </div>
        @endisset
    </div>
</div>
