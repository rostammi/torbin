<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\RunAutomationSync;
use App\Models\PriceSource;
use App\Models\SyncRun;
use App\Models\TourSuggestion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SyncController extends Controller
{
    public function index(): View
    {
        $runs = SyncRun::with('user')->latest()->paginate(20);
        $stats = [
            'sources' => PriceSource::where('is_active', true)->count(),
            'stale_prices' => PriceSource::where('is_active', true)->where(fn ($q) => $q->whereNull('last_checked_at')->orWhere('last_checked_at', '<', now()->subHour()))->count(),
            'stale_content' => PriceSource::where('is_active', true)->where(fn ($q) => $q->whereNull('content_checked_at')->orWhere('content_checked_at', '<', now()->subDay()))->count(),
            'suggestions' => TourSuggestion::where('status', 'pending')->count(),
        ];

        return view('admin.sync.index', compact('runs', 'stats'));
    }

    public function run(Request $request): RedirectResponse
    {
        $data = $request->validate(['type' => ['required', Rule::in(['discover_tours', 'prices', 'content', 'all'])]]);
        $run = SyncRun::create(['user_id' => auth()->id(), 'type' => $data['type'], 'started_at' => now()]);
        RunAutomationSync::dispatch($run->id);

        return back()->with('success', 'عملیات در صف اجرا قرار گرفت؛ وضعیت آن در جدول همین صفحه به‌روز می‌شود.');
    }
}
