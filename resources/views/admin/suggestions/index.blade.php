@extends('layouts.app')

@section('title', 'پیشنهادهای تور محبوب')

@section('content')
    <section class="container admin-page">
        <div class="section-head">
            <div><span class="eyebrow">اتوماسیون محتوا</span><h1>تورهای پرطرفدار</h1><p class="muted">پیشنهادهای Google Trends، جست‌وجوهای کاربران و کاتالوگ مقصدها</p></div>
            <form method="post" action="{{ route('admin.suggestions.discover') }}">@csrf<button class="button">↻ دریافت پیشنهادهای تازه</button></form>
        </div>

        <div class="filter-tabs">
            <a class="{{ $status === '' ? 'active' : '' }}" href="{{ route('admin.suggestions.index') }}">همه</a>
            <a class="{{ $status === 'pending' ? 'active' : '' }}" href="{{ route('admin.suggestions.index', ['status' => 'pending']) }}">در انتظار ساخت</a>
            <a class="{{ $status === 'created' ? 'active' : '' }}" href="{{ route('admin.suggestions.index', ['status' => 'created']) }}">ساخته‌شده</a>
            <a class="{{ $status === 'failed' ? 'active' : '' }}" href="{{ route('admin.suggestions.index', ['status' => 'failed']) }}">نیازمند بررسی</a>
        </div>

        <div class="panel table-wrap">
            <table>
                <thead><tr><th>کلیدواژه و عنوان پیشنهادی</th><th>امتیاز تقاضا</th><th>منبع</th><th>وضعیت</th><th>عملیات</th></tr></thead>
                <tbody>
                @forelse ($suggestions as $suggestion)
                    <tr>
                        <td><strong>{{ $suggestion->keyword }}</strong><small>{{ $suggestion->suggested_title }}</small></td>
                        <td><span class="trend-score"><i style="width: {{ $suggestion->trend_score }}%"></i></span><small>{{ $suggestion->trend_score }} از ۱۰۰</small></td>
                        <td>{{ match($suggestion->source) {'google_trends' => 'Google Trends', 'site_search' => 'جست‌وجوی سایت', default => 'کاتالوگ مقصدها'} }}</td>
                        <td><span class="status {{ $suggestion->status === 'created' ? 'success' : ($suggestion->status === 'failed' ? 'failed' : '') }}">{{ match($suggestion->status) {'created' => 'ساخته‌شده', 'processing' => 'در حال پردازش', 'failed' => 'ناموفق', default => 'آماده ساخت'} }}</span></td>
                        <td class="actions">
                            @if ($suggestion->tour)
                                <a href="{{ route('admin.tours.edit', $suggestion->tour) }}">ویرایش تور</a>
                            @else
                                <form method="post" action="{{ route('admin.suggestions.store', $suggestion) }}" onsubmit="this.querySelector('button').disabled=true; this.querySelector('button').textContent='در حال ساخت…'">@csrf<button class="button compact-button">ایجاد خودکار تور</button></form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty-cell">هنوز پیشنهادی دریافت نشده؛ دکمه «دریافت پیشنهادهای تازه» را بزنید.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $suggestions->links() }}
    </section>
@endsection
