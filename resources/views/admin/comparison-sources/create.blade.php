@extends('layouts.app')

@section('title', 'افزودن منبع مقایسه')

@section('content')
    <section class="container admin-page">
        <div class="section-head">
            <div><span class="eyebrow">مدیریت منابع</span><h1>افزودن منبع تازه</h1></div>
            <a href="{{ route('admin.comparison-sources.index') }}">بازگشت به منابع</a>
        </div>
        <form class="panel admin-form" method="post" action="{{ route('admin.comparison-sources.store') }}">
            @csrf
            @include('admin.comparison-sources._form')
        </form>
    </section>
@endsection
