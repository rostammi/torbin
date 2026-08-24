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
                    @if(isset($image[1]))
                        <a class="mag-index-image" href="{{ $page->publicUrl() }}" tabindex="-1" aria-hidden="true">
                            <img src="{{ $image[1] }}" alt="">
                        </a>
                    @endif
                    <div class="mag-index-card-body">
                        <h2><a href="{{ $page->publicUrl() }}">{{ $page->title }}</a></h2>
                        <p>{{ $summary }}</p>
                        <a class="mag-index-link" href="{{ $page->publicUrl() }}">مطالعه مطلب ←</a>
                    </div>
                </article>
            @empty
                <div class="empty-state"><h2>هنوز مطلبی منتشر نشده است</h2></div>
            @endforelse
        </div>
    </section>
@endsection
