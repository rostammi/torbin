@extends('layouts.app')

@section('title', 'تبلیغ جدید')

@section('content')
    <section class="container admin-page narrow">
        <div class="section-head"><div><span class="eyebrow">مدیریت تبلیغات</span><h1>تبلیغ جدید</h1></div><a href="{{ route('admin.advertisements.index') }}">بازگشت</a></div>
        <form class="panel admin-form" action="{{ route('admin.advertisements.store') }}" method="post" enctype="multipart/form-data">@csrf @include('admin.advertisements._form')</form>
    </section>
@endsection
