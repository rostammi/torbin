@extends('layouts.app')

@section('title', $categoryConfig ? 'مقایسه قیمت '.$categoryConfig['plural'].' | گیت' : 'گیت | مقایسه خدمات سفر')

@section('content')
    <section class="hero">
        <div class="container hero-content">
            <div>
                <span class="eyebrow">{{ $categoryConfig ? $categoryConfig['plural'] : 'سفر بهتر، با قیمت بهتر' }}</span>
                <h1>{{ $categoryConfig['hero'] ?? 'خدمات سفر را یک‌جا مقایسه کنید' }}</h1>
                <p>به‌جای گشتن بین ده‌ها سایت، پیشنهادهای معتبر را کنار هم ببینید و مستقیم از ارائه‌دهنده خرید کنید.</p>
            </div>
            <div class="hero-orbit" aria-hidden="true"><span>✈</span></div>
        </div>
    </section>

    @include('advertisements._slider', ['advertisements' => $homeSliderAds])

    @if($categoryConfig)
        <section class="container section-space">
            <div class="section-head">
                <div><span class="eyebrow">{{ $categoryConfig['label'] }}</span><h2>{{ $categoryConfig['plural'] }} قابل مقایسه</h2></div>
                <span class="muted">{{ number_format($tours->total()) }} {{ $categoryConfig['label'] }}</span>
            </div>
            <div class="tour-grid">
                @forelse ($tours as $tour)
                    @include('tours._card')
                @empty
                    <div class="empty-state"><span>{{ $categoryConfig['icon'] }}</span><h3>هنوز موردی منتشر نشده است</h3><p>پیشنهادهای معتبر این دسته در حال بررسی هستند.</p></div>
                @endforelse
            </div>
            {{ $tours->links() }}
        </section>
    @else
        @foreach($categorySections as $section)
            <section class="container category-home-section {{ $loop->first ? 'section-space' : '' }}">
                <div class="section-head">
                    <div><span class="eyebrow">{{ $section['config']['label'] }}</span><h2>پیشنهادهای {{ $section['config']['plural'] }}</h2></div>
                    <a class="button button-secondary" href="{{ route($section['config']['route'].'.index') }}">مشاهده همه {{ $section['config']['plural'] }} ←</a>
                </div>
                <div class="tour-grid">
                    @forelse($section['items'] as $tour)
                        @include('tours._card')
                    @empty
                        <div class="empty-state"><span>{{ $section['config']['icon'] }}</span><h3>در حال آماده‌سازی</h3><p>پیشنهادهای این دسته پس از تکمیل قیمت و تصویر نمایش داده می‌شوند.</p></div>
                    @endforelse
                </div>
                @if($homeInlineAds->has($loop->index))
                    @include('advertisements._banner', ['advertisement' => $homeInlineAds[$loop->index], 'class' => 'ad-banner-grid'])
                @endif
            </section>
        @endforeach
    @endif
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
