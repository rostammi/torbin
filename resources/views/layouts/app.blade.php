<!doctype html>
<html lang="fa" dir="rtl">
<head>
    @include('analytics.google-tag-manager-head')
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'گیت | مقایسه قیمت تور، هتل، اقامتگاه و خدمات ویزا')</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" sizes="512x512" href="{{ asset('favicon.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
    @yield('meta')
    @if(request()->routeIs('admin.*', 'login'))
        <meta name="robots" content="noindex, nofollow">
    @elseif(request()->routeIs('search.*'))
        <meta name="robots" content="noindex, follow">
    @endif
    @unless(request()->routeIs('admin.*', 'login'))
        @include('seo.social-meta')
    @endunless
    @include('seo.structured-data', ['structuredData' => $structuredData ?? []])
    @include('seo.critical-css')
    <link rel="preload" href="{{ asset('fonts/Vazirmatn.woff2') }}" as="font" type="font/woff2" crossorigin fetchpriority="high">
    <link rel="preload" href="{{ asset('css/app.css') }}" as="style" fetchpriority="high">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}" media="print" onload="this.onload=null;this.media='all'">
    <noscript><link rel="stylesheet" href="{{ asset('css/app.css') }}"></noscript>
</head>
<body>
    @include('analytics.google-tag-manager-body')
    <header class="site-header">
        <div class="container nav-wrap">
            <div class="header-branding">
                <a class="brand" href="{{ route('home') }}" aria-label="گیت؛ صفحه اصلی">
                    <span class="brand-logo-mark" aria-hidden="true">
                        <img src="{{ asset('images/geyt-logo.png') }}" alt="لوگوی گیت؛ مرجع مقایسه خدمات سفر" width="299" height="80" loading="eager" decoding="async">
                    </span>
                    <span class="brand-copy">
                        <span class="brand-name"><span>گ</span>یت</span>
                        <span class="header-slogan">مرجع تخصصی مقایسه تور، هتل، اقامتگاه و ویزا</span>
                    </span>
                </a>
            </div>
            <div class="header-search" data-suggestions-url="{{ route('search.suggestions') }}">
                <form action="{{ route('search.index') }}" method="get" role="search" data-search-form data-search-base-url="{{ route('search.index') }}">
                    <input id="site-search" type="search" name="q" value="{{ request()->routeIs('search.index') ? ($term ?? str_replace('+', ' ', request()->route('query') ?? '')) : '' }}" placeholder="مثلاً سفر داخلی با ۴ میلیون…" minlength="3" autocomplete="off" aria-label="جست‌وجوی خدمات سفر" aria-controls="search-suggestions" aria-expanded="false">
                    <button type="submit" aria-label="جست‌وجو">⌕</button>
                </form>
                <div id="search-suggestions" class="search-suggestions" role="listbox" hidden></div>
            </div>
            <nav @class(['admin-nav' => auth()->check() && auth()->user()->isAdmin()])>
                @auth
                    @if(auth()->user()->isAdmin())
                        <a class="nav-primary-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}">داشبورد</a>
                        <details class="nav-menu {{ request()->routeIs('home', 'tours.*', 'hotels.*', 'stays.*', 'visas.*') ? 'active' : '' }}">
                            <summary>مشاهده سایت</summary>
                            <div class="nav-dropdown">
                                <a class="{{ request()->routeIs('home') ? 'active' : '' }}" href="{{ route('home') }}">صفحه اصلی</a>
                                <a class="{{ request()->routeIs('tours.*') ? 'active' : '' }}" href="{{ route('tours.index').'/' }}">تورها</a>
                                <a class="{{ request()->routeIs('hotels.*') ? 'active' : '' }}" href="{{ route('hotels.index').'/' }}">هتل‌ها</a>
                                <a class="{{ request()->routeIs('stays.*') ? 'active' : '' }}" href="{{ route('stays.index').'/' }}">اقامتگاه‌ها</a>
                                <a class="{{ request()->routeIs('visas.*') ? 'active' : '' }}" href="{{ route('visas.index').'/' }}">ویزاها</a>
                                <a class="{{ request()->routeIs('mag.*') ? 'active' : '' }}" href="{{ route('mag.index').'/' }}">مگ</a>
                            </div>
                        </details>
                        <details class="nav-menu {{ request()->routeIs('admin.tours.*', 'admin.comparison-sources.*', 'admin.agencies.*') ? 'active' : '' }}">
                            <summary>مدیریت مقایسه</summary>
                            <div class="nav-dropdown">
                                <a class="{{ request()->routeIs('admin.tours.*') ? 'active' : '' }}" href="{{ route('admin.tours.index') }}">صفحات مقایسه</a>
                                <a class="{{ request()->routeIs('admin.comparison-sources.*') ? 'active' : '' }}" href="{{ route('admin.comparison-sources.index') }}">منابع و کراولرها</a>
                                <a class="{{ request()->routeIs('admin.agencies.*') ? 'active' : '' }}" href="{{ route('admin.agencies.index') }}">آژانس‌ها، اعتبار و تماس</a>
                            </div>
                        </details>
                        <details class="nav-menu {{ request()->routeIs('admin.suggestions.*', 'admin.sync.*', 'admin.contact-requests.*') ? 'active' : '' }}">
                            <summary>عملیات و پیگیری</summary>
                            <div class="nav-dropdown">
                                <a class="{{ request()->routeIs('admin.suggestions.*') ? 'active' : '' }}" href="{{ route('admin.suggestions.index') }}">پیشنهادهای صفحات</a>
                                <a class="{{ request()->routeIs('admin.sync.*') ? 'active' : '' }}" href="{{ route('admin.sync.index') }}">مرکز همگام‌سازی</a>
                                <a class="{{ request()->routeIs('admin.contact-requests.*') ? 'active' : '' }}" href="{{ route('admin.contact-requests.index') }}">شماره‌ها و درخواست‌های تماس</a>
                            </div>
                        </details>
                        <details class="nav-menu {{ request()->routeIs('admin.advertisements.*', 'admin.static-pages.*', 'admin.seo-redirects.*') ? 'active' : '' }}">
                            <summary>محتوا و درآمد</summary>
                            <div class="nav-dropdown">
                                <a class="{{ request()->routeIs('admin.advertisements.*') ? 'active' : '' }}" href="{{ route('admin.advertisements.index') }}">تبلیغات</a>
                                <a class="{{ request()->routeIs('admin.static-pages.*') ? 'active' : '' }}" href="{{ route('admin.static-pages.index') }}">صفحات ثابت</a>
                                <a class="{{ request()->routeIs('admin.seo-redirects.*') ? 'active' : '' }}" href="{{ route('admin.seo-redirects.index') }}">ریدایرکت‌های SEO</a>
                            </div>
                        </details>
                    @else
                        <a href="{{ route('tours.index').'/' }}">تورها</a>
                        <a href="{{ route('hotels.index').'/' }}">هتل‌ها</a>
                        <a href="{{ route('stays.index').'/' }}">اقامتگاه‌ها</a>
                        <a href="{{ route('visas.index').'/' }}">ویزا</a>
                        <a href="{{ route('mag.index').'/' }}">مگ</a>
                        <a href="{{ route('admin.dashboard') }}">داشبورد</a>
                    @endif
                    <form action="{{ route('logout') }}" method="post" class="inline-form">
                        @csrf
                        <button class="link-button" type="submit">خروج</button>
                    </form>
                @else
                    <a href="{{ route('tours.index').'/' }}">تورها</a>
                    <a href="{{ route('hotels.index').'/' }}">هتل‌ها</a>
                    <a href="{{ route('stays.index').'/' }}">اقامتگاه‌ها</a>
                    <a href="{{ route('visas.index').'/' }}">ویزا</a>
                    <a href="{{ route('mag.index').'/' }}">مگ</a>
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
                <nav class="footer-social" aria-label="شبکه‌های اجتماعی گیت">
                    <a class="social-link" href="https://www.linkedin.com/company/geyt/" target="_blank" rel="noopener noreferrer" aria-label="لینکدین گیت" title="لینکدین گیت">
                        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <path fill="currentColor" d="M6.5 8.25H3.25v12.5H6.5V8.25ZM4.88 3.25A1.88 1.88 0 1 0 4.88 7a1.88 1.88 0 0 0 0-3.75ZM9 8.25h3.12v1.7h.04c.44-.83 1.5-2.2 3.67-2.2 3.92 0 4.64 2.58 4.64 5.94v7.06h-3.25v-6.26c0-1.49-.03-3.41-2.08-3.41-2.08 0-2.4 1.63-2.4 3.3v6.37H9V8.25Z"/>
                        </svg>
                    </a>
                    <a class="social-link" href="https://www.instagram.com/geyt.ir" target="_blank" rel="noopener noreferrer" aria-label="اینستاگرام گیت" title="اینستاگرام گیت">
                        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <rect x="3" y="3" width="18" height="18" rx="5" fill="none" stroke="currentColor" stroke-width="2"/>
                            <circle cx="12" cy="12" r="4" fill="none" stroke="currentColor" stroke-width="2"/>
                            <circle cx="17.5" cy="6.5" r="1.2" fill="currentColor"/>
                        </svg>
                    </a>
                </nav>
            </div>
            <div>
                <strong>با گیت</strong>
                <a href="{{ route('pages.about').'/' }}">درباره ما</a>
                <a href="{{ route('pages.contact').'/' }}">تماس با ما</a>
                <a href="{{ route('pages.faq').'/' }}">سؤالات متداول</a>
            </div>
            <div>
                <strong>دسته‌بندی‌ها</strong>
                <a href="{{ route('tours.index').'/' }}">تورها</a>
                <a href="{{ route('hotels.index').'/' }}">هتل‌ها</a>
                <a href="{{ route('stays.index').'/' }}">اقامتگاه‌ها</a>
                <a href="{{ route('visas.index').'/' }}">ویزا</a>
            </div>
            <div>
                <strong>اطلاعات تماس</strong>
                <a href="tel:09199010216" dir="ltr">۰۹۱۹۹۰۱۰۲۱۶</a>
                <a href="mailto:info@geyt.ir" dir="ltr">info@geyt.ir</a>
            </div>
            <div class="footer-licenses">
                <strong>مجوزها</strong>
                <div class="license-list">
                    <a class="license-badge" href="https://trustseal.enamad.ir/?id=522515&amp;Code=1b3swSGBmJiCpB9APi3D7QOz5GOSJKC2" target="_blank" rel="noopener noreferrer" referrerpolicy="origin" aria-label="استعلام نماد اعتماد الکترونیکی گیت">
                        <span class="license-logo">
                            <span class="license-placeholder" aria-hidden="true">اینماد</span>
                            <img src="https://trustseal.enamad.ir/logo.aspx?id=522515&amp;Code=1b3swSGBmJiCpB9APi3D7QOz5GOSJKC2" alt="نماد اعتماد الکترونیکی گیت" width="60" height="64" loading="lazy" fetchpriority="low" decoding="async" referrerpolicy="origin" onerror="this.hidden=true">
                        </span>
                        <small>نماد اعتماد الکترونیکی</small>
                    </a>
                    <a class="license-badge" href="https://logo.samandehi.ir/Verify.aspx?id=371533&amp;p=xlaojyoerfthdshwxlaoxlao" target="_blank" rel="noopener noreferrer" referrerpolicy="origin" aria-label="استعلام نشان ساماندهی گیت">
                        <span class="license-logo">
                            <span class="license-placeholder" aria-hidden="true">ساماندهی</span>
                            <img id="rgvjjzpejxlzapfurgvjrgvj" src="https://logo.samandehi.ir/logo.aspx?id=371533&amp;p=qftiyndtnbpdujynqftiqfti" alt="نشان ساماندهی گیت" width="60" height="64" loading="lazy" fetchpriority="low" decoding="async" referrerpolicy="origin" onerror="this.hidden=true">
                        </span>
                        <small>نشان ساماندهی</small>
                    </a>
                </div>
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

            document.querySelectorAll('[data-search-form]').forEach(form => {
                form.addEventListener('submit', event => {
                    const query = form.querySelector('input[name="q"]')?.value.trim();
                    if (!query) return;

                    event.preventDefault();
                    const encodedQuery = encodeURIComponent(query).replace(/%20/g, '+');
                    window.location.assign(`${form.dataset.searchBaseUrl.replace(/\/$/, '')}/${encodedQuery}/`);
                });
            });

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
    <script>
        (() => {
            const menus = [...document.querySelectorAll('.nav-menu')];
            if (!menus.length) return;

            menus.forEach(menu => menu.addEventListener('toggle', () => {
                if (!menu.open) return;
                menus.forEach(other => { if (other !== menu) other.open = false; });
            }));
            document.addEventListener('click', event => {
                if (!event.target.closest('.nav-menu')) menus.forEach(menu => { menu.open = false; });
            });
            document.addEventListener('keydown', event => {
                if (event.key === 'Escape') menus.forEach(menu => { menu.open = false; });
            });
        })();
    </script>
    @stack('scripts')
</body>
</html>
