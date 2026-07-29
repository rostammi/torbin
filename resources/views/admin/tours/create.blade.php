@extends('layouts.app')

@section('title', 'صفحه مقایسه جدید')

@section('content')
    <section class="container admin-page narrow">
        <div class="section-head"><div><span class="eyebrow">مدیریت</span><h1>ساخت صفحه مقایسه جدید</h1></div><a href="{{ route('admin.tours.index') }}">بازگشت</a></div>
        <form class="panel admin-form" action="{{ route('admin.tours.store') }}" method="post" enctype="multipart/form-data">
            @csrf
            @include('admin.tours._form')
        </form>
    </section>
@endsection
