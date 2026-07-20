<article class="tour-card">
    <a href="{{ route('tours.show', $tour) }}" class="card-image">
        @if ($tour->cover_image)
            <img src="{{ Storage::url($tour->cover_image) }}" alt="{{ $tour->title }}">
        @else
            <div class="image-placeholder">{{ mb_substr($tour->title, 0, 1) }}</div>
        @endif
        <span class="source-badge">مقایسه {{ $tour->compared_sources_count }} سایت</span>
    </a>
    <div class="card-body">
        <h3><a href="{{ route('tours.show', $tour) }}">{{ $tour->title }}</a></h3>
        <p>{{ $tour->excerpt ?: Str::limit(strip_tags($tour->description), 95) }}</p>
        <div class="card-footer">
            <div>
                <span class="price-label">ارزان‌ترین قیمت</span>
                @if ($tour->minimum_price)
                    <strong>{{ number_format($tour->minimum_price) }} <small>تومان</small></strong>
                @elseif ($tour->compared_sources_count)
                    <strong>۰ <small>تومان · ناموجود</small></strong>
                @else
                    <strong class="pending">در حال بررسی</strong>
                @endif
            </div>
            <a class="circle-link" href="{{ route('tours.show', $tour) }}" aria-label="مشاهده">←</a>
        </div>
    </div>
</article>
