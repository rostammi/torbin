@extends('layouts.app')

@section('title', 'داشبورد عملکرد پیشنهادها')

@section('content')
    <section class="container admin-page">
        <div class="section-head dashboard-heading">
            <div>
                <span class="eyebrow">تحلیل عملکرد</span>
                <h1>{{ $selectedAgency ? 'داشبورد '.$selectedAgency->name : 'داشبورد کل آژانس‌ها' }}</h1>
            </div>
            <form class="dashboard-filters" method="get">
                @if(request()->filled('q'))<input type="hidden" name="q" value="{{ request('q') }}">@endif
                @if(request()->filled('sort'))<input type="hidden" name="sort" value="{{ request('sort') }}"><input type="hidden" name="direction" value="{{ request('direction', 'desc') }}">@endif
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
            <article class="panel"><span>نمایش صفحات پیشنهاد</span><strong>{{ number_format($viewsTotal) }}</strong></article>
            <article class="panel"><span>کلیک خروجی موفق</span><strong>{{ number_format($clicksTotal) }}</strong></article>
            <article class="panel"><span>کلیک «تماس بگیرید»</span><strong>{{ number_format($contactClicksTotal) }}</strong></article>
            <article class="panel"><span>نرخ تبدیل کلیک</span><strong>{{ number_format($conversionTotal, 2) }}٪</strong></article>
            <article class="panel"><span>هزینه کلیک‌ها</span><strong>{{ number_format($costTotal) }} <small>تومان</small></strong></article>
        </div>

        <x-admin.table-search placeholder="جست‌وجو در عنوان یا آدرس پیشنهاد…" />

        <div class="panel table-wrap dashboard-table">
            <table>
                <thead><tr><x-admin.sortable-header column="title" label="پیشنهاد" /><x-admin.sortable-header column="minimum_price" label="کمترین قیمت سایت" />@if($selectedAgency)<x-admin.sortable-header column="agency_price" label="قیمت آژانس" /><x-admin.sortable-header column="gap" label="فاصله با کمترین قیمت" />@else<th>قیمت آژانس</th><th>فاصله با کمترین قیمت</th>@endif<x-admin.sortable-header column="views" label="نمایش صفحه" /><x-admin.sortable-header column="clicks" label="کلیک خرید" /><x-admin.sortable-header column="contact_clicks" label="کلیک تماس" /><x-admin.sortable-header column="conversion" label="کانورژن" /><x-admin.sortable-header column="cost" label="هزینه برای آژانس" /></tr></thead>
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
                            <td><strong>{{ $tour->title }}</strong><small><a href="{{ $tour->publicUrl() }}" target="_blank">مشاهده صفحه ↗</a></small></td>
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
                            <td>{{ number_format($tour->contact_clicks_count) }}</td>
                            <td>{{ number_format($conversion, 2) }}٪</td>
                            <td>{{ number_format($tour->click_cost ?? 0) }} تومان</td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="empty-cell">هنوز پیشنهادی برای نمایش در این داشبورد وجود ندارد.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $tours->links('pagination.admin') }}

        <section class="potential-keywords">
            <div class="subsection-head">
                <div><span class="eyebrow">تفکیک تماس‌ها</span><h2>کلیک‌های «تماس بگیرید» بر اساس شماره مقصد</h2></div>
                <span class="muted">مستقل از کلیک خرید و بدون هزینه کلیک</span>
            </div>
            <x-admin.table-search query-key="q_contact" page-key="page_contact" placeholder="جست‌وجو در نام مقصد تماس…" />
            <div class="panel table-wrap">
                <table>
                    <thead><tr><x-admin.sortable-header column="agency" label="مقصد تماس" sort-key="sort_contact" direction-key="direction_contact" page-key="page_contact" /><th>نوع شماره</th><x-admin.sortable-header column="clicks" label="تعداد کلیک" sort-key="sort_contact" direction-key="direction_contact" page-key="page_contact" /></tr></thead>
                    <tbody>
                        @foreach($agencyContactClicks as $contactRow)
                            <tr>
                                <td><strong>{{ $contactRow->agency?->name ?? 'آژانس حذف‌شده' }}</strong></td>
                                <td>شماره اختصاصی آژانس</td>
                                <td>{{ number_format($contactRow->clicks_count) }}</td>
                            </tr>
                        @endforeach
                        @if($showGeneralContactClicks)
                            <tr>
                                <td><strong>گیت</strong></td>
                                <td>شماره عمومی</td>
                                <td>{{ number_format($generalContactClicksTotal) }}</td>
                            </tr>
                        @endif
                        @if($agencyContactClicks->isEmpty() && ! $showGeneralContactClicks)
                            <tr><td colspan="3" class="empty-cell">در بازه انتخاب‌شده هنوز کلیک تماسی ثبت نشده است.</td></tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </section>

        @if(auth()->user()->isAdmin())
            <section class="potential-keywords">
                <div class="subsection-head">
                    <div><span class="eyebrow">فرصت توسعه محصول</span><h2>کیوردهای دارای پتانسیل ساخت پیشنهاد</h2></div>
                    <span class="muted">جست‌وجوهایی که هیچ نتیجه‌ای نداشته‌اند</span>
                </div>
                <x-admin.table-search query-key="q_keyword" page-key="page_keyword" placeholder="جست‌وجو در عبارت‌های بدون نتیجه…" />
                <div class="panel table-wrap">
                    <table>
                        <thead><tr><x-admin.sortable-header column="keyword" label="عبارت جست‌وجو" sort-key="sort_keyword" direction-key="direction_keyword" page-key="page_keyword" /><x-admin.sortable-header column="searches" label="تعداد جست‌وجو" sort-key="sort_keyword" direction-key="direction_keyword" page-key="page_keyword" /><x-admin.sortable-header column="visitors" label="کاربران تقریبی" sort-key="sort_keyword" direction-key="direction_keyword" page-key="page_keyword" /><x-admin.sortable-header column="last_search" label="آخرین جست‌وجو" sort-key="sort_keyword" direction-key="direction_keyword" page-key="page_keyword" /></tr></thead>
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
