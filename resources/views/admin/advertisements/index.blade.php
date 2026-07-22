@extends('layouts.app')

@section('title', 'مدیریت تبلیغات')

@section('content')
    <section class="container admin-page">
        <div class="section-head">
            <div><span class="eyebrow">درآمد تبلیغاتی</span><h1>بنرها و اسلایدرها</h1></div>
            <a class="button" href="{{ route('admin.advertisements.create') }}">+ تبلیغ جدید</a>
        </div>

        <form class="filter-tabs" method="get">
            <a class="{{ $placement === '' ? 'active' : '' }}" href="{{ route('admin.advertisements.index') }}">همه</a>
            @foreach(\App\Models\Advertisement::PLACEMENTS as $value => $label)
                <a class="{{ $placement === $value ? 'active' : '' }}" href="{{ route('admin.advertisements.index', ['placement' => $value]) }}">{{ $label }}</a>
            @endforeach
        </form>

        <div class="panel table-wrap">
            <table class="advertisement-table">
                <thead><tr><th>کمپین</th><th>جایگاه</th><th>زمان‌بندی</th><th>بازدید / کلیک</th><th>وضعیت</th><th>عملیات</th></tr></thead>
                <tbody>
                    @forelse($advertisements as $advertisement)
                        <tr>
                            <td><strong>{{ $advertisement->name }}</strong><small>{{ $advertisement->advertiser_name }}</small>@if($advertisement->contract_amount)<small>قرارداد: {{ number_format($advertisement->contract_amount) }} {{ $advertisement->contract_currency }}</small>@endif</td>
                            <td>{{ $advertisement->placement_label }}<small>اولویت {{ $advertisement->priority }}</small></td>
                            <td><small>از {{ $advertisement->starts_at?->format('Y/m/d H:i') ?? 'همین حالا' }}</small><small>تا {{ $advertisement->ends_at?->format('Y/m/d H:i') ?? 'بدون پایان' }}</small></td>
                            <td><strong>{{ number_format($advertisement->impressions) }} / {{ number_format($advertisement->clicks) }}</strong><small>CTR: {{ number_format($advertisement->click_through_rate, 2) }}٪</small></td>
                            <td><span class="status {{ $advertisement->is_active ? 'success' : '' }}">{{ $advertisement->is_active ? 'فعال' : 'غیرفعال' }}</span></td>
                            <td class="actions"><a href="{{ route('admin.advertisements.edit', $advertisement) }}">ویرایش</a><form method="post" action="{{ route('admin.advertisements.destroy', $advertisement) }}" onsubmit="return confirm('این تبلیغ حذف شود؟')">@csrf @method('DELETE')<button class="danger-link">حذف</button></form></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="empty-cell">هنوز تبلیغی برای این جایگاه ثبت نشده است.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $advertisements->links() }}
    </section>
@endsection
