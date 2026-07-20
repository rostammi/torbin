<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'توربین | مقایسه قیمت تور')</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    <header class="site-header">
        <div class="container nav-wrap">
            <a class="brand" href="{{ route('home') }}"><span>تـور</span>بین</a>
            <nav>
                <a href="{{ route('home') }}">همه تورها</a>
                @auth
                    <a href="{{ route('admin.tours.index') }}">مدیریت</a>
                    <form action="{{ route('logout') }}" method="post" class="inline-form">
                        @csrf
                        <button class="link-button" type="submit">خروج</button>
                    </form>
                @else
                    <a href="{{ route('login') }}">ورود مدیر</a>
                @endauth
            </nav>
        </div>
    </header>

    @if (session('success') || session('error'))
        <div class="container flash {{ session('error') ? 'flash-error' : '' }}">
            {{ session('success') ?? session('error') }}
        </div>
    @endif

    <main>@yield('content')</main>

    <footer class="site-footer">
        <div class="container">توربین؛ انتخاب آگاهانه برای سفر بعدی شما</div>
    </footer>
</body>
</html>
