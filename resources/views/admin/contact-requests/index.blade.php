@extends('layouts.app')

@section('title', 'شماره‌ها و درخواست‌های تماس')

@section('content')
    <section class="container admin-page">
        <div class="section-head">
            <div><span class="eyebrow">پیگیری کاربران</span><h1>شماره‌ها و درخواست‌های تماس</h1></div>
        </div>

        <div class="contact-request-filters">
            <div class="filter-tabs">
                <a class="{{ $status === '' ? 'active' : '' }}" href="{{ route('admin.contact-requests.index', ['origin' => $origin ?: null]) }}">همه وضعیت‌ها</a>
                @foreach(\App\Models\PriceAlert::CONTACT_STATUSES as $value => $label)
                    <a class="{{ $status === $value ? 'active' : '' }}" href="{{ route('admin.contact-requests.index', ['status' => $value, 'origin' => $origin ?: null]) }}">{{ $label }}</a>
                @endforeach
            </div>
            <div class="filter-tabs">
                <a class="{{ $origin === '' ? 'active' : '' }}" href="{{ route('admin.contact-requests.index', ['status' => $status ?: null]) }}">همه منابع ثبت</a>
                @foreach(\App\Models\PriceAlert::ORIGINS as $value => $label)
                    <a class="{{ $origin === $value ? 'active' : '' }}" href="{{ route('admin.contact-requests.index', ['status' => $status ?: null, 'origin' => $value]) }}">{{ $label }}</a>
                @endforeach
            </div>
        </div>

        <div class="panel table-wrap">
            <table class="contact-request-table">
                <thead><tr><th>شماره تماس</th><th>صفحه مقایسه</th><th>نحوه ثبت</th><th>زمان ثبت</th><th>وضعیت پیگیری</th></tr></thead>
                <tbody>
                    @forelse($requests as $contactRequest)
                        <tr>
                            <td><a class="admin-phone-link" dir="ltr" href="tel:{{ $contactRequest->phone }}">{{ $contactRequest->phone }}</a></td>
                            <td><a href="{{ $contactRequest->tour->publicUrl() }}" target="_blank">{{ $contactRequest->tour->title }}</a><small>{{ $contactRequest->tour->categoryLabel() }}</small></td>
                            <td><span class="request-origin {{ $contactRequest->origin }}">{{ $contactRequest->originLabel() }}</span>@if($contactRequest->target_price)<small>مبنای هشدار: {{ number_format($contactRequest->target_price) }} {{ $contactRequest->currency }}</small>@endif</td>
                            <td>{{ $contactRequest->created_at->format('Y/m/d H:i') }}@if($contactRequest->contacted_at)<small>تماس: {{ $contactRequest->contacted_at->format('Y/m/d H:i') }}</small>@endif</td>
                            <td>
                                <form class="contact-status-form" method="post" action="{{ route('admin.contact-requests.update', $contactRequest) }}">
                                    @csrf
                                    @method('PUT')
                                    <select name="contact_status" aria-label="وضعیت پیگیری">
                                        @foreach(\App\Models\PriceAlert::CONTACT_STATUSES as $value => $label)
                                            <option value="{{ $value }}" @selected($contactRequest->contact_status === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <button class="button compact-button" type="submit">ذخیره</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty-cell">شماره‌ای با این فیلتر پیدا نشد.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $requests->links('pagination.admin') }}
    </section>
@endsection
