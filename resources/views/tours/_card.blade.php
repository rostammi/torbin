<article class="tour-card">
    <a href="{{ $tour->publicUrl() }}" class="tour-card-link" aria-label="مشاهده و مقایسه قیمت {{ $tour->title }}">
        <div class="card-image">
            @if ($tour->cover_image)
                <img src="{{ Storage::url($tour->cover_image) }}" alt="مقایسه قیمت {{ $tour->title }}" width="1280" height="720" loading="lazy" fetchpriority="low" decoding="async">
            @else
                <div class="image-placeholder">{{ mb_substr($tour->title, 0, 1) }}</div>
            @endif
            <span class="source-badge">مقایسه {{ $tour->compared_sources_count }} سایت</span>
            @if($showCategoryBadge ?? false)<span class="category-badge">{{ $tour->categoryLabel() }}</span>@endif
        </div>
        <div class="card-body">
            <h3>{{ $tour->title }}</h3>
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
                <span class="card-price-link">مشاهده قیمت‌ها</span>
            </div>
        </div>
    </a>
</article>
