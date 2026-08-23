@props(['rows' => 3])

<div {{ $attributes->merge(['class' => 'animate-pulse space-y-3']) }} role="status" aria-label="Loading">
    @for($i = 0; $i < $rows; $i++)
        <div class="h-12 rounded-lg bg-surface-muted"></div>
    @endfor
    <span class="sr-only">Loading…</span>
</div>
