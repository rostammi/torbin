@extends('layouts.app')

@section('title', 'مگ گیت | راهنمای سفر، تور و اقامت')
@section('meta')
    <link rel="canonical" href="{{ route('mag.index').'/' }}">
    <meta name="description" content="راهنمای سفر، انتخاب تور، رزرو هتل و اقامتگاه در مگ گیت.">
@endsection

@section('content')
    <section class="container static-page mag-index-page">
        <div class="static-page-head">
            <span class="eyebrow">مگ گیت</span>
            <h1>راهنمای سفر و اقامت</h1>
            <p class="muted">مطالب کاربردی برای انتخاب بهتر تور، هتل و اقامتگاه</p>
        </div>

        <div class="mag-index-grid">
            @forelse($pages as $page)
                @php
                    preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $page->content, $image);
                    $summary = \Illuminate\Support\Str::limit(trim(strip_tags($page->content)), 150);
                @endphp
                <article class="panel mag-index-card">
                    <a class="mag-index-card-link" href="{{ $page->publicUrl() }}" aria-label="مطالعه مطلب {{ $page->title }}">
                        @if(isset($image[1]))
                        <span class="mag-index-image">
                            <img src="{{ $image[1] }}" alt="تصویر مطلب {{ $page->title }}" width="1200" height="675" loading="lazy" fetchpriority="low" decoding="async">
                        </span>
                        @endif
                        <div class="mag-index-card-body">
                            <h2>{{ $page->title }}</h2>
                            <span class="mag-index-summary">{{ $summary }}</span>
                            <span class="mag-index-link">مطالعه مطلب ←</span>
                        </div>
                    </a>
                </article>
            @empty
                <div class="empty-state"><h2>هنوز مطلبی منتشر نشده است</h2></div>
            @endforelse
        </div>
    </section>
@endsection
