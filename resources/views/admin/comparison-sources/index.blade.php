@extends('layouts.app')

@section('title', 'مدیریت منابع')

@section('content')
    <section class="container admin-page">
        <div class="section-head">
            <div>
                <span class="eyebrow">کشف خودکار پیشنهادها</span>
                <h1>مدیریت منابع</h1>
                <p class="muted">هوم‌پیج هر سایت را همراه دسته‌ها ثبت کنید؛ اسکن، لینک‌های قابل مقایسه را به فهرست پیشنهادهای گیت اضافه می‌کند.</p>
            </div>
            <a class="button" href="{{ route('admin.comparison-sources.create') }}">+ افزودن منبع</a>
        </div>

        <div class="panel table-wrap">
            <table>
                <thead>
                <tr><th>منبع</th><th>دسته‌ها</th><th>آخرین اسکن</th><th>نتیجه</th><th>عملیات</th></tr>
                </thead>
                <tbody>
                @forelse($sources as $source)
                    @php($summary = $source->last_scan_summary ?? [])
                    <tr>
                        <td>
                            <strong>{{ $source->name }}</strong>
                            <small><a dir="ltr" target="_blank" rel="nofollow noopener noreferrer" href="{{ $source->homepage_url }}">{{ $source->homepage_url }}</a></small>
                            @unless($source->is_active)<span class="status">غیرفعال</span>@endunless
                        </td>
                        <td>
                            @foreach($source->categories ?? [] as $category)
                                <span class="status">{{ config("comparison.categories.{$category}.label", $category) }}</span>
                            @endforeach
                        </td>
                        <td>
                            {{ $source->last_scanned_at?->diffForHumans() ?? 'هنوز اسکن نشده' }}
                            @if($source->last_error)<small class="field-error">{{ $source->last_error }}</small>@endif
                        </td>
                        <td>
                            @if($source->last_status === 'success')
                                <span class="status success">{{ number_format($summary['found'] ?? 0) }} مورد پیدا شد</span>
                                <small>{{ number_format($summary['created'] ?? 0) }} جدید، {{ number_format($summary['updated'] ?? 0) }} به‌روزشده</small>
                            @elseif($source->last_status === 'failed')
                                <span class="status failed">ناموفق</span>
                            @else
                                <span class="status">در انتظار اجرا</span>
                            @endif
                        </td>
                        <td class="actions">
                            <a href="{{ route('admin.comparison-sources.edit', $source) }}">ویرایش</a>
                            <form method="post" action="{{ route('admin.comparison-sources.scan', $source) }}">
                                @csrf
                                <button class="link-button" @disabled(!$source->is_active)>اسکن حالا</button>
                            </form>
                            <form method="post" action="{{ route('admin.comparison-sources.destroy', $source) }}" onsubmit="return confirm('این منبع از مدیریت منابع حذف شود؟')">
                                @csrf
                                @method('delete')
                                <button class="link-button danger">حذف</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td class="empty-cell" colspan="5">هنوز منبعی ثبت نشده است.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $sources->links('pagination.admin') }}
    </section>
@endsection
