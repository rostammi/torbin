@extends('layouts.app')

@section('title', 'مدیریت صفحات مقایسه')

@section('content')
    <section class="container admin-page">
        <div class="section-head">
            <div><span class="eyebrow">پنل مدیریت</span><h1>صفحات مقایسه</h1></div>
            <div class="heading-actions"><a class="button button-secondary" href="{{ route('admin.suggestions.index') }}">پیشنهادها</a><a class="button" href="{{ route('admin.tours.create', ['category' => $category]) }}">+ صفحه جدید</a></div>
        </div>
        <div class="filter-tabs">
            <a class="{{ $category === null ? 'active' : '' }}" href="{{ route('admin.tours.index') }}">همه</a>
            @foreach(config('comparison.categories') as $key => $item)
                <a class="{{ $category === $key ? 'active' : '' }}" href="{{ route('admin.tours.index', ['category' => $key]) }}">{{ $item['plural'] }}</a>
            @endforeach
        </div>
        <div class="panel table-wrap">
            <table>
                <thead><tr><th>صفحه</th><th>تصویر اول</th><th>دسته</th><th>منابع قیمت</th><th>وضعیت</th><th>عملیات</th></tr></thead>
                <tbody>
                @forelse ($tours as $tour)
                    @php($firstImage = $tour->cover_image ?: data_get($tour->gallery, 0))
                    <tr>
                        <td><strong>{{ $tour->title }}</strong><small>/{{ $tour->slug }}</small></td>
                        <td class="admin-tour-image-cell">
                            @if ($firstImage)
                                <a href="{{ route('admin.tours.edit', $tour) }}" title="ویرایش تصاویر {{ $tour->title }}">
                                    <img class="admin-tour-thumbnail" src="{{ Storage::url($firstImage) }}" alt="تصویر اول {{ $tour->title }}" loading="lazy">
                                </a>
                            @else
                                <span class="admin-tour-image-empty">بدون عکس</span>
                            @endif
                        </td>
                        <td>{{ $tour->categoryLabel() }}</td>
                        <td>{{ $tour->price_sources_count }} سایت<small>{{ $tour->priced_sources_count }} قیمت معتبر</small></td>
                        <td><span class="status {{ $tour->is_active ? 'success' : '' }}">{{ $tour->is_active ? 'منتشرشده' : 'پیش‌نویس' }}</span></td>
                        <td class="actions">
                            <a href="{{ $tour->publicUrl() }}" target="_blank">نمایش</a>
                            <a href="{{ route('admin.tours.edit', $tour) }}">ویرایش</a>
                            <form method="post" action="{{ route('admin.tours.crawl', $tour) }}" onsubmit="this.querySelector('button').disabled=true; this.querySelector('button').textContent='در حال بررسی…'">@csrf<button>به‌روزرسانی قیمت</button></form>
                            <form method="post" action="{{ route('admin.tours.add-images', $tour) }}" onsubmit="this.querySelector('button').disabled=true; this.querySelector('button').textContent='در صف…'">@csrf<button @class(['missing-image-button' => ! $firstImage])>افزودن ۳ عکس</button></form>
                            <form method="post" action="{{ route('admin.tours.destroy', $tour) }}" onsubmit="return confirm('این تور حذف شود؟')">@csrf @method('DELETE')<button class="danger-link">حذف</button></form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty-cell">هنوز صفحه‌ای نساخته‌اید.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $tours->links('pagination.admin') }}
    </section>
@endsection
