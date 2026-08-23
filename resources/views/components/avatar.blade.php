@props(['name' => 'U', 'size' => 'md'])

@php
    $initial = strtoupper(substr(trim($name) ?: 'U', 0, 1));

    $sizeClasses = match ($size) {
        'sm' => 'h-6 w-6 text-xs',
        'lg' => 'h-12 w-12 text-base',
        default => 'h-8 w-8 text-sm',
    };
@endphp

<div {{ $attributes->merge(['class' => "rounded-full bg-primary-100 text-primary-700 flex items-center justify-center font-medium shrink-0 $sizeClasses"]) }}>
    {{ $initial }}
</div>
