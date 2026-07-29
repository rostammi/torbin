@extends('layouts.app')

@section('title', 'مدیریت صفحات ثابت')

@section('content')
    <section class="container admin-page">
        <div class="section-head">
            <div><span class="eyebrow">مدیریت محتوا</span><h1>صفحات ثابت</h1></div>
        </div>
        <div class="panel table-wrap">
            <table>
                <thead><tr><th>عنوان</th><th>آدرس</th><th>وضعیت</th><th>عملیات</th></tr></thead>
                <tbody>
                @foreach($pages as $page)
                    <tr>
                        <td><strong>{{ $page->title }}</strong></td>
                        <td><span dir="ltr">/{{ $page->slug }}</span></td>
                        <td><span class="status {{ $page->is_published ? 'success' : '' }}">{{ $page->is_published ? 'منتشرشده' : 'پیش‌نویس' }}</span></td>
                        <td class="actions"><a href="{{ route('admin.static-pages.edit', $page) }}">ویرایش محتوا</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endsection
