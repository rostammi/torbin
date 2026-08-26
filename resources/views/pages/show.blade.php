@extends('layouts.app')

@section('title', $page->title.' | گیت')
@section('meta')
    <link rel="canonical" href="{{ $page->publicUrl() }}">
    <meta name="description" content="{{ \Illuminate\Support\Str::limit(trim(strip_tags($page->content)), 160) }}">
@endsection

@section('content')
    <section class="container static-page">
        <div class="static-page-head">
            <span class="eyebrow">گیت</span>
            <h1>{{ $page->title }}</h1>
        </div>
        <article class="panel static-page-content">
            {!! $page->contentWithImageAlts() !!}
        </article>
    </section>
@endsection
