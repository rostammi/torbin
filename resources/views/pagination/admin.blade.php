@if ($paginator->hasPages())
    <nav class="admin-pagination" role="navigation" aria-label="صفحه‌بندی نتایج">
        <p>نمایش {{ number_format($paginator->firstItem()) }} تا {{ number_format($paginator->lastItem()) }} از {{ number_format($paginator->total()) }} نتیجه</p>
        <div>
            @if ($paginator->onFirstPage())
                <span class="pagination-disabled">قبلی</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev">قبلی</a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="pagination-ellipsis">…</span>
                @endif
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page === $paginator->currentPage())
                            <span class="pagination-current" aria-current="page">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next">بعدی</a>
            @else
                <span class="pagination-disabled">بعدی</span>
            @endif
        </div>
    </nav>
@endif
