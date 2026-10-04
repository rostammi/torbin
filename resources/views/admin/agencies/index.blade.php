@extends('layouts.app')

@section('title', 'آژانس‌ها و اعتبار کلیک')

@section('content')
    <section class="container admin-page">
        <div class="section-head">
            <div><span class="eyebrow">درآمد کلیکی</span><h1>آژانس‌ها و اعتبار</h1></div>
            <a href="{{ route('admin.tours.index') }}">بازگشت به پیشنهادها</a>
        </div>

        @if($errors->any())
            <div class="validation-errors"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif

        <form class="panel admin-form comparison-contact-settings category-contact-settings" method="post" action="{{ route('admin.agencies.comparison-contact') }}">
            @csrf @method('PUT')
            <div>
                <span class="eyebrow">پیشنهاد بدون قیمت</span>
                <h2>شماره تماس دسته‌بندی‌ها</h2>
                <p class="muted">شماره مرتبط با دسته، کنار منبع بدون قیمت منتخب و دکمه «تماس بگیرید» نمایش داده می‌شود.</p>
            </div>
            <div class="category-contact-grid">
                @foreach(config('comparison.categories') as $key => $item)
                    <label>{{ $item['plural'] }}
                        <input name="comparison_contact_phones[{{ $key }}]" dir="ltr" value="{{ old("comparison_contact_phones.{$key}", $comparisonContactPhones[$key]) }}" required maxlength="30">
                    </label>
                @endforeach
            </div>
            <button class="button" type="submit">ذخیره شماره‌های تماس</button>
        </form>

        @if($contactOrderAgencies->isNotEmpty())
            <form class="panel admin-form contact-source-order" method="post" action="{{ route('admin.agencies.contact-order') }}" data-contact-order-form>
                @csrf @method('PUT')
                <div class="contact-source-order-head">
                    <div>
                        <span class="eyebrow">کنترل منبع انتهای فهرست</span>
                        <h2>ترتیب انتخاب «تماس بگیرید»</h2>
                        <p class="muted">اولین منبع این فهرست که در صفحه قیمت ندارد، به‌عنوان آخرین پیشنهاد نمایش داده می‌شود. ردیف‌ها را جابه‌جا و سپس ذخیره کنید.</p>
                    </div>
                    <button class="button" type="submit">ذخیره ترتیب</button>
                </div>
                <ol class="contact-source-order-list" data-contact-order-list>
                    @foreach($contactOrderAgencies as $agency)
                        <li class="contact-source-order-row" draggable="true" data-contact-order-row>
                            <input type="hidden" name="agencies[]" value="{{ $agency->id }}">
                            <span class="contact-order-handle" aria-hidden="true">⋮⋮</span>
                            <span class="contact-order-rank" data-contact-order-rank>{{ $loop->iteration }}</span>
                            <span class="contact-order-name"><strong>{{ $agency->name }}</strong><small>{{ $agency->price_sources_count }} پیشنهاد</small></span>
                            <span class="contact-order-actions">
                                <button type="button" data-move="up" aria-label="انتقال {{ $agency->name }} به بالا">↑</button>
                                <button type="button" data-move="down" aria-label="انتقال {{ $agency->name }} به پایین">↓</button>
                            </span>
                        </li>
                    @endforeach
                </ol>
            </form>
        @endif

        <form method="get" class="admin-table-search" role="search">
            <label><span class="sr-only">جست‌وجوی آژانس</span><input type="search" name="q" value="{{ request('q') }}" placeholder="جست‌وجو در نام، شماره تماس یا ایمیل…" maxlength="100"></label>
            <select name="sort" aria-label="مرتب‌سازی آژانس‌ها">
                <option value="name" @selected(request('sort', 'name') === 'name')>نام</option>
                <option value="balance" @selected(request('sort') === 'balance')>اعتبار</option>
                <option value="sources" @selected(request('sort') === 'sources')>تعداد پیشنهادها</option>
                <option value="clicks" @selected(request('sort') === 'clicks')>تعداد کلیک‌ها</option>
                <option value="charged" @selected(request('sort') === 'charged')>مبلغ کسرشده</option>
                <option value="cost" @selected(request('sort') === 'cost')>هزینه هر کلیک</option>
            </select>
            <select name="direction" aria-label="جهت مرتب‌سازی"><option value="asc" @selected(request('direction', 'asc') === 'asc')>صعودی</option><option value="desc" @selected(request('direction') === 'desc')>نزولی</option></select>
            <button class="button button-secondary compact-button">اعمال</button>
            @if(request()->filled('q'))<a class="admin-search-clear" href="{{ route('admin.agencies.index', request()->except(['q', 'page'])) }}">پاک‌کردن</a>@endif
        </form>

        <div class="agency-grid">
            @forelse($agencies as $agency)
                <article class="panel agency-card">
                    <header class="agency-card-head">
                        <div><h2>{{ $agency->name }}</h2><span>{{ $agency->price_sources_count }} پیشنهاد فعال/غیرفعال</span></div>
                        <strong class="agency-balance {{ $agency->canAffordClick() ? '' : 'low' }}">{{ number_format($agency->balance) }} <small>{{ $agency->currency }}</small></strong>
                    </header>

                    <div class="agency-stats">
                        <div><span>کل کلیک‌ها</span><b>{{ number_format($agency->clicks_count) }}</b></div>
                        <div><span>کلیک‌های پولی</span><b>{{ number_format($agency->charged_clicks_count) }}</b></div>
                        <div><span>مبلغ کسرشده</span><b>{{ number_format($agency->total_charged ?? 0) }}</b></div>
                        <div><span>هزینه هر کلیک</span><b>{{ number_format($agency->cost_per_click) }}</b></div>
                    </div>

                    <form class="admin-form agency-inline-form" method="post" action="{{ route('admin.agencies.update', $agency) }}">
                        @csrf @method('PUT')
                        <h3>تنظیمات عمومی منبع</h3>
                        <p class="muted">این تنظیمات روی تمام پیشنهادهای {{ $agency->name }} در همه تورها، هتل‌ها و سایر دسته‌ها اعمال می‌شود.</p>
                        <div class="form-grid">
                            <label>نام منبع<input name="name" value="{{ old('name', $agency->name) }}" required maxlength="120"></label>
                            <label>شماره تماس اختصاصی<input type="tel" dir="ltr" name="contact_phone" value="{{ old('contact_phone', $agency->contact_phone) }}" placeholder="{{ $comparisonContactPhone }}"></label>
                        </div>
                        <div class="form-grid">
                            <label>اولویت نمایش<input type="number" min="0" max="10000" name="display_priority" value="{{ $agency->display_priority }}" required><small>عدد کمتر، نمایش زودتر</small></label>
                            <label>هزینه هر کلیک<input type="number" min="0" name="cost_per_click" value="{{ $agency->cost_per_click }}" required></label>
                        </div>
                        <label class="check-label"><input type="checkbox" name="is_contact_only" value="1" @checked($agency->is_contact_only)> همیشه به‌جای لینک خرید، شماره تماس نمایش داده شود</label>
                        <label class="check-label"><input type="checkbox" name="is_featured" value="1" @checked($agency->is_featured)> همه پیشنهادهای این منبع نشان «پیشنهاد ویژه» داشته باشند</label>
                        <label class="check-label"><input type="checkbox" name="is_pinned" value="1" @checked($agency->is_pinned)> این منبع در همه صفحات ابتدای فهرست باشد <small>(خودکار ویژه می‌شود)</small></label>
                        <button class="button" type="submit">اعمال روی همه پیشنهادها</button>
                    </form>

                    <form class="agency-balance-form" method="post" action="{{ route('admin.agencies.balance', $agency) }}">
                        @csrf
                        <select name="type" required><option value="credit">افزودن اعتبار</option><option value="debit">کاهش اعتبار</option></select>
                        <input type="number" min="1" name="amount" placeholder="مبلغ به تومان" required>
                        <input name="note" maxlength="500" placeholder="توضیح تراکنش (اختیاری)">
                        <button class="button button-secondary" type="submit">ثبت تراکنش</button>
                    </form>

                    @php($agencyAccount = $agency->users->first())
                    <details class="agency-access">
                        <summary>{{ $agencyAccount ? 'ویرایش دسترسی داشبورد' : 'ساخت حساب ورود آژانس' }}</summary>
                        <form method="post" action="{{ route('admin.agencies.access', $agency) }}">
                            @csrf
                            <label>ایمیل ورود<input type="email" name="email" value="{{ old('email', $agencyAccount?->email) }}" required></label>
                            <div class="form-grid">
                                <label>رمز عبور {{ $agencyAccount ? '(برای عدم تغییر خالی بگذارید)' : '' }}<input type="password" name="password" @required(!$agencyAccount)></label>
                                <label>تکرار رمز عبور<input type="password" name="password_confirmation" @required(!$agencyAccount)></label>
                            </div>
                            <button class="button button-secondary" type="submit">ذخیره دسترسی</button>
                        </form>
                    </details>

                    @if($agency->creditTransactions->isNotEmpty())
                        <details class="agency-ledger">
                            <summary>۵ تراکنش اخیر</summary>
                            @foreach($agency->creditTransactions as $transaction)
                                <div>
                                    <span>{{ ['click_charge' => 'هزینه کلیک', 'manual_credit' => 'افزایش دستی', 'manual_debit' => 'کاهش دستی'][$transaction->type] ?? $transaction->type }}</span>
                                    <b class="{{ $transaction->amount < 0 ? 'negative' : 'positive' }}">{{ $transaction->amount > 0 ? '+' : '' }}{{ number_format($transaction->amount) }}</b>
                                    <small>مانده: {{ number_format($transaction->balance_after) }}</small>
                                </div>
                            @endforeach
                        </details>
                    @endif
                </article>
            @empty
                <div class="empty-state"><p>هنوز آژانسی از منابع قیمت ساخته نشده است.</p></div>
            @endforelse
        </div>

        {{ $agencies->links() }}
    </section>
