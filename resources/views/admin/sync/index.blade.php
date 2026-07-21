@extends('layouts.app')

@section('title', 'مرکز همگام‌سازی')

@section('content')
    <section class="container admin-page">
        <div class="section-head"><div><span class="eyebrow">عملیات دوره‌ای</span><h1>مرکز همگام‌سازی</h1><p class="muted">همه‌ی عملیات خواندن تور، قیمت، امتیاز و محتوا در یک صفحه</p></div></div>

        <div class="sync-grid">
            <article class="panel sync-card"><span>{{ $stats['suggestions'] }} پیشنهاد آماده</span><h2>کشف تورهای محبوب</h2><p>Google Trends و تقاضای داخلی سایت را بخوان و فهرست پیشنهادها را به‌روز کن.</p><form method="post" action="{{ route('admin.sync.run') }}">@csrf<input type="hidden" name="type" value="discover_tours"><button class="button">اجرای کشف تورها</button></form></article>
            <article class="panel sync-card"><span>{{ $stats['stale_prices'] }} منبع نیازمند بروزرسانی</span><h2>قیمت‌ها و امتیازها</h2><p>قیمت، موجودی، لینک خرید و امتیاز همه‌ی منابع فعال را دوباره بخوان.</p><form method="post" action="{{ route('admin.sync.run') }}">@csrf<input type="hidden" name="type" value="prices"><button class="button">خواندن همه قیمت‌ها</button></form></article>
            <article class="panel sync-card"><span>{{ $stats['stale_content'] }} محتوای قدیمی</span><h2>محتوای ارائه‌دهنده‌ها</h2><p>موضوعات مفید صفحات ارائه‌دهندگان را استخراج و محتوای خودکار تور را تلفیق کن.</p><form method="post" action="{{ route('admin.sync.run') }}">@csrf<input type="hidden" name="type" value="content"><button class="button">خواندن همه محتواها</button></form></article>
            <article class="panel sync-card sync-all"><span>{{ $stats['sources'] }} منبع فعال</span><h2>همگام‌سازی کامل</h2><p>کشف تورها، قیمت‌ها، امتیازها و محتواها را به‌ترتیب اجرا کن.</p><form method="post" action="{{ route('admin.sync.run') }}">@csrf<input type="hidden" name="type" value="all"><button class="button button-featured">اجرای همه عملیات</button></form></article>
        </div>

        <div class="subsection-head"><div><span class="eyebrow">گزارش اجرا</span><h2>آخرین همگام‌سازی‌ها</h2></div></div>
        <div class="panel table-wrap">
            <table>
                <thead><tr><th>عملیات</th><th>شروع</th><th>نتیجه</th><th>موفق / کل</th><th>پیام</th></tr></thead>
                <tbody>
                @forelse ($runs as $run)
                    <tr>
                        <td><strong>{{ match($run->type) {'discover_tours' => 'کشف تورها', 'provision_tour' => 'ساخت خودکار تور', 'prices' => 'قیمت‌ها', 'content' => 'محتواها', default => 'همگام‌سازی کامل'} }}</strong><small>{{ $run->user?->name ?? 'زمان‌بندی سیستم' }}</small></td>
                        <td>{{ $run->started_at?->diffForHumans() }}</td>
                        <td><span class="status {{ $run->status === 'success' ? 'success' : ($run->status === 'failed' ? 'failed' : '') }}">{{ match($run->status) {'success' => 'موفق', 'partial' => 'بخشی موفق', 'failed' => 'ناموفق', default => 'در حال اجرا'} }}</span></td>
                        <td>{{ $run->successful }} / {{ $run->total }}</td>
                        <td><small>{{ $run->error ?: ($run->finished_at ? 'پایان در '.$run->finished_at->format('H:i') : 'در حال اجرا') }}</small></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty-cell">هنوز عملیاتی اجرا نشده است.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $runs->links() }}
    </section>
@endsection
