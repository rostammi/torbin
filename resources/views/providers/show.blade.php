@extends('layouts.app')

@section('title', $providerName.' | تور، هتل، اقامتگاه و ویزا در گیت')
@section('meta')
    <link rel="canonical" href="{{ route('providers.show', $provider) }}">
    <meta name="description" content="مشاهده و مقایسه همه تورها، هتل‌ها، اقامتگاه‌ها و خدمات ویزای ارائه‌شده توسط {{ $providerName }} در گیت.">
@endsection

@section('content')
    <section class="provider-hero">
        <div class="container provider-hero-content">
            <div class="provider-monogram" aria-hidden="true">{{ mb_substr($providerName, 0, 1) }}</div>
            <div>
                <span class="eyebrow">صفحه ارائه‌دهنده</span>
                <h1>{{ $providerName }}</h1>
                <p>همه پیشنهادهای فعال {{ $providerName }} را در یک صفحه ببینید و قیمت آن‌ها را با سایر ارائه‌دهنده‌ها مقایسه کنید.</p>
            </div>
        </div>
    </section>

    <section class="container section-space provider-catalog">
        <div class="section-head">
            <div>
                <span class="eyebrow">پیشنهادهای قابل خرید</span>
                <h2>{{ $category ? $categories[$category]['plural'].' '.$providerName : 'همه خدمات '.$providerName }}</h2>
            </div>
            <span class="muted">{{ number_format($items->total()) }} پیشنهاد</span>
        </div>

        <nav class="provider-category-tabs" aria-label="دسته‌بندی پیشنهادهای ارائه‌دهنده">
            <a class="{{ $category === '' ? 'active' : '' }}" href="{{ route('providers.show', $provider) }}">
                <span>همه</span><b>{{ number_format($categoryCounts->sum()) }}</b>
            </a>
            @foreach($categories as $key => $config)
                <a class="{{ $category === $key ? 'active' : '' }}" href="{{ route('providers.show', [$provider, 'category' => $key]) }}">
                    <span>{{ $config['plural'] }}</span><b>{{ number_format($categoryCounts[$key]) }}</b>
                </a>
            @endforeach
        </nav>

        <div class="tour-grid">
            @forelse($items as $tour)
                @include('tours._card', ['showCategoryBadge' => $category === ''])
            @empty
                <div class="empty-state"><span>⌕</span><h3>پیشنهاد فعالی پیدا نشد</h3><p>{{ $providerName }} در حال حاضر در این دسته پیشنهاد دارای قیمت و اعتبار فعال ندارد.</p></div>
            @endforelse
        </div>

        {{ $items->links() }}
    </section>
@endsection
