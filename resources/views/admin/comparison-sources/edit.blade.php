@extends('layouts.app')

@section('title', 'ویرایش منبع مقایسه')

@section('content')
    <section class="container admin-page">
        <div class="section-head">
            <div><span class="eyebrow">مدیریت منابع</span><h1>ویرایش {{ $source->name }}</h1></div>
            <a href="{{ route('admin.comparison-sources.index') }}">بازگشت به منابع</a>
        </div>
        <form class="panel admin-form" method="post" action="{{ route('admin.comparison-sources.update', $source) }}">
            @csrf
            @method('put')
            @include('admin.comparison-sources._form')
        </form>
    </section>
@endsection
