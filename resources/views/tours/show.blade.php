@extends('layouts.app')

@section('title', $tour->title . ' | مقایسه قیمت')

@section('content')
    <section class="tour-hero">
        @if ($tour->cover_image)
            <img src="{{ Storage::url($tour->cover_image) }}" alt="{{ $tour->title }}">
        @endif
        <div class="tour-hero-overlay"></div>
        <div class="container tour-hero-content">
            <a href="{{ route('home') }}" class="back-link">همه تورها ←</a>
            <h1>{{ $tour->title }}</h1>
            <p>{{ $tour->excerpt }}</p>
        </div>
    </section>

    <div class="container detail-layout section-space">
        <article class="tour-content">
            <span class="eyebrow">درباره این سفر</span>
            <h2>جزئیات تور</h2>
            <div class="prose">{!! nl2br(e($tour->description)) !!}</div>

            @if ($tour->gallery)
                <div class="gallery">
                    @foreach ($tour->gallery as $image)
                        <a href="{{ Storage::url($image) }}" target="_blank">
                            <img src="{{ Storage::url($image) }}" alt="تصویر {{ $tour->title }}">
                        </a>
                    @endforeach
                </div>
            @endif

            @if ($tour->video_url)
                <div class="video-box">
                    <h3>ویدئوی تور</h3>
                    <a class="button button-secondary" href="{{ $tour->video_url }}" target="_blank" rel="noopener">تماشای ویدئو ↗</a>
                </div>
            @endif
        </article>

        <aside class="comparison-panel">
            <div class="comparison-title">
                <span class="eyebrow">قیمت امروز</span>
                <h2>مقایسه فروشنده‌ها</h2>
                <p>{{ $tour->priceSources->count() }} پیشنهاد فعال، مرتب‌شده از ارزان‌ترین</p>
            </div>

            <div class="price-list">
                @forelse ($tour->priceSources as $index => $source)
                    <div class="price-row {{ $loop->first && $source->latest_price > 0 ? 'best-price' : '' }}">
                        @if ($loop->first && $source->latest_price > 0)<span class="best-label">بهترین قیمت</span>@endif
                        <div class="provider">
                            <span class="provider-rank">{{ $index + 1 }}</span>
                            <div>
                                <strong>{{ $source->provider_name }}</strong>
                                @if(data_get($source->latest_details, 'hotel'))<small>{{ data_get($source->latest_details, 'hotel') }}</small>@endif
                                <small>بررسی {{ optional($source->last_checked_at)->diffForHumans() ?? 'اخیر' }}</small>
                                @if($source->latest_rating !== null)
                                    <span class="rating">★ {{ number_format($source->latest_rating, 1) }} از ۵ <small>{{ $source->rating_type === 'hotel_stars' ? 'ستاره هتل' : 'امتیاز کاربران' }}@if($source->latest_rating_count) · {{ number_format($source->latest_rating_count) }} رأی@endif</small></span>
                                @else
                                    <span class="rating rating-empty">امتیاز عمومی ارائه نشده</span>
                                @endif
                            </div>
                        </div>
                        <div class="price-action">
                            @if ($source->latest_price > 0)
                                <strong>{{ number_format($source->latest_price) }} <small>{{ $source->currency }}</small></strong>
                            @else
                                <strong class="unavailable-price">۰ <small>{{ $source->currency }} · بدون تور فعال</small></strong>
                            @endif
                            <a href="{{ $source->buy_url ?: $source->source_url }}" target="_blank" rel="nofollow sponsored noopener">{{ $source->latest_price > 0 ? 'خرید تور' : 'بررسی سایت' }} ↗</a>
                        </div>
                    </div>
                @empty
                    <div class="empty-state compact"><p>هنوز قیمت معتبری برای این تور ثبت نشده است.</p></div>
                @endforelse
            </div>
            <p class="comparison-note">قیمت‌ها ممکن است در سایت فروشنده تغییر کنند؛ مبلغ نهایی را پیش از خرید بررسی کنید.</p>
        </aside>
    </div>

    <section class="container history-section">
        <div class="section-head">
            <div><span class="eyebrow">روند تغییرات</span><h2>سابقه قیمت این تور</h2></div>
            <span class="muted">۳۰ بررسی اخیر هر سایت</span>
        </div>
        <div class="history-grid">
            @foreach($tour->priceSources as $source)
                @include('tours._history', ['source' => $source])
            @endforeach
        </div>
    </section>
@endsection
