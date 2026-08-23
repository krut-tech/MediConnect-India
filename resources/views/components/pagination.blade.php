@props(['paginator'])

@if($paginator->hasPages())
    <nav class="flex items-center justify-between border-t border-surface-muted px-4 py-3 sm:px-0" aria-label="Pagination">
        <div class="flex flex-1 justify-between sm:hidden">
            @if($paginator->onFirstPage())
                <span class="btn-secondary opacity-50">Previous</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="btn-secondary">Previous</a>
            @endif

            @if($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="btn-secondary">Next</a>
            @else
                <span class="btn-secondary opacity-50">Next</span>
            @endif
        </div>

        <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
            <p class="text-sm text-ink-muted">
                Showing <span class="font-medium">{{ $paginator->firstItem() }}</span>
                to <span class="font-medium">{{ $paginator->lastItem() }}</span>
                of <span class="font-medium">{{ $paginator->total() }}</span> results
            </p>

            <div class="flex gap-1">
                @foreach($paginator->getUrlRange(1, $paginator->lastPage()) as $page => $url)
                    <a
                        href="{{ $url }}"
                        @class([
                            'rounded-lg px-3 py-1.5 text-sm font-medium',
                            'bg-primary-600 text-white' => $page === $paginator->currentPage(),
                            'text-ink-muted hover:bg-surface-muted' => $page !== $paginator->currentPage(),
                        ])
                    >
                        {{ $page }}
                    </a>
                @endforeach
            </div>
        </div>
    </nav>
@endif
