@extends('layouts.app')

@section('title', 'آژانس‌ها و اعتبار کلیک')

@section('content')
    <section class="container admin-page">
        <div class="section-head">
            <div><span class="eyebrow">درآمد کلیکی</span><h1>آژانس‌ها و اعتبار</h1></div>
            <a href="{{ route('admin.tours.index') }}">بازگشت به تورها</a>
        </div>

        @if($errors->any())
            <div class="validation-errors"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif

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

                    <form class="agency-inline-form" method="post" action="{{ route('admin.agencies.update', $agency) }}">
                        @csrf @method('PUT')
                        <label>هزینه هر کلیک (تومان)<input type="number" min="0" name="cost_per_click" value="{{ $agency->cost_per_click }}" required></label>
                        <button class="button" type="submit">ذخیره هزینه</button>
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
