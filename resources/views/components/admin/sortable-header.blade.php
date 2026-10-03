@props(['column', 'label', 'sortKey' => 'sort', 'directionKey' => 'direction', 'pageKey' => 'page'])
@php
    $active = request($sortKey) === $column;
    $nextDirection = $active && request($directionKey) === 'asc' ? 'desc' : 'asc';
    $url = request()->fullUrlWithQuery([$sortKey => $column, $directionKey => $nextDirection, $pageKey => null]);
@endphp
<th {{ $attributes->class(['sortable-column', 'is-sorted' => $active]) }} scope="col" aria-sort="{{ $active ? (request($directionKey) === 'asc' ? 'ascending' : 'descending') : 'none' }}">
    <a href="{{ $url }}">
        <span>{{ $label }}</span>
        <span class="sort-indicator" aria-hidden="true">{{ $active ? (request($directionKey) === 'asc' ? '▲' : '▼') : '↕' }}</span>
    </a>
</th>
