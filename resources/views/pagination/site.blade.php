@if ($paginator->hasPages())
    <nav class="pagination" aria-label="Pages">
        @if ($paginator->onFirstPage())
            <span class="btn" aria-disabled="true">‹ Previous</span>
        @else
            <a class="btn" href="{{ $paginator->previousPageUrl() }}" rel="prev">‹ Previous</a>
        @endif

        <span class="pages">
            @foreach ($elements as $element)
                @if (is_string($element))
                    <span aria-hidden="true">{{ $element }}</span>
                @elseif (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span aria-current="page"><span class="sr-only">Page </span>{{ $page }}</span>
                        @else
                            <a href="{{ $url }}"><span class="sr-only">Page </span>{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach
        </span>

        @if ($paginator->hasMorePages())
            <a class="btn" href="{{ $paginator->nextPageUrl() }}" rel="next">Next ›</a>
        @else
            <span class="btn" aria-disabled="true">Next ›</span>
        @endif
    </nav>
@endif
