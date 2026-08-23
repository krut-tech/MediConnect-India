@props(['variant' => 'info', 'dismissible' => false])

@php
    $classes = match ($variant) {
        'success' => 'alert-success',
        'warning' => 'alert-warning',
        'danger' => 'alert-danger',
        default => 'alert-info',
    };
@endphp

<div {{ $attributes->merge(['class' => $classes]) }} role="alert">
    <div class="flex-1">{{ $slot }}</div>

    @if($dismissible)
        <button type="button" class="shrink-0 opacity-70 hover:opacity-100" onclick="this.closest('[role=alert]').remove()">
            <span class="sr-only">Dismiss</span>
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
            </svg>
        </button>
    @endif
</div>
