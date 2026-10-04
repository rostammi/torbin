@extends('layouts.app')

@section('title', 'گزارش‌های کاربران')

@section('content')
    <section class="container admin-page">
        <div class="section-head">
            <div><span class="eyebrow">کنترل کیفیت</span><h1>گزارش‌های کاربران</h1></div>
        </div>

        <div class="contact-request-filters">
            <div class="filter-tabs">
                <a class="{{ $status === '' ? 'active' : '' }}" href="{{ route('admin.comparison-reports.index', ['type' => $type ?: null]) }}">همه وضعیت‌ها</a>
                @foreach(\App\Models\ComparisonReport::STATUSES as $value => $label)
                    <a class="{{ $status === $value ? 'active' : '' }}" href="{{ route('admin.comparison-reports.index', ['status' => $value, 'type' => $type ?: null]) }}">{{ $label }}</a>
                @endforeach
            </div>
            <div class="filter-tabs">
                <a class="{{ $type === '' ? 'active' : '' }}" href="{{ route('admin.comparison-reports.index', ['status' => $status ?: null]) }}">همه انواع</a>
                @foreach(\App\Models\ComparisonReport::TYPES as $value => $label)
                    <a class="{{ $type === $value ? 'active' : '' }}" href="{{ route('admin.comparison-reports.index', ['status' => $status ?: null, 'type' => $value]) }}">{{ $label }}</a>
                @endforeach
            </div>
        </div>
        <x-admin.table-search placeholder="جست‌وجو در عنوان صفحه یا توضیحات گزارش…" />

        <div class="panel table-wrap">
            <table class="comparison-report-table">
                <thead><tr><x-admin.sortable-header column="tour" label="صفحه مقایسه" /><x-admin.sortable-header column="type" label="نوع گزارش" /><th>توضیحات کاربر</th><x-admin.sortable-header column="created" label="زمان ثبت" /><x-admin.sortable-header column="status" label="وضعیت" /></tr></thead>
                <tbody>
                    @forelse($reports as $report)
                        <tr>
                            <td><a href="{{ $report->tour->publicUrl() }}" target="_blank">{{ $report->tour->title }}</a><small>{{ $report->tour->categoryLabel() }}</small></td>
                            <td><span class="request-origin {{ $report->type }}">{{ $report->typeLabel() }}</span></td>
                            <td class="report-details-cell">{{ $report->details ?: '—' }}</td>
                            <td>{{ $report->created_at->format('Y/m/d H:i') }}@if($report->resolved_at)<small>رسیدگی: {{ $report->resolved_at->format('Y/m/d H:i') }}</small>@endif</td>
                            <td>
                                <form class="contact-status-form" method="post" action="{{ route('admin.comparison-reports.update', $report) }}">
                                    @csrf @method('PUT')
                                    <select name="status" aria-label="وضعیت گزارش">
                                        @foreach(\App\Models\ComparisonReport::STATUSES as $value => $label)
                                            <option value="{{ $value }}" @selected($report->status === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <button class="button compact-button" type="submit">ذخیره</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty-cell">گزارشی با این فیلتر پیدا نشد.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $reports->links('pagination.admin') }}
    </section>
@endsection
