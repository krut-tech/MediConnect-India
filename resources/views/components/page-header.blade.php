@props(['title', 'subtitle' => null])

<div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
    <div>
        <h1 class="text-xl font-semibold text-ink">{{ $title }}</h1>
        @if($subtitle)
            <p class="mt-1 text-sm text-ink-muted">{{ $subtitle }}</p>
        @endif
    </div>

    @isset($actions)
        <div class="flex items-center gap-2">
            {{ $actions }}
        </div>
    @endisset
</div>
