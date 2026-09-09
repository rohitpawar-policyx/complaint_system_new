@props(['paginator'])
@if ($paginator->hasPages())
    <nav class="pagination" aria-label="Pagination">
        @if ($paginator->onFirstPage())
        @else
            <a href="{{ $paginator->previousPageUrl() }}"><x-icon name="arrow-left" /> Previous</a>
        @endif
        <span>Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}</span>
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}">Next <x-icon name="arrow-right" /></a>
        @endif
    </nav>
@endif
