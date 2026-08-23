@props(['message' => 'This screen is a design/presentation layer. Some actions are not wired to live data yet.'])

{{--
    Use on any screen where the visual UI exists but the underlying
    read/write flow isn't fully implemented — so it's never mistaken for
    finished functionality. Per project rule: never present a prototype
    screen as if it were production-ready.
--}}
<x-alert variant="warning" {{ $attributes }}>
    <span class="font-medium">Prototype notice:</span> {{ $message }}
</x-alert>
