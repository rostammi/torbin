<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProvisionAllSuggestedTours;
use App\Jobs\ProvisionSuggestedTour;
use App\Models\SyncRun;
use App\Models\TourSuggestion;
use App\Services\Discovery\PopularTourDiscovery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class TourSuggestionController extends Controller
{
    public function index(Request $request): View
    {
        $region = in_array($request->string('region')->toString(), ['domestic', 'foreign'], true)
            ? $request->string('region')->toString()
            : 'domestic';
        $status = $request->string('status')->toString();
        $suggestions = TourSuggestion::with('tour')
            ->where('source', 'destination_catalog')
            ->where('metadata->region', $region)
            ->when($status, fn ($query) => $query->where('status', $status))
            ->orderByDesc('trend_score')->latest('discovered_at')->paginate(25)->withQueryString();
        $regionCounts = collect(['domestic', 'foreign'])->mapWithKeys(fn (string $catalogRegion) => [
            $catalogRegion => TourSuggestion::query()
                ->where('source', 'destination_catalog')
                ->where('metadata->region', $catalogRegion)
                ->count(),
        ]);
        $bulkRun = SyncRun::query()->where('type', 'provision_all_tours')->latest()->first();

        return view('admin.suggestions.index', compact('suggestions', 'status', 'region', 'regionCounts', 'bulkRun'));
    }

    public function discover(PopularTourDiscovery $discovery): RedirectResponse
    {
        $run = SyncRun::create(['user_id' => auth()->id(), 'type' => 'discover_tours', 'started_at' => now()]);
        try {
            $result = $discovery->discover();
            $run->update([
                'status' => 'success', 'total' => $result['total'], 'successful' => $result['total'],
                'details' => $result, 'finished_at' => now(),
            ]);

            return back()->with('success', "{$result['domestic_total']} پیشنهاد داخلی و {$result['foreign_total']} پیشنهاد خارجی از کاتالوگ مقصدها آماده شد.");
        } catch (Throwable $exception) {
            $run->update(['status' => 'failed', 'error' => $exception->getMessage(), 'finished_at' => now()]);
            report($exception);

            return back()->with('error', 'دریافت پیشنهادها ناموفق بود: '.$exception->getMessage());
        }
    }

    public function store(TourSuggestion $suggestion): RedirectResponse
    {
        $run = SyncRun::create(['user_id' => auth()->id(), 'type' => 'provision_tour', 'total' => 1, 'started_at' => now()]);
        try {
            ProvisionSuggestedTour::dispatch($suggestion->id, $run->id);
            $suggestion->refresh();
            if ($suggestion->tour) {
                return redirect()->route('admin.tours.edit', $suggestion->tour)
                    ->with('success', 'تور و ارائه‌دهنده‌ها ساخته و کراول اولیه اجرا شد.');
            }

            return back()->with('success', 'ساخت تور در صف قرار گرفت؛ نتیجه را در مرکز همگام‌سازی ببینید.');
        } catch (Throwable $exception) {
            $suggestion->update(['status' => 'failed']);
            $run->update(['status' => 'failed', 'failed' => 1, 'error' => $exception->getMessage(), 'finished_at' => now()]);
            report($exception);

            return back()->with('error', 'ساخت خودکار تور ناموفق بود: '.$exception->getMessage());
        }
    }

    public function storeAll(): RedirectResponse
    {
        $running = SyncRun::query()
            ->where('type', 'provision_all_tours')
            ->where('status', 'running')
            ->whereNull('finished_at')
            ->latest()
            ->first();

        if ($running) {
            return back()->with('error', 'ساخت و به‌روزرسانی همه تورها از قبل در حال اجراست.');
        }

        $total = TourSuggestion::query()->where('source', 'destination_catalog')->count();
        if ($total === 0) {
            return back()->with('error', 'ابتدا پیشنهادهای مقصد را دریافت کنید.');
        }

        $run = SyncRun::create([
            'user_id' => auth()->id(),
            'type' => 'provision_all_tours',
            'total' => $total,
            'started_at' => now(),
        ]);

        try {
            ProvisionAllSuggestedTours::dispatch($run->id);

            return back()->with('success', "جاب ساخت و به‌روزرسانی {$total} پیشنهاد در صف قرار گرفت؛ تورهای موجود فقط به‌روزرسانی می‌شوند.");
        } catch (Throwable $exception) {
            $run->update([
                'status' => 'failed',
                'error' => $exception->getMessage(),
                'finished_at' => now(),
            ]);
            report($exception);

            return back()->with('error', 'شروع جاب ساخت همه تورها ناموفق بود: '.$exception->getMessage());
        }
    }
}
