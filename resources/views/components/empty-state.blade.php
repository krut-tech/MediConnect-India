@props(['title' => 'Nothing here yet', 'description' => null])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center gap-2 py-16 text-center']) }}>
    <div class="h-12 w-12 rounded-full bg-surface-muted flex items-center justify-center text-ink-subtle">
        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 9.75h16.5M3.75 9.75a2.25 2.25 0 012.25-2.25h12a2.25 2.25 0 012.25 2.25m-16.5 0v7.5A2.25 2.25 0 006 19.5h12a2.25 2.25 0 002.25-2.25v-7.5" />
        </svg>
    </div>
    <h3 class="text-sm font-semibold text-ink">{{ $title }}</h3>
    @if($description)
        <p class="max-w-sm text-sm text-ink-subtle">{{ $description }}</p>
    @endif

    @isset($action)
        <div class="mt-2">{{ $action }}</div>
    @endisset
</div>
