@props(['title' => 'Something went wrong', 'description' => null])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center gap-2 py-16 text-center']) }}>
    <div class="h-12 w-12 rounded-full bg-danger-50 flex items-center justify-center text-danger-600">
        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
        </svg>
    </div>
    <h3 class="text-sm font-semibold text-ink">{{ $title }}</h3>
    @if($description)
        <p class="max-w-sm text-sm text-ink-subtle">{{ $description }}</p>
    @endif
</div>
