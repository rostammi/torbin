@extends('layouts.app')

@section('title', 'ویرایش تبلیغ')

@section('content')
    <section class="container admin-page narrow">
        <div class="section-head"><div><span class="eyebrow">مدیریت تبلیغات</span><h1>ویرایش {{ $advertisement->name }}</h1></div><a href="{{ route('admin.advertisements.index') }}">بازگشت</a></div>
        <form class="panel admin-form" action="{{ route('admin.advertisements.update', $advertisement) }}" method="post" enctype="multipart/form-data">@csrf @method('PUT') @include('admin.advertisements._form')</form>
    </section>
@endsection
