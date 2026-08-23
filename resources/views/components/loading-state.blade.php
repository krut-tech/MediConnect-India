@props(['label' => 'Loading…'])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center gap-3 py-12 text-ink-subtle']) }} role="status">
    <svg class="h-6 w-6 animate-spin text-primary-500" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
    </svg>
    <span class="text-sm">{{ $label }}</span>
</div>
