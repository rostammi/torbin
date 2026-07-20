@extends('layouts.app')

@section('title', 'توربین | مقایسه قیمت تورها')

@section('content')
    <section class="hero">
        <div class="container hero-content">
            <div>
                <span class="eyebrow">سفر بهتر، با قیمت بهتر</span>
                <h1>قیمت تورها را یک‌جا<br>مقایسه کنید</h1>
                <p>به‌جای گشتن بین ده‌ها سایت، پیشنهادها را از ارزان‌ترین تا گران‌ترین ببینید و مستقیم خرید کنید.</p>
            </div>
            <div class="hero-orbit" aria-hidden="true"><span>✈</span></div>
        </div>
    </section>

    <section class="container section-space">
        <div class="section-head">
            <div>
                <span class="eyebrow">مقصد بعدی</span>
                <h2>تورهای قابل مقایسه</h2>
            </div>
            <span class="muted">{{ number_format($tours->total()) }} تور</span>
        </div>

        <div class="tour-grid">
            @forelse ($tours as $tour)
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
            @empty
                <div class="empty-state">
                    <span>🧭</span>
                    <h3>هنوز توری منتشر نشده است</h3>
                    <p>به‌زودی پیشنهادهای سفر اینجا نمایش داده می‌شوند.</p>
                </div>
            @endforelse
        </div>

        {{ $tours->links() }}
    </section>
@endsection
