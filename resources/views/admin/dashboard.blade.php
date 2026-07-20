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
                <thead><tr><th>تور</th><th>نمایش صفحه</th><th>کلیک خرید</th><th>کانورژن</th><th>هزینه برای آژانس</th></tr></thead>
                <tbody>
                    @forelse($tours as $tour)
                        @php($conversion = $tour->views_count > 0 ? ($tour->clicks_count / $tour->views_count) * 100 : 0)
                        <tr>
                            <td><strong>{{ $tour->title }}</strong><small><a href="{{ route('tours.show', $tour) }}" target="_blank">مشاهده صفحه ↗</a></small></td>
                            <td>{{ number_format($tour->views_count) }}</td>
                            <td>{{ number_format($tour->clicks_count) }}</td>
                            <td>{{ number_format($conversion, 2) }}٪</td>
                            <td>{{ number_format($tour->click_cost ?? 0) }} تومان</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty-cell">هنوز توری برای نمایش در این داشبورد وجود ندارد.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
