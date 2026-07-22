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

    @include('advertisements._slider', ['advertisements' => $homeSliderAds])

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
                @include('tours._card')
                @if(($loop->iteration % 9 === 0 || $loop->last) && $homeInlineAds->isNotEmpty())
                    @php($slotIndex = min(intdiv($loop->iteration - 1, 9), $homeInlineAds->count() - 1))
                    @include('advertisements._banner', ['advertisement' => $homeInlineAds[$slotIndex], 'class' => 'ad-banner-grid'])
                @endif
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

@push('scripts')
    <script>
        (() => {
            const slider = document.querySelector('[data-ad-slider]');
            if (!slider) return;
            const slides = [...slider.querySelectorAll('.ad-slide')];
            const dots = [...slider.querySelectorAll('.ad-slider-dots button')];
            if (slides.length < 2) return;
            let current = 0;
            const show = index => {
                current = (index + slides.length) % slides.length;
                slides.forEach((slide, i) => slide.classList.toggle('is-active', i === current));
                dots.forEach((dot, i) => dot.classList.toggle('is-active', i === current));
            };
            dots.forEach((dot, index) => dot.addEventListener('click', () => show(index)));
            window.setInterval(() => show(current + 1), 6500);
        })();
    </script>
@endpush
