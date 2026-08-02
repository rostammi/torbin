@extends('layouts.app')

@section('title', $term ? 'نتایج جست‌وجوی '.$term : 'جست‌وجوی تور')

@section('content')
    <section class="container admin-page search-page">
        <div class="section-head">
            <div><span class="eyebrow">پیدا کردن سفر</span><h1>{{ $intent->isRecommendation ? 'پیشنهادهای متناسب با بودجه شما' : 'نتایج جست‌وجو' }}</h1></div>
            @if($tours)<span class="muted">{{ number_format($tours->total()) }} نتیجه برای «{{ $term }}»</span>@endif
        </div>

        @if($searchTopAd)
            @include('advertisements._banner', ['advertisement' => $searchTopAd, 'class' => 'search-top-ad'])
        @endif

        <form class="search-page-form" action="{{ route('search.index') }}" method="get">
            <input type="search" name="q" value="{{ $term }}" placeholder="مثلاً: سفر داخلی با ۴ میلیون کجا برم؟" minlength="3" required autofocus>
            <button class="button">جست‌وجو</button>
        </form>

        @if($intent->isRecommendation)
            <div class="search-intent-summary">
                <strong>فیلترهای تشخیص‌داده‌شده:</strong>
                @if($intent->regionLabel())<span>{{ $intent->regionLabel() }}</span>@endif
                @if($intent->destination)<span>مقصد: {{ $intent->destination }}</span>@endif
                @if($intent->maximumBudget)<span>حداکثر {{ number_format($intent->maximumBudget) }} تومان</span>@endif
            </div>
        @endif

        @if(mb_strlen($term) > 0 && mb_strlen($term) < 3)
            <div class="validation-errors">برای جست‌وجو حداقل ۳ کاراکتر وارد کنید.</div>
        @elseif($tours)
            <div class="tour-grid search-tour-grid">
                @forelse($tours as $tour)
                    @include('tours._card')
                    @if($loop->iteration === 3 && $searchResultAd)
                        @include('advertisements._card', ['advertisement' => $searchResultAd])
                    @endif
                @empty
                    <div class="empty-state"><span>⌕</span><h3>نتیجه‌ای پیدا نشد</h3><p>{{ $intent->isRecommendation ? 'در حال حاضر تور فعال و دارای قیمت با این شرایط نداریم؛ بودجه یا نوع سفر را تغییر دهید.' : 'عبارت دیگری مثل نام شهر یا آژانس را امتحان کنید.' }}</p></div>
                @endforelse
            </div>
            @if($tours->count() < 3 && $searchResultAd)
                <div class="search-single-ad">@include('advertisements._banner', ['advertisement' => $searchResultAd])</div>
            @endif
            {{ $tours->links() }}
        @endif
    </section>
@endsection
