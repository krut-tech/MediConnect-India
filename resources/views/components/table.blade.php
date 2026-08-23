@props(['headings' => []])

<div class="overflow-x-auto rounded-lg ring-1 ring-black/5">
    <table {{ $attributes->merge(['class' => 'table-base']) }}>
        @if(count($headings))
            <thead class="bg-surface-subtle">
                <tr>
                    @foreach($headings as $heading)
                        <th scope="col">{{ $heading }}</th>
                    @endforeach
                </tr>
            </thead>
        @endif

        <tbody>
            {{ $slot }}
        </tbody>
    </table>
</div>
