@extends('layouts.app')

@section('title', 'ویرایش ' . $tour->title)

@section('content')
    <section class="container admin-page narrow">
        <div class="section-head">
            <div><span class="eyebrow">مدیریت تور</span><h1>{{ $tour->title }}</h1></div>
            <div class="actions"><a href="{{ route('tours.show', $tour) }}" target="_blank">نمایش صفحه</a><a href="{{ route('admin.tours.index') }}">بازگشت</a></div>
        </div>

        <form class="panel admin-form" action="{{ route('admin.tours.update', $tour) }}" method="post" enctype="multipart/form-data">
            @csrf @method('PUT')
            @include('admin.tours._form')
        </form>

        <div class="subsection-head">
            <div><span class="eyebrow">کراولرها</span><h2>منابع مقایسه قیمت</h2></div>
            <div class="actions">
                <form method="post" action="{{ route('admin.sources.official', $tour) }}">@csrf<button class="button button-secondary">افزودن ۳ منبع رسمی</button></form>
                <form method="post" action="{{ route('admin.tours.crawl-content', $tour) }}">@csrf<button class="button button-secondary">بررسی محتوای همه منابع</button></form>
                <form method="post" action="{{ route('admin.tours.crawl', $tour) }}">@csrf<button class="button">بررسی همه قیمت‌ها</button></form>
            </div>
        </div>

        <form class="panel admin-form" action="{{ route('admin.sources.store', $tour) }}" method="post">
            @csrf
            <h3>افزودن منبع جدید</h3>
            @include('admin.sources._fields', ['source' => new \App\Models\PriceSource])
            <button class="button" type="submit">افزودن منبع</button>
        </form>

        <div class="source-stack">
            @foreach ($tour->priceSources as $source)
                <details class="panel source-card" @if($source->last_status === 'failed') open @endif>
                    <summary>
                        <span><strong>{{ $source->provider_name }}</strong><small>{{ $source->extraction_type }}</small></span>
                        <span class="source-summary">
                            @if($source->is_featured)<i class="status featured">پیشنهاد ویژه</i>@endif
                            @if($source->latest_price)<b>{{ number_format($source->latest_price) }} {{ $source->currency }}</b>@endif
                            <i class="status {{ $source->last_status === 'success' || $source->last_status === 'manual' ? 'success' : ($source->last_status === 'failed' ? 'failed' : '') }}">{{ ['success'=>'موفق', 'empty'=>'بدون تور فعال', 'failed'=>'خطا', 'manual'=>'دستی'][$source->last_status] ?? 'بررسی‌نشده' }}</i>
                        </span>
                    </summary>
                    @if($source->last_error)<div class="crawl-error">{{ $source->last_error }}</div>@endif
                    @if($source->content_error)<div class="crawl-error">خطای خواندن محتوا: {{ $source->content_error }}</div>@endif
                    @if($source->content_checked_at)
                        <div class="content-crawl-status">
                            محتوای صفحه {{ $source->content_checked_at->diffForHumans() }} بررسی شد؛
                            {{ count($source->content_insights ?? []) }} موضوع مفید پیدا شد.
                        </div>
                    @endif
                    <form class="admin-form inner-form" action="{{ route('admin.sources.update', $source) }}" method="post">
                        @csrf @method('PUT')
                        @include('admin.sources._fields')
                        <button class="button" type="submit">ذخیره منبع</button>
                    </form>
                    <div class="source-actions">
                        @if($source->extraction_type !== 'manual')<form method="post" action="{{ route('admin.sources.crawl', $source) }}">@csrf<button class="button button-secondary">اجرای آزمایشی</button></form>@endif
                        <form method="post" action="{{ route('admin.agencies.featured') }}">
                            @csrf @method('PUT')
                            <input type="hidden" name="provider_name" value="{{ $source->provider_name }}">
                            <input type="hidden" name="is_featured" value="1">
                            <button class="button button-featured" type="submit">ویژه‌کردن همه پیشنهادهای {{ $source->provider_name }}</button>
                        </form>
                        <form method="post" action="{{ route('admin.agencies.featured') }}">
                            @csrf @method('PUT')
                            <input type="hidden" name="provider_name" value="{{ $source->provider_name }}">
                            <input type="hidden" name="is_featured" value="0">
                            <button class="button button-secondary" type="submit">حذف نشان ویژه از همه</button>
                        </form>
                        <form method="post" action="{{ route('admin.sources.destroy', $source) }}" onsubmit="return confirm('منبع حذف شود؟')">@csrf @method('DELETE')<button class="button button-danger">حذف منبع</button></form>
                    </div>
                </details>
            @endforeach
        </div>
    </section>
@endsection