@endsection

@push('scripts')
    <script>
        (() => {
            const list = document.querySelector('[data-contact-order-list]');
            if (!list) return;

            const refresh = () => {
                const rows = [...list.querySelectorAll('[data-contact-order-row]')];
                rows.forEach((row, index) => {
                    row.querySelector('[data-contact-order-rank]').textContent = index + 1;
                    row.querySelector('[data-move="up"]').disabled = index === 0;
                    row.querySelector('[data-move="down"]').disabled = index === rows.length - 1;
                });
            };

            list.addEventListener('click', event => {
                const button = event.target.closest('[data-move]');
                if (!button) return;
                const row = button.closest('[data-contact-order-row]');
                if (button.dataset.move === 'up' && row.previousElementSibling) {
                    list.insertBefore(row, row.previousElementSibling);
                } else if (button.dataset.move === 'down' && row.nextElementSibling) {
                    list.insertBefore(row.nextElementSibling, row);
                }
                refresh();
            });

            let dragged;
            list.addEventListener('dragstart', event => {
                dragged = event.target.closest('[data-contact-order-row]');
                dragged?.classList.add('is-dragging');
            });
            list.addEventListener('dragover', event => {
                event.preventDefault();
                const target = event.target.closest('[data-contact-order-row]');
                if (!dragged || !target || dragged === target) return;
                const rect = target.getBoundingClientRect();
                list.insertBefore(dragged, event.clientY < rect.top + rect.height / 2 ? target : target.nextElementSibling);
            });
            list.addEventListener('dragend', () => {
                dragged?.classList.remove('is-dragging');
                dragged = null;
                refresh();
            });

            refresh();
        })();
    </script>
@endpush
