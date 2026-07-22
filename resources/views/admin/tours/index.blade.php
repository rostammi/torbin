@extends('layouts.app')

@section('title', 'مدیریت تورها')

@section('content')
    <section class="container admin-page">
        <div class="section-head">
            <div><span class="eyebrow">پنل مدیریت</span><h1>تورها</h1></div>
            <div class="heading-actions"><a class="button button-secondary" href="{{ route('admin.suggestions.index') }}">پیشنهادهای محبوب</a><a class="button" href="{{ route('admin.tours.create') }}">+ تور جدید</a></div>
        </div>
        <div class="panel table-wrap">
            <table>
                <thead><tr><th>تور</th><th>منابع قیمت</th><th>وضعیت</th><th>عملیات</th></tr></thead>
                <tbody>
                @forelse ($tours as $tour)
                    <tr>
                        <td><strong>{{ $tour->title }}</strong><small>/{{ $tour->slug }}</small></td>
                        <td>{{ $tour->price_sources_count }} سایت</td>
                        <td><span class="status {{ $tour->is_active ? 'success' : '' }}">{{ $tour->is_active ? 'منتشرشده' : 'پیش‌نویس' }}</span></td>
                        <td class="actions">
                            <a href="{{ route('tours.show', $tour) }}" target="_blank">نمایش</a>
                            <a href="{{ route('admin.tours.edit', $tour) }}">ویرایش</a>
                            <form method="post" action="{{ route('admin.tours.destroy', $tour) }}" onsubmit="return confirm('این تور حذف شود؟')">@csrf @method('DELETE')<button class="danger-link">حذف</button></form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="empty-cell">هنوز توری نساخته‌اید.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $tours->links('pagination.admin') }}
    </section>
@endsection
