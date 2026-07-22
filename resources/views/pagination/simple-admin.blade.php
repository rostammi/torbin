@if ($paginator->hasPages())
    <nav class="admin-pagination simple-pagination" role="navigation" aria-label="صفحه‌بندی نتایج">
        <div>
            @if ($paginator->onFirstPage())
                <span class="pagination-disabled">قبلی</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev">قبلی</a>
            @endif

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next">بعدی</a>
            @else
                <span class="pagination-disabled">بعدی</span>
            @endif
        </div>
    </nav>
@endif
