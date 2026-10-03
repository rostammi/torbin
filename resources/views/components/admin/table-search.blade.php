@props(['placeholder' => 'جست‌وجو…', 'queryKey' => 'q', 'pageKey' => 'page'])
<form method="get" class="admin-table-search" role="search">
    @foreach(request()->except([$queryKey, $pageKey]) as $key => $value)
        @if(is_scalar($value))
            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
        @endif
    @endforeach
    <label>
        <span class="sr-only">جست‌وجو در فهرست</span>
        <input type="search" name="{{ $queryKey }}" value="{{ request($queryKey) }}" placeholder="{{ $placeholder }}" maxlength="100">
    </label>
    <button class="button button-secondary compact-button" type="submit">جست‌وجو</button>
    @if(request()->filled($queryKey))
        <a class="admin-search-clear" href="{{ request()->fullUrlWithQuery([$queryKey => null, $pageKey => null]) }}">پاک‌کردن</a>
    @endif
</form>
