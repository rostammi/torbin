<?php

namespace App\Http\Controllers;

use App\Models\ComparisonReport;
use App\Models\Tour;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ComparisonReportController extends Controller
{
    public function store(Request $request, Tour $tour): RedirectResponse
    {
        abort_unless($tour->is_active, 404);
        $data = $request->validate([
            'report_type' => ['required', Rule::in(array_keys(ComparisonReport::TYPES))],
            'report_details' => ['nullable', 'string', 'max:1000'],
            'website' => ['nullable', 'max:0'],
        ], [
            'report_type.required' => 'نوع مشکل را انتخاب کنید.',
            'report_type.in' => 'نوع گزارش انتخاب‌شده معتبر نیست.',
            'report_details.max' => 'توضیحات گزارش حداکثر می‌تواند ۱۰۰۰ نویسه باشد.',
        ]);

        $tour->comparisonReports()->create([
            'type' => $data['report_type'],
            'details' => trim((string) ($data['report_details'] ?? '')) ?: null,
            'status' => 'pending',
            'ip_hash' => $request->ip() ? hash_hmac('sha256', $request->ip(), config('app.key')) : null,
            'user_agent_hash' => $request->userAgent() ? hash('sha256', $request->userAgent()) : null,
        ]);

        return back()->with('success', 'گزارش شما ثبت شد؛ ممنون که به بهبود اطلاعات گیت کمک می‌کنید.');
    }
}
