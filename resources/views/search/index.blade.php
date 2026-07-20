@extends('layouts.app')

@section('title', $term ? 'نتایج جست‌وجوی '.$term : 'جست‌وجوی تور')

@section('content')
    <section class="container admin-page search-page">
        <div class="section-head">
            <div><span class="eyebrow">پیدا کردن سفر</span><h1>نتایج جست‌وجو</h1></div>
            @if($tours)<span class="muted">{{ number_format($tours->total()) }} نتیجه برای «{{ $term }}»</span>@endif
        </div>

        <form class="search-page-form" action="{{ route('search.index') }}" method="get">
            <input type="search" name="q" value="{{ $term }}" placeholder="نام تور، آژانس یا بخشی از توضیحات" minlength="3" required autofocus>
            <button class="button">جست‌وجو</button>
        </form>

        @if(mb_strlen($term) > 0 && mb_strlen($term) < 3)
            <div class="validation-errors">برای جست‌وجو حداقل ۳ کاراکتر وارد کنید.</div>
        @elseif($tours)
            <div class="tour-grid search-tour-grid">
                @forelse($tours as $tour)
                    @include('tours._card')
                @empty
                    <div class="empty-state"><span>⌕</span><h3>نتیجه‌ای پیدا نشد</h3><p>عبارت دیگری مثل نام شهر یا آژانس را امتحان کنید.</p></div>
                @endforelse
            </div>
            {{ $tours->links() }}
        @endif
    </section>
@endsection
