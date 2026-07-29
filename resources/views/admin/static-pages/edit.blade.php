@extends('layouts.app')

@section('title', 'ویرایش '.$page->title)

@section('content')
    <section class="container admin-page narrow">
        <div class="section-head">
            <div><span class="eyebrow">مدیریت محتوا</span><h1>{{ $page->title }}</h1></div>
            <a href="{{ route('admin.static-pages.index') }}">بازگشت</a>
        </div>
        <form class="panel admin-form" action="{{ route('admin.static-pages.update', $page) }}" method="post">
            @csrf @method('PUT')
            @if ($errors->any())
                <div class="validation-errors"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif
            <label>عنوان صفحه<input name="title" value="{{ old('title', $page->title) }}" required maxlength="160"></label>
            <label>محتوا
                <small>امکان استفاده از HTML مانند h2، p، strong، a و details وجود دارد.</small>
                <textarea name="content" rows="22" dir="rtl" required>{{ old('content', $page->content) }}</textarea>
            </label>
            <label class="check-label"><input type="checkbox" name="is_published" value="1" @checked(old('is_published', $page->is_published))> نمایش عمومی صفحه</label>
            <button class="button">ذخیره محتوا</button>
        </form>
    </section>
@endsection
