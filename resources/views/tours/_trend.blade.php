@php
    $width = 960;
    $height = 290;
    $plotLeft = 100;
    $plotRight = 24;
    $plotTop = 20;
    $plotBottom = 60;
    $plotWidth = $width - $plotLeft - $plotRight;
    $plotHeight = $height - $plotTop - $plotBottom;
    $axisBottom = $height - $plotBottom;
    $minPrice = $trend->min('price') ?? 0;
    $maxPrice = $trend->max('price') ?? 0;
    $axisMinPrice = $minPrice === $maxPrice ? max(0, (int) floor($minPrice * 0.95)) : $minPrice;
    $axisMaxPrice = $minPrice === $maxPrice ? max($axisMinPrice + 1, (int) ceil($maxPrice * 1.05)) : $maxPrice;
    $range = max(1, $axisMaxPrice - $axisMinPrice);
    $points = $trend->map(function ($snapshot, $index) use ($trend, $plotLeft, $plotWidth, $axisBottom, $plotHeight, $axisMinPrice, $range) {
        $x = $trend->count() === 1 ? $plotLeft + ($plotWidth / 2) : $plotLeft + ($index * ($plotWidth / ($trend->count() - 1)));
        $y = $axisBottom - ((($snapshot['price'] - $axisMinPrice) / $range) * $plotHeight);

        return round($x, 1).','.round($y, 1);
    })->implode(' ');
    $priceTicks = collect([0, 0.5, 1])->map(function ($ratio) use ($axisMinPrice, $range, $axisBottom, $plotHeight) {
        return [
            'price' => (int) round($axisMinPrice + ($range * $ratio)),
            'y' => round($axisBottom - ($plotHeight * $ratio), 1),
        ];
    })->unique('price')->values();
    $dateTickIndexes = $trend->isEmpty()
        ? collect()
        : collect([0, intdiv(max(0, $trend->count() - 1), 2), $trend->count() - 1])->unique()->values();
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
            <svg class="price-chart" viewBox="0 0 {{ $width }} {{ $height }}" role="img" aria-label="نمودار کمترین قیمت تاریخی تور؛ محور افقی تاریخ و محور عمودی قیمت به تومان">
                @foreach($priceTicks as $tick)
                    <line x1="{{ $plotLeft }}" y1="{{ $tick['y'] }}" x2="{{ $width - $plotRight }}" y2="{{ $tick['y'] }}" class="chart-grid-line" />
                    <text x="{{ $plotLeft - 12 }}" y="{{ $tick['y'] + 4 }}" text-anchor="end" class="chart-tick-label">{{ number_format($tick['price']) }}</text>
                @endforeach

                <line x1="{{ $plotLeft }}" y1="{{ $plotTop }}" x2="{{ $plotLeft }}" y2="{{ $axisBottom }}" class="chart-axis" />
                <line x1="{{ $plotLeft }}" y1="{{ $axisBottom }}" x2="{{ $width - $plotRight }}" y2="{{ $axisBottom }}" class="chart-axis" />

                @foreach($dateTickIndexes as $tickIndex)
                    @php
                        $tickX = $trend->count() === 1 ? $plotLeft + ($plotWidth / 2) : $plotLeft + ($tickIndex * ($plotWidth / ($trend->count() - 1)));
                        $snapshot = $trend[$tickIndex];
                    @endphp
                    <line x1="{{ $tickX }}" y1="{{ $axisBottom }}" x2="{{ $tickX }}" y2="{{ $axisBottom + 6 }}" class="chart-axis" />
                    <text x="{{ $tickX }}" y="{{ $axisBottom + 23 }}" text-anchor="middle" class="chart-tick-label" direction="ltr">{{ $snapshot['date']->format('Y/m/d') }}</text>
                @endforeach

                <text x="{{ $plotLeft + ($plotWidth / 2) }}" y="{{ $height - 8 }}" text-anchor="middle" class="chart-axis-title">تاریخ</text>
                <text x="20" y="{{ $plotTop + ($plotHeight / 2) }}" text-anchor="middle" class="chart-axis-title" transform="rotate(-90 20 {{ $plotTop + ($plotHeight / 2) }})">قیمت (تومان)</text>

                @if($trend->count() > 1)<polyline points="{{ $points }}" class="chart-line" />@endif
                @foreach(explode(' ', $points) as $point)
                    @php
                        [$x, $y] = explode(',', $point);
                        $snapshot = $trend[$loop->index];
                    @endphp
                    <circle cx="{{ $x }}" cy="{{ $y }}" r="5" class="chart-point">
                        <title>{{ $snapshot['date']->format('Y/m/d') }} · {{ number_format($snapshot['price']) }} تومان · {{ $snapshot['provider'] }}</title>
                    </circle>
                @endforeach
            </svg>
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
