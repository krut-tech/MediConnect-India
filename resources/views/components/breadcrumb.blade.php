@props(['items' => []])

{{--
    $items = [['label' => 'Facilities', 'href' => '...'], ['label' => 'Apollo Clinic']]
    Last item (no href, or the array's final entry) renders as the current page.
--}}
<nav aria-label="Breadcrumb" {{ $attributes->merge(['class' => 'flex']) }}>
    <ol class="flex flex-wrap items-center gap-1.5 text-sm text-ink-subtle">
        @foreach($items as $index => $item)
            <li class="flex items-center gap-1.5">
                @if($index > 0)
                    <svg class="h-3.5 w-3.5 text-ink-subtle/60" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m9 6 6 6-6 6" />
                    </svg>
                @endif

                @if(!empty($item['href']) && $index !== count($items) - 1)
                    <a href="{{ $item['href'] }}" class="hover:text-ink transition-colors">{{ $item['label'] }}</a>
                @else
                    <span class="text-ink font-medium" @if($index === count($items) - 1) aria-current="page" @endif>{{ $item['label'] }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
