<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'گیت | مقایسه قیمت تور')</title>
    @yield('meta')
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    <header class="site-header">
        <div class="container nav-wrap">
            <a class="brand" href="{{ route('home') }}"><span>گ</span>یت</a>
            <div class="header-search" data-suggestions-url="{{ route('search.suggestions') }}">
                <form action="{{ route('search.index') }}" method="get" role="search">
                    <input id="site-search" type="search" name="q" value="{{ request()->routeIs('search.*') ? request('q') : '' }}" placeholder="جست‌وجوی تور، هتل، اقامتگاه یا ویزا…" minlength="3" autocomplete="off" aria-label="جست‌وجوی خدمات سفر" aria-controls="search-suggestions" aria-expanded="false">
                    <button type="submit" aria-label="جست‌وجو">⌕</button>
                </form>
                <div id="search-suggestions" class="search-suggestions" role="listbox" hidden></div>
            </div>
            <nav>
                <a href="{{ route('tours.index') }}">تورها</a>
                <a href="{{ route('hotels.index') }}">هتل‌ها</a>
                <a href="{{ route('stays.index') }}">اقامتگاه‌ها</a>
                <a href="{{ route('visas.index') }}">ویزا</a>
                @auth
                    <a href="{{ route('admin.dashboard') }}">داشبورد</a>
                    @if(auth()->user()->isAdmin())
                        <a href="{{ route('admin.tours.index') }}">صفحات مقایسه</a>
                        <a href="{{ route('admin.suggestions.index') }}">پیشنهادها</a>
                        <a href="{{ route('admin.sync.index') }}">همگام‌سازی</a>
                        <a href="{{ route('admin.agencies.index') }}">آژانس‌ها و اعتبار</a>
                        <a href="{{ route('admin.advertisements.index') }}">تبلیغات</a>
                        <a href="{{ route('admin.static-pages.index') }}">صفحات ثابت</a>
                    @endif
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
        <div class="container footer-grid">
            <div>
                <a class="footer-brand" href="{{ route('home') }}">گیت</a>
                <p>مرجع جست‌وجو و مقایسه قیمت تور، هتل، اقامتگاه و خدمات ویزا</p>
            </div>
            <div>
                <strong>با گیت</strong>
                <a href="{{ route('pages.about') }}">درباره ما</a>
                <a href="{{ route('pages.contact') }}">تماس با ما</a>
                <a href="{{ route('pages.faq') }}">سؤالات متداول</a>
            </div>
            <div>
                <strong>دسته‌بندی‌ها</strong>
                <a href="{{ route('tours.index') }}">تورها</a>
                <a href="{{ route('hotels.index') }}">هتل‌ها</a>
                <a href="{{ route('stays.index') }}">اقامتگاه‌ها</a>
                <a href="{{ route('visas.index') }}">ویزا</a>
            </div>
            <div>
                <strong>اطلاعات تماس</strong>
                <a href="tel:09199010216" dir="ltr">۰۹۱۹۹۰۱۰۲۱۶</a>
                <a href="mailto:info@geyt.ir" dir="ltr">info@geyt.ir</a>
            </div>
        </div>
        <div class="container footer-bottom">کلیه حقوق این سایت متعلق به گیت است.</div>
    </footer>
    <script>
        (() => {
            const box = document.querySelector('.header-search');
            const input = document.querySelector('#site-search');
            const results = document.querySelector('#search-suggestions');
            if (!box || !input || !results) return;

            let timer;
            let request;
            const close = () => {
                results.hidden = true;
                results.replaceChildren();
                input.setAttribute('aria-expanded', 'false');
            };
            const show = () => {
                results.hidden = false;
                input.setAttribute('aria-expanded', 'true');
            };
            const metaText = item => item.minimum_price
                ? `${Number(item.minimum_price).toLocaleString('fa-IR')} تومان · ${item.compared_sources_count} سایت`
                : `${item.compared_sources_count} سایت قابل مقایسه`;

            input.addEventListener('input', () => {
                clearTimeout(timer);
                request?.abort();
                const query = input.value.trim();
                if ([...query].length < 3) return close();

                timer = setTimeout(async () => {
                    request = new AbortController();
                    try {
                        const url = new URL(box.dataset.suggestionsUrl, window.location.origin);
                        url.searchParams.set('q', query);
                        const response = await fetch(url, {signal: request.signal, headers: {'Accept': 'application/json'}});
                        if (!response.ok) return close();
                        const data = await response.json();
                        results.replaceChildren();

                        for (const item of data.items) {
                            const link = document.createElement('a');
                            link.href = item.url;
                            link.setAttribute('role', 'option');
                            const title = document.createElement('strong');
                            title.textContent = item.title;
                            const excerpt = document.createElement('span');
                            excerpt.textContent = item.excerpt;
                            const meta = document.createElement('small');
                            meta.textContent = metaText(item);
                            link.append(title, excerpt, meta);
                            results.append(link);
                        }

                        if (!data.items.length) {
                            const empty = document.createElement('span');
                            empty.className = 'search-empty';
                            empty.textContent = 'نتیجه‌ای پیدا نشد.';
                            results.append(empty);
                        } else if (data.total > 4) {
                            const all = document.createElement('a');
                            all.className = 'search-all';
                            all.href = data.all_url;
                            all.textContent = `مشاهده همه ${Number(data.total).toLocaleString('fa-IR')} نتیجه ←`;
                            results.append(all);
                        }
                        show();
                    } catch (error) {
                        if (error.name !== 'AbortError') close();
                    }
                }, 250);
            });

            input.addEventListener('keydown', event => { if (event.key === 'Escape') close(); });
            document.addEventListener('click', event => { if (!box.contains(event.target)) close(); });
        })();
    </script>
    @stack('scripts')
</body>
</html>
