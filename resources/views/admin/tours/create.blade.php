@extends('layouts.app')

@section('title', 'تور جدید')

@section('content')
    <section class="container admin-page narrow">
        <div class="section-head"><div><span class="eyebrow">مدیریت</span><h1>ساخت تور جدید</h1></div><a href="{{ route('admin.tours.index') }}">بازگشت</a></div>
        <form class="panel admin-form" action="{{ route('admin.tours.store') }}" method="post" enctype="multipart/form-data">
            @csrf
            @include('admin.tours._form')
        </form>
    </section>
@endsection
