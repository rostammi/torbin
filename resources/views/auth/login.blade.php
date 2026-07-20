@extends('layouts.app')

@section('title', 'ورود مدیر | توربین')

@section('content')
    <div class="auth-page container">
        <form class="panel auth-card" action="{{ route('login.store') }}" method="post">
            @csrf
            <span class="eyebrow">پنل مدیریت</span>
            <h1>خوش آمدید</h1>
            <p class="muted">برای مدیریت تورها و قیمت‌ها وارد شوید.</p>
            <label>ایمیل<input type="email" name="email" value="{{ old('email') }}" required autofocus></label>
            @error('email')<span class="field-error">{{ $message }}</span>@enderror
            <label>رمز عبور<input type="password" name="password" required></label>
            <label class="check-label"><input type="checkbox" name="remember" value="1"> مرا به خاطر بسپار</label>
            <button class="button" type="submit">ورود به مدیریت</button>
        </form>
    </div>
@endsection
