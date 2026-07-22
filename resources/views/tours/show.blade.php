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

            @if ($tour->image_sources)
                <details class="image-credits">
                    <summary>منبع و مجوز تصاویر</summary>
                    <ul>
                        @foreach ($tour->image_sources as $source)
                            <li>
                                <a href="{{ data_get($source, 'page_url') }}" target="_blank" rel="noopener noreferrer">{{ data_get($source, 'artist', 'Wikimedia Commons') }}</a>
                                <span>—</span>
                                @if (data_get($source, 'license_url'))
                                    <a href="{{ data_get($source, 'license_url') }}" target="_blank" rel="license noopener noreferrer">{{ data_get($source, 'license', 'مجوز آزاد') }}</a>
                                @else
                                    <span>{{ data_get($source, 'license', 'مجوز آزاد') }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </details>
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
                <p>{{ $tour->priceSources->count() }} پیشنهاد دارای قیمت، مرتب‌شده از ارزان‌ترین</p>
            </div>

            <div class="price-list">
                @forelse ($tour->priceSources as $index => $source)
                    <div class="price-row {{ $loop->first && $source->latest_price > 0 ? 'best-price' : '' }} {{ $source->is_featured ? 'has-featured' : '' }}">
                        @if ($loop->first && $source->latest_price > 0)<span class="best-label">بهترین قیمت</span>@endif
                        @if ($source->is_featured)<span class="special-offer-badge">پیشنهاد ویژه</span>@endif
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
                            <strong>{{ number_format($source->latest_price) }} <small>{{ $source->currency }}</small></strong>
                            @if(!$source->agency || $source->agency->canAffordClick())
                                <a href="{{ route('outbound.click', $source) }}" target="_blank" rel="nofollow sponsored noopener">خرید تور ↗</a>
                            @else
                                <span class="buy-disabled">اعتبار ارائه‌دهنده کافی نیست</span>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="empty-state compact"><p>هنوز قیمت معتبری برای این تور ثبت نشده است.</p></div>
                @endforelse
            </div>
            <p class="comparison-note">قیمت‌ها ممکن است در سایت فروشنده تغییر کنند؛ مبلغ نهایی را پیش از خرید بررسی کنید.</p>
            @if($offersBottomAd)
                @include('advertisements._banner', ['advertisement' => $offersBottomAd, 'class' => 'tour-offers-ad'])
            @endif
            @php($alertOffer = $tour->priceSources->first(fn ($item) => $item->latest_price > 0))
            @if($alertOffer)
                <div class="price-alert-box">
                    <span class="eyebrow">هنوز گران است؟</span>
                    <h3>خبرم کن ارزان‌تر شد</h3>
                    <p>اگر قیمت این تور از {{ number_format($alertOffer->latest_price) }} {{ $alertOffer->currency }} کمتر شد، پیام می‌دهیم.</p>
                    <form action="{{ route('price-alerts.store', $tour) }}" method="post">
                        @csrf
                        <div class="alert-phone-row">
                            <input type="tel" name="phone" dir="ltr" inputmode="numeric" autocomplete="tel" value="{{ old('phone') }}" placeholder="09123456789" required>
                            <button class="button" type="submit">فعال‌کردن هشدار</button>
                        </div>
                        @error('phone')<small class="field-error">{{ $message }}</small>@enderror
                        <label class="alert-consent"><input type="checkbox" name="consent" value="1" required> با دریافت پیامک کاهش قیمت و امکان لغو آن موافقم.</label>
                        @error('consent')<small class="field-error">{{ $message }}</small>@enderror
                    </form>
                </div>
            @endif
        </aside>
    </div>

    @if(data_get($tour->auto_content, 'topics'))
        <section class="container auto-guide">
            <div class="section-head">
                <div><span class="eyebrow">برگرفته از منابع مقایسه‌شده</span><h2>راهنمای تکمیلی {{ $tour->title }}</h2></div>
                @if($tour->auto_content_updated_at)<span class="muted">به‌روزرسانی {{ $tour->auto_content_updated_at->diffForHumans() }}</span>@endif
            </div>
            <p class="auto-guide-intro">موضوعات زیر با بررسی محتوای عمومی سایت‌های ارائه‌دهنده شناسایی شده‌اند. برای جزئیات هر موضوع می‌توانید منبع مرتبط را ببینید.</p>
            <div class="topic-grid">
                @foreach(data_get($tour->auto_content, 'topics', []) as $topic)
                    <article class="topic-card">
                        <h3>{{ $topic['title'] }}</h3>
                        <div class="topic-sources">
                            @foreach($topic['sources'] ?? [] as $contentSource)
                                <a href="{{ $contentSource['url'] }}" target="_blank" rel="nofollow noopener">{{ $contentSource['name'] }} ↗</a>
                            @endforeach
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    <section class="container history-section">
        @if($trendTopAd)
            @include('advertisements._banner', ['advertisement' => $trendTopAd, 'class' => 'trend-top-ad'])
        @endif
        <div class="section-head">
            <div><span class="eyebrow">روند تغییرات</span><h2>سابقه قیمت این تور</h2></div>
            <span class="muted">کمترین قیمت در ۳۰ روز دارای داده اخیر</span>
        </div>
        @include('tours._trend', ['trend' => $priceTrend])
    </section>
@endsection
