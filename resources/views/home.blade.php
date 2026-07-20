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
                @include('tours._card')
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
