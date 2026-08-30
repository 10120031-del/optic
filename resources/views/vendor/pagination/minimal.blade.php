@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="flex items-center justify-between gap-4 py-6">
        <div class="flex-1">
            @if ($paginator->onFirstPage())
                <span class="btn-outline-accent btn-sm opacity-40 pointer-events-none">&larr; {{ __('Previous') }}</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="btn-outline-accent btn-sm" rel="prev">&larr; {{ __('Previous') }}</a>
            @endif
        </div>

        <p class="eyebrow whitespace-nowrap">
            {{ __('Page') }} {{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}
        </p>

        <div class="flex flex-1 justify-end">
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="btn-outline-accent btn-sm" rel="next">{{ __('Next') }} &rarr;</a>
            @else
                <span class="btn-outline-accent btn-sm opacity-40 pointer-events-none">{{ __('Next') }} &rarr;</span>
            @endif
        </div>
    </nav>
@endif
