@php
    $snapshots = $source->recentHistory->sortBy('observed_at')->values();
    $priced = $snapshots->where('price', '>', 0)->values();
    $width = 720;
    $height = 180;
    $padding = 18;
    $minPrice = $priced->min('price') ?? 0;
    $maxPrice = $priced->max('price') ?? 0;
    $range = max(1, $maxPrice - $minPrice);
    $points = $priced->map(function ($snapshot, $index) use ($priced, $width, $height, $padding, $minPrice, $range) {
        $x = $priced->count() === 1 ? $width / 2 : $padding + ($index * (($width - 2 * $padding) / ($priced->count() - 1)));
        $y = $height - $padding - ((($snapshot->price - $minPrice) / $range) * ($height - 2 * $padding));
        return round($x, 1).','.round($y, 1);
    })->implode(' ');
@endphp

<article class="panel history-card">
    <div class="history-card-head">
        <div>
            <h3>{{ $source->provider_name }}</h3>
            <span>{{ $snapshots->count() }} بار ثبت قیمت</span>
        </div>
        <div class="history-current">
            @if($source->latest_price > 0)
                <strong>{{ number_format($source->latest_price) }}</strong><small>{{ $source->currency }}</small>
            @else
                <strong>ناموجود</strong>
            @endif
        </div>
    </div>

    @if($priced->isNotEmpty())
        <div class="chart-wrap">
            <svg class="price-chart" viewBox="0 0 {{ $width }} {{ $height }}" role="img" aria-label="نمودار سابقه قیمت {{ $source->provider_name }}">
                <line x1="{{ $padding }}" y1="{{ $height - $padding }}" x2="{{ $width - $padding }}" y2="{{ $height - $padding }}" class="chart-axis" />
                @if($priced->count() > 1)<polyline points="{{ $points }}" class="chart-line" />@endif
                @foreach(explode(' ', $points) as $point)
                    @php
                        [$x, $y] = explode(',', $point);
                    @endphp
                    <circle cx="{{ $x }}" cy="{{ $y }}" r="5" class="chart-point" />
                @endforeach
            </svg>
            <div class="chart-range"><span>{{ number_format($minPrice) }}</span><span>{{ number_format($maxPrice) }} {{ $source->currency }}</span></div>
        </div>
    @else
        <div class="history-empty">هنوز قیمت فعالی برای رسم نمودار ثبت نشده است.</div>
    @endif

    <div class="snapshot-list">
        @foreach($snapshots->sortByDesc('observed_at')->take(5) as $snapshot)
            <div>
                <time>{{ $snapshot->observed_at->format('Y/m/d H:i') }}</time>
                <span>{{ $snapshot->is_available ? number_format($snapshot->price).' '.$source->currency : 'بدون تور فعال' }}</span>
                @if($snapshot->rating !== null)<b>★ {{ number_format($snapshot->rating, 1) }}</b>@endif
            </div>
        @endforeach
    </div>
</article>
