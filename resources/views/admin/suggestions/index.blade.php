@extends('layouts.app')

@section('title', 'پیشنهادهای تور محبوب')

@section('content')
    <section class="container admin-page">
        <div class="section-head">
            <div><span class="eyebrow">اتوماسیون محتوا</span><h1>پیشنهادهای صفحات مقایسه</h1><p class="muted">کاتالوگ کنترل‌شده تور، هتل، اقامتگاه و خدمات ویزا</p></div>
            <div class="heading-actions">
                <form method="post" action="{{ route('admin.suggestions.discover') }}">@csrf<button class="button button-secondary">↻ دریافت پیشنهادهای تازه</button></form>
                <form method="post" action="{{ route('admin.suggestions.store-all') }}" onsubmit="if (!confirm('همه پیشنهادهای ساخته‌نشده این دسته ساخته شوند؟')) return false; this.querySelector('button').disabled=true; this.querySelector('button').textContent='در حال شروع ساخت…'">
                    @csrf
                    <input type="hidden" name="category" value="{{ $category }}">
                    <input type="hidden" name="mode" value="create">
                    <button class="button button-featured" @disabled($buildableCount === 0 || ($bulkRun?->status === 'running' && ! $bulkRun?->finished_at))>ساخت همه ({{ number_format($buildableCount) }})</button>
                </form>
                <form method="post" action="{{ route('admin.suggestions.store-all') }}" onsubmit="if (!confirm('همه صفحات ساخته‌شده این دسته به‌روزرسانی شوند؟')) return false; this.querySelector('button').disabled=true; this.querySelector('button').textContent='در حال شروع به‌روزرسانی…'">
                    @csrf
                    <input type="hidden" name="category" value="{{ $category }}">
                    <input type="hidden" name="mode" value="update">
                    <button class="button button-secondary" @disabled($updatableCount === 0 || ($bulkRun?->status === 'running' && ! $bulkRun?->finished_at))>به‌روزرسانی همه ({{ number_format($updatableCount) }})</button>
                </form>
            </div>
        </div>

        @if ($bulkRun?->status === 'running' && ! $bulkRun?->finished_at)
            <div class="panel bulk-job-status">
                <div><strong>{{ data_get($bulkRun->details, 'mode') === 'update' ? 'به‌روزرسانی صفحات' : 'ساخت پیشنهادها' }} در حال اجراست</strong><small>{{ number_format($bulkRun->successful + $bulkRun->failed) }} از {{ number_format($bulkRun->total) }} پیشنهاد پردازش شده</small></div>
                <a href="{{ route('admin.sync.index') }}">مشاهده گزارش اجرا</a>
            </div>
        @endif

        <div class="suggestion-region-tabs" role="tablist" aria-label="دسته مقایسه">
            @foreach(config('comparison.categories') as $key => $item)
                <a role="tab" aria-selected="{{ $category === $key ? 'true' : 'false' }}" class="{{ $category === $key ? 'active' : '' }}" href="{{ route('admin.suggestions.index', ['category' => $key]) }}">
                    <span>{{ $item['plural'] }}</span><b>{{ number_format($categoryCounts->get($key, 0)) }} پیشنهاد</b>
                </a>
            @endforeach
        </div>

        @if($category === 'tour')
            <div class="filter-tabs">
                <a class="{{ $region === 'domestic' ? 'active' : '' }}" href="{{ route('admin.suggestions.index', ['category' => $category, 'region' => 'domestic']) }}">مقصدهای داخلی</a>
                <a class="{{ $region === 'foreign' ? 'active' : '' }}" href="{{ route('admin.suggestions.index', ['category' => $category, 'region' => 'foreign']) }}">مقصدهای خارجی</a>
            </div>
        @endif

        <div class="filter-tabs">
            <a class="{{ $status === '' ? 'active' : '' }}" href="{{ route('admin.suggestions.index', ['category' => $category, 'region' => $region]) }}">همه</a>
            <a class="{{ $status === 'pending' ? 'active' : '' }}" href="{{ route('admin.suggestions.index', ['category' => $category, 'region' => $region, 'status' => 'pending']) }}">در انتظار ساخت</a>
            <a class="{{ $status === 'created' ? 'active' : '' }}" href="{{ route('admin.suggestions.index', ['category' => $category, 'region' => $region, 'status' => 'created']) }}">ساخته‌شده</a>
            <a class="{{ $status === 'failed' ? 'active' : '' }}" href="{{ route('admin.suggestions.index', ['category' => $category, 'region' => $region, 'status' => 'failed']) }}">نیازمند بررسی</a>
        </div>

        <div class="panel table-wrap">
            <table>
                <thead><tr><th>کلیدواژه و عنوان پیشنهادی</th><th>امتیاز تقاضا</th><th>منبع</th><th>وضعیت</th><th>عملیات</th></tr></thead>
                <tbody>
                @forelse ($suggestions as $suggestion)
                    <tr>
                        <td>
                            <strong>{{ $suggestion->keyword }}</strong>
                            <small>{{ $suggestion->suggested_title }}</small>
                            @if(data_get($suggestion->metadata, 'keywords'))
                                <small>{{ count(data_get($suggestion->metadata, 'keywords')) }} کلیدواژه در یک صفحه مقصد</small>
                            @endif
                        </td>
                        <td><span class="trend-score"><i style="width: {{ $suggestion->trend_score }}%"></i></span><small>{{ $suggestion->trend_score }} از ۱۰۰</small></td>
                        <td>{{ config("comparison.categories.{$suggestion->category}.plural") }}<small>{{ $suggestion->source === 'managed_source' ? 'منبع مدیریت‌شده' : ($suggestion->category === 'tour' ? (data_get($suggestion->metadata, 'region') === 'domestic' ? 'داخلی' : 'خارجی') : 'کاتالوگ مقایسه') }}</small></td>
                        <td><span class="status {{ $suggestion->status === 'created' ? 'success' : ($suggestion->status === 'failed' ? 'failed' : '') }}">{{ match($suggestion->status) {'created' => 'ساخته‌شده', 'processing' => 'در حال پردازش', 'failed' => 'ناموفق', default => 'آماده ساخت'} }}</span></td>
                        <td class="actions">
                            @if ($suggestion->tour)
                                <a href="{{ route('admin.tours.edit', $suggestion->tour) }}">ویرایش تور</a>
                            @else
                                <form method="post" action="{{ route('admin.suggestions.store', $suggestion) }}" onsubmit="this.querySelector('button').disabled=true; this.querySelector('button').textContent='در حال ساخت…'">@csrf<button class="button compact-button">ایجاد خودکار صفحه</button></form>
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
