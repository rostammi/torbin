@extends('layouts.app')

@section('title', 'داشبورد عملکرد تورها')

@section('content')
    <section class="container admin-page">
        <div class="section-head dashboard-heading">
            <div>
                <span class="eyebrow">تحلیل عملکرد</span>
                <h1>{{ $selectedAgency ? 'داشبورد '.$selectedAgency->name : 'داشبورد کل آژانس‌ها' }}</h1>
            </div>
            <form class="dashboard-filters" method="get">
                @if(auth()->user()->isAdmin())
                    <select name="agency_id">
                        <option value="">همه آژانس‌ها</option>
                        @foreach($agencies as $agency)<option value="{{ $agency->id }}" @selected((string)request('agency_id') === (string)$agency->id)>{{ $agency->name }}</option>@endforeach
                    </select>
                @endif
                <select name="period">
                    <option value="1" @selected($period === '1')>۲۴ ساعت اخیر</option>
                    <option value="7" @selected($period === '7')>۷ روز اخیر</option>
                    <option value="30" @selected($period === '30')>۳۰ روز اخیر</option>
                    <option value="all" @selected($period === 'all')>کل دوره</option>
                </select>
                <button class="button button-secondary">اعمال فیلتر</button>
            </form>
        </div>

        <div class="dashboard-kpis">
            <article class="panel"><span>نمایش صفحات تور</span><strong>{{ number_format($viewsTotal) }}</strong></article>
            <article class="panel"><span>کلیک خروجی موفق</span><strong>{{ number_format($clicksTotal) }}</strong></article>
            <article class="panel"><span>نرخ تبدیل کلیک</span><strong>{{ number_format($conversionTotal, 2) }}٪</strong></article>
            <article class="panel"><span>هزینه کلیک‌ها</span><strong>{{ number_format($costTotal) }} <small>تومان</small></strong></article>
        </div>

        <div class="panel table-wrap dashboard-table">
            <table>
                <thead><tr><th>تور</th><th>کمترین قیمت سایت</th><th>قیمت آژانس</th><th>فاصله با کمترین قیمت</th><th>نمایش صفحه</th><th>کلیک خرید</th><th>کانورژن</th><th>هزینه برای آژانس</th></tr></thead>
                <tbody>
                    @forelse($tours as $tour)
                        @php
                            $conversion = $tour->views_count > 0 ? ($tour->clicks_count / $tour->views_count) * 100 : 0;
                            $priceGap = isset($tour->agency_price) && $tour->public_minimum_price
                                ? $tour->agency_price - $tour->public_minimum_price
                                : null;
                            $priceGapPercent = $priceGap !== null && $tour->public_minimum_price > 0
                                ? (abs($priceGap) / $tour->public_minimum_price) * 100
                                : null;
                        @endphp
                        <tr>
                            <td><strong>{{ $tour->title }}</strong><small><a href="{{ route('tours.show', $tour) }}" target="_blank">مشاهده صفحه ↗</a></small></td>
                            <td>{{ $tour->public_minimum_price ? number_format($tour->public_minimum_price).' تومان' : 'بدون قیمت فعال' }}</td>
                            <td>
                                @if(isset($tour->agency_price))
                                    {{ number_format($tour->agency_price) }} تومان
                                @elseif(auth()->user()->isAdmin())
                                    <small class="muted">یک آژانس را فیلتر کنید</small>
                                @else
                                    بدون قیمت فعال
                                @endif
                            </td>
                            <td>
                                @if($priceGap !== null)
                                    <strong class="price-gap {{ $priceGap <= 0 ? 'is-best' : '' }}">
                                        @if($priceGap === 0) هم‌قیمت با کمترین پیشنهاد
                                        @elseif($priceGap > 0) {{ number_format($priceGap) }} تومان بالاتر
                                        @else {{ number_format(abs($priceGap)) }} تومان پایین‌تر
                                        @endif
                                    </strong>
                                    @if($priceGap !== 0)<small>{{ number_format($priceGapPercent, 2) }}٪ {{ $priceGap > 0 ? 'بالاتر' : 'پایین‌تر' }}</small>@endif
                                    @if($selectedAgency && $selectedAgency->balance <= 0)<small class="price-hidden-note">به‌دلیل اعتبار صفر در مقایسه نمایش داده نمی‌شود.</small>@endif
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ number_format($tour->views_count) }}</td>
                            <td>{{ number_format($tour->clicks_count) }}</td>
                            <td>{{ number_format($conversion, 2) }}٪</td>
                            <td>{{ number_format($tour->click_cost ?? 0) }} تومان</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="empty-cell">هنوز توری برای نمایش در این داشبورد وجود ندارد.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if(auth()->user()->isAdmin())
            <section class="potential-keywords">
                <div class="subsection-head">
                    <div><span class="eyebrow">فرصت توسعه محصول</span><h2>کیوردهای دارای پتانسیل اجرای تور</h2></div>
                    <span class="muted">جست‌وجوهایی که هیچ نتیجه‌ای نداشته‌اند</span>
                </div>
                <div class="panel table-wrap">
                    <table>
                        <thead><tr><th>عبارت جست‌وجو</th><th>تعداد جست‌وجو</th><th>کاربران تقریبی</th><th>آخرین جست‌وجو</th></tr></thead>
                        <tbody>
                            @forelse($potentialKeywords as $keyword)
                                <tr>
                                    <td><strong>{{ $keyword->keyword }}</strong></td>
                                    <td>{{ number_format($keyword->searches_count) }}</td>
                                    <td>{{ number_format($keyword->visitors_count) }}</td>
                                    <td>{{ \Illuminate\Support\Carbon::parse($keyword->last_searched_at)->diffForHumans() }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="empty-cell">در بازه انتخاب‌شده هنوز جست‌وجوی بدون نتیجه‌ای ثبت نشده است.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    </section>
@endsection
