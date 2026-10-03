@extends('layouts.app')

@section('title', 'ریدایرکت‌های مهاجرت SEO')

@section('content')
    <section class="container admin-page">
        <div class="section-head">
            <div><span class="eyebrow">مهاجرت نسخه قدیم</span><h1>ریدایرکت‌های SEO</h1><p class="muted">هر URL قدیمی باید مستقیماً با ۳۰۱ به نزدیک‌ترین صفحه جدید متصل شود.</p></div>
            <form method="post" action="{{ route('admin.seo-redirects.sync') }}">@csrf<button class="button button-secondary">همگام‌سازی خودکار URLها</button></form>
        </div>

        <form class="panel admin-form" method="post" action="{{ route('admin.seo-redirects.store') }}">
            @csrf
            <h2>ثبت نگاشت دستی</h2>
            <div class="form-grid">
                <label>URL قدیمی geyt.ir<input name="source_url" type="url" dir="ltr" required placeholder="https://geyt.ir/tour/old-slug/" value="{{ old('source_url') }}">@error('source_url')<small class="field-error">{{ $message }}</small>@enderror</label>
                <label>صفحه جدید<select name="tour_id" required><option value="">انتخاب کنید</option>@foreach($tours as $tour)<option value="{{ $tour->id }}" @selected((string)old('tour_id') === (string)$tour->id)>[{{ $tour->categoryLabel() }}] {{ $tour->title }}</option>@endforeach</select></label>
            </div>
            <label>یادداشت<input name="notes" maxlength="1000" value="{{ old('notes') }}"></label>
            <button class="button">ذخیره ریدایرکت ۳۰۱</button>
        </form>

        <div class="filter-tabs">
            <a class="{{ $status === 'unmatched' ? 'active' : '' }}" href="{{ route('admin.seo-redirects.index', ['status' => 'unmatched']) }}">بدون متناظر ({{ number_format($counts['unmatched']) }})</a>
            <a class="{{ $status === 'matched' ? 'active' : '' }}" href="{{ route('admin.seo-redirects.index', ['status' => 'matched']) }}">متناظر ({{ number_format($counts['matched']) }})</a>
            <a class="{{ $status === 'manual' ? 'active' : '' }}" href="{{ route('admin.seo-redirects.index', ['status' => 'manual']) }}">مرج دستی ({{ number_format($counts['manual']) }})</a>
        </div>
        <x-admin.table-search placeholder="جست‌وجو در URL قدیمی، عنوان یا مقصد…" />

        <div class="panel table-wrap">
            <table>
                <thead><tr><x-admin.sortable-header column="source" label="صفحه قدیمی" /><x-admin.sortable-header column="status" label="وضعیت" /><x-admin.sortable-header column="destination" label="مقصد canonical جدید" /><x-admin.sortable-header column="hits" label="بازدید ریدایرکت" /><th>مرج دستی</th></tr></thead>
                <tbody>
                @forelse($redirects as $redirect)
                    <tr>
                        <td><strong>{{ $redirect->source_title ?: 'بدون عنوان' }}</strong><small dir="ltr">{{ $redirect->old_path }}</small></td>
                        <td><span class="status {{ $redirect->tour_id ? 'success' : 'failed' }}">{{ $redirect->tour_id ? ($redirect->match_type === 'manual' ? 'دستی' : 'خودکار') : 'بدون متناظر' }}</span></td>
                        <td>@if($redirect->tour)<a href="{{ $redirect->tour->publicUrl() }}" target="_blank">{{ $redirect->tour->title }} ↗</a>@else—@endif</td>
                        <td>{{ number_format($redirect->hits) }}<small>{{ $redirect->last_hit_at?->diffForHumans() ?: 'بدون بازدید' }}</small></td>
                        <td>
                            <form method="post" action="{{ route('admin.seo-redirects.update', $redirect) }}">
                                @csrf @method('PUT')
                                <select name="tour_id" required><option value="">انتخاب پیشنهاد</option>@foreach($tours as $tour)<option value="{{ $tour->id }}" @selected($redirect->tour_id === $tour->id)>[{{ $tour->categoryLabel() }}] {{ $tour->title }}</option>@endforeach</select>
                                <button class="button compact-button">ذخیره مرج</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td class="empty-cell" colspan="5">موردی در این وضعیت وجود ندارد.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $redirects->links('pagination.admin') }}
    </section>
@endsection
