@extends('layouts.app')

@section('title', 'صفحه پیدا نشد | گیت')
@section('meta')
    <meta name="robots" content="noindex, follow">
@endsection

@section('content')
    <section class="container auth-page">
        <div class="panel auth-card">
            <span class="eyebrow">خطای ۴۰۴</span>
            <h1>این صفحه پیدا نشد</h1>
            <p class="muted">ممکن است آدرس تغییر کرده باشد. از صفحه اصلی یا دسته‌بندی‌های سفر، مسیر درست را پیدا کنید.</p>
            <a class="button" href="{{ route('home') }}">بازگشت به صفحه اصلی</a>
        </div>
    </section>
@endsection
