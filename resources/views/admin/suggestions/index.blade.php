@extends('layouts.app')

@section('title', 'پیشنهادهای تور محبوب')

@section('content')
    <section class="container admin-page">
        <div class="section-head">
            <div><span class="eyebrow">اتوماسیون محتوا</span><h1>پیشنهادهای ساخت تور</h1><p class="muted">پیشنهادهای کنترل‌شده و مرتبط، فقط از کاتالوگ مقصدهای داخلی و خارجی</p></div>
            <div class="heading-actions">
                <form method="post" action="{{ route('admin.suggestions.discover') }}">@csrf<button class="button button-secondary">↻ دریافت پیشنهادهای تازه</button></form>
                <form method="post" action="{{ route('admin.suggestions.store-all') }}" onsubmit="if (!confirm('همه پیشنهادهای داخلی و خارجی ساخته یا به‌روزرسانی شوند؟')) return false; this.querySelector('button').disabled=true; this.querySelector('button').textContent='در حال شروع جاب…'">
                    @csrf
                    <button class="button button-featured" @disabled($bulkRun?->status === 'running' && ! $bulkRun?->finished_at)>ساخت/به‌روزرسانی همه</button>
                </form>
            </div>
        </div>

        @if ($bulkRun?->status === 'running' && ! $bulkRun?->finished_at)
            <div class="panel bulk-job-status">
                <div><strong>جاب ساخت و به‌روزرسانی در حال اجراست</strong><small>{{ number_format($bulkRun->successful + $bulkRun->failed) }} از {{ number_format($bulkRun->total) }} پیشنهاد پردازش شده</small></div>
                <a href="{{ route('admin.sync.index') }}">مشاهده گزارش اجرا</a>
            </div>
        @endif

        <div class="suggestion-region-tabs" role="tablist" aria-label="نوع مقصد">
            <a role="tab" aria-selected="{{ $region === 'domestic' ? 'true' : 'false' }}" class="{{ $region === 'domestic' ? 'active' : '' }}" href="{{ route('admin.suggestions.index', array_filter(['region' => 'domestic', 'status' => $status])) }}"><span>مقصدهای داخلی</span><b>{{ number_format($regionCounts->get('domestic', 0)) }} پیشنهاد</b></a>
            <a role="tab" aria-selected="{{ $region === 'foreign' ? 'true' : 'false' }}" class="{{ $region === 'foreign' ? 'active' : '' }}" href="{{ route('admin.suggestions.index', array_filter(['region' => 'foreign', 'status' => $status])) }}"><span>مقصدهای خارجی</span><b>{{ number_format($regionCounts->get('foreign', 0)) }} پیشنهاد</b></a>
        </div>

        <div class="filter-tabs">
            <a class="{{ $status === '' ? 'active' : '' }}" href="{{ route('admin.suggestions.index', ['region' => $region]) }}">همه</a>
            <a class="{{ $status === 'pending' ? 'active' : '' }}" href="{{ route('admin.suggestions.index', ['region' => $region, 'status' => 'pending']) }}">در انتظار ساخت</a>
            <a class="{{ $status === 'created' ? 'active' : '' }}" href="{{ route('admin.suggestions.index', ['region' => $region, 'status' => 'created']) }}">ساخته‌شده</a>
            <a class="{{ $status === 'failed' ? 'active' : '' }}" href="{{ route('admin.suggestions.index', ['region' => $region, 'status' => 'failed']) }}">نیازمند بررسی</a>
        </div>

        <div class="panel table-wrap">
            <table>
                <thead><tr><th>کلیدواژه و عنوان پیشنهادی</th><th>امتیاز تقاضا</th><th>منبع</th><th>وضعیت</th><th>عملیات</th></tr></thead>
                <tbody>
                @forelse ($suggestions as $suggestion)
                    <tr>
                        <td><strong>{{ $suggestion->keyword }}</strong><small>{{ $suggestion->suggested_title }}</small></td>
                        <td><span class="trend-score"><i style="width: {{ $suggestion->trend_score }}%"></i></span><small>{{ $suggestion->trend_score }} از ۱۰۰</small></td>
                        <td>کاتالوگ مقصدها<small>{{ data_get($suggestion->metadata, 'region') === 'domestic' ? 'داخلی' : 'خارجی' }}</small></td>
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
        {{ $suggestions->links('pagination.admin') }}
    </section>
@endsection
