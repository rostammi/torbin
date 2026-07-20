@php
    $width = 960;
    $height = 220;
    $padding = 22;
    $minPrice = $trend->min('price') ?? 0;
    $maxPrice = $trend->max('price') ?? 0;
    $range = max(1, $maxPrice - $minPrice);
    $points = $trend->map(function ($snapshot, $index) use ($trend, $width, $height, $padding, $minPrice, $range) {
        $x = $trend->count() === 1 ? $width / 2 : $padding + ($index * (($width - 2 * $padding) / ($trend->count() - 1)));
        $y = $height - $padding - ((($snapshot['price'] - $minPrice) / $range) * ($height - 2 * $padding));
        return round($x, 1).','.round($y, 1);
    })->implode(' ');
@endphp

<article class="panel history-card unified-trend">
    <div class="history-card-head">
        <div>
            <h3>روند کمترین قیمت تور</h3>
            <span>{{ $trend->count() }} روز دارای قیمت معتبر</span>
        </div>
        <div class="history-current">
            @if($trend->isNotEmpty())
                <strong>{{ number_format($trend->last()['price']) }}</strong><small>تومان</small>
            @else
                <strong>بدون داده</strong>
            @endif
        </div>
    </div>

    @if($trend->isNotEmpty())
        <div class="chart-wrap">
            <svg class="price-chart" viewBox="0 0 {{ $width }} {{ $height }}" role="img" aria-label="نمودار کمترین قیمت تاریخی تور">
                <line x1="{{ $padding }}" y1="{{ $height - $padding }}" x2="{{ $width - $padding }}" y2="{{ $height - $padding }}" class="chart-axis" />
                @if($trend->count() > 1)<polyline points="{{ $points }}" class="chart-line" />@endif
                @foreach(explode(' ', $points) as $point)
                    @php
                        [$x, $y] = explode(',', $point);
                        $snapshot = $trend[$loop->index];
                    @endphp
                    <circle cx="{{ $x }}" cy="{{ $y }}" r="5" class="chart-point">
                        <title>{{ $snapshot['day'] }} · {{ number_format($snapshot['price']) }} تومان · {{ $snapshot['provider'] }}</title>
                    </circle>
                @endforeach
            </svg>
            <div class="chart-range"><span>{{ number_format($minPrice) }}</span><span>{{ number_format($maxPrice) }} تومان</span></div>
        </div>

        <div class="snapshot-list unified-snapshots">
            @foreach($trend->sortByDesc('day')->take(7) as $snapshot)
                <div>
                    <time>{{ $snapshot['date']->format('Y/m/d') }}</time>
                    <span>{{ number_format($snapshot['price']) }} تومان</span>
                    <b>{{ $snapshot['provider'] }}</b>
                </div>
            @endforeach
        </div>
    @else
        <div class="history-empty">هنوز سابقه قیمت فعالی برای رسم نمودار ثبت نشده است.</div>
    @endif
</article>
