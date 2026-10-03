@extends('layouts.app')

@section('title', 'مدیریت صفحات ثابت')

@section('content')
    <section class="container admin-page">
        <div class="section-head">
            <div><span class="eyebrow">مدیریت محتوا</span><h1>صفحات ثابت</h1></div>
        </div>
        <x-admin.table-search placeholder="جست‌وجو در عنوان یا آدرس صفحه…" />
        <div class="panel table-wrap">
            <table>
                <thead><tr><x-admin.sortable-header column="title" label="عنوان" /><x-admin.sortable-header column="slug" label="آدرس" /><x-admin.sortable-header column="status" label="وضعیت" /><th>عملیات</th></tr></thead>
                <tbody>
                @forelse($pages as $page)
                    <tr>
                        <td><strong>{{ $page->title }}</strong></td>
                        <td><a dir="ltr" href="{{ $page->publicUrl() }}" target="_blank">{{ parse_url($page->publicUrl(), PHP_URL_PATH) }}</a></td>
                        <td><span class="status {{ $page->is_published ? 'success' : '' }}">{{ $page->is_published ? 'منتشرشده' : 'پیش‌نویس' }}</span></td>
                        <td class="actions"><a href="{{ route('admin.static-pages.edit', $page) }}">ویرایش محتوا</a></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="empty-cell">صفحه‌ای با این جست‌وجو پیدا نشد.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
