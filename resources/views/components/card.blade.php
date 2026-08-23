@props(['title' => null])

<div {{ $attributes->merge(['class' => 'card']) }}>
    @if($title || isset($actions))
        <div class="card-header">
            @if($title)
                <h2 class="text-base font-semibold text-ink">{{ $title }}</h2>
            @endif

            @isset($actions)
                <div class="flex items-center gap-2">
                    {{ $actions }}
                </div>
            @endisset
        </div>
    @endif

    {{ $slot }}
</div>
