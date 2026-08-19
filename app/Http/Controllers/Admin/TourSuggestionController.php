<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProvisionAllSuggestedTours;
use App\Jobs\ProvisionSuggestedTour;
use App\Models\SyncRun;
use App\Models\TourSuggestion;
use App\Services\Discovery\ComparisonCatalogDiscovery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class TourSuggestionController extends Controller
{
    public function index(Request $request): View
    {
        $category = array_key_exists($request->string('category')->toString(), config('comparison.categories'))
            ? $request->string('category')->toString()
            : 'tour';
        $region = in_array($request->string('region')->toString(), ['domestic', 'foreign'], true)
            ? $request->string('region')->toString()
            : 'domestic';
        $status = $request->string('status')->toString();
        $suggestions = TourSuggestion::with('tour')
            ->where('category', $category)
            ->when($category === 'tour', fn ($query) => $query->where('metadata->region', $region))
            ->when($status, fn ($query) => $query->where('status', $status))
            ->orderByDesc('trend_score')->latest('discovered_at')->paginate(25)->withQueryString();
        $regionCounts = collect(['domestic', 'foreign'])->mapWithKeys(fn (string $catalogRegion) => [
            $catalogRegion => TourSuggestion::query()
                ->where('source', 'destination_catalog')
                ->where('metadata->region', $catalogRegion)
                ->count(),
        ]);
        $categoryCounts = TourSuggestion::query()
            ->selectRaw('category, count(*) as total')
            ->groupBy('category')
            ->pluck('total', 'category');
        $bulkRun = SyncRun::query()->where('type', 'provision_all_tours')->latest()->first();

        return view('admin.suggestions.index', compact('suggestions', 'status', 'region', 'category', 'regionCounts', 'categoryCounts', 'bulkRun'));
    }

    public function discover(ComparisonCatalogDiscovery $discovery): RedirectResponse
    {
        $run = SyncRun::create(['user_id' => auth()->id(), 'type' => 'discover_tours', 'started_at' => now()]);
        try {
            $result = $discovery->discover();
            $run->update([
                'status' => 'success', 'total' => $result['total'], 'successful' => $result['total'],
                'details' => $result, 'finished_at' => now(),
            ]);

            return back()->with('success', "{$result['total']} پیشنهاد در چهار دسته تور، هتل، اقامتگاه و ویزا آماده شد.");
        } catch (Throwable $exception) {
            $run->update(['status' => 'failed', 'error' => $exception->getMessage(), 'finished_at' => now()]);
            report($exception);

            return back()->with('error', 'دریافت پیشنهادها ناموفق بود: '.$exception->getMessage());
        }
    }

    public function store(TourSuggestion $suggestion): RedirectResponse
    {
        $run = SyncRun::create([
            'user_id' => auth()->id(),
            'type' => 'provision_tour',
            'total' => 1,
            'details' => ['suggestion_id' => $suggestion->id],
            'started_at' => now(),
        ]);
        try {
            ProvisionSuggestedTour::dispatch($suggestion->id, $run->id);
            $suggestion->refresh();
            if ($suggestion->tour) {
                return redirect()->route('admin.tours.edit', $suggestion->tour)
                    ->with('success', 'صفحه مقایسه همراه با ارائه‌دهنده‌ها، قیمت، محتوا و تصاویر ساخته شد.');
            }

            return back()->with('success', 'ساخت تور در صف قرار گرفت؛ نتیجه را در مرکز همگام‌سازی ببینید.');
        } catch (Throwable $exception) {
            $suggestion->update(['status' => 'failed']);
            $run->update(['status' => 'failed', 'failed' => 1, 'error' => $exception->getMessage(), 'finished_at' => now()]);
            report($exception);

            return back()->with('error', 'ساخت خودکار تور ناموفق بود: '.$exception->getMessage());
        }
    }

    public function storeAll(Request $request): RedirectResponse
    {
        $category = array_key_exists($request->string('category')->toString(), config('comparison.categories'))
            ? $request->string('category')->toString()
            : null;
        $running = SyncRun::query()
            ->where('type', 'provision_all_tours')
            ->where('status', 'running')
            ->whereNull('finished_at')
            ->latest()
            ->first();

        if ($running) {
            return back()->with('error', 'ساخت و به‌روزرسانی همه تورها از قبل در حال اجراست.');
        }

        $total = TourSuggestion::query()
            ->where('source', 'like', '%_catalog')
            ->when($category, fn ($query) => $query->where('category', $category))
            ->count();
        if ($total === 0) {
            return back()->with('error', 'ابتدا پیشنهادهای مقصد را دریافت کنید.');
        }

        $run = SyncRun::create([
            'user_id' => auth()->id(),
            'type' => 'provision_all_tours',
            'total' => $total,
            'details' => ['category' => $category],
            'started_at' => now(),
        ]);

        try {
            ProvisionAllSuggestedTours::dispatch($run->id, $category);

            return back()->with('success', "جاب تکمیل {$total} صفحه مقایسه همراه با قیمت، محتوا و تصاویر در صف قرار گرفت؛ تورهای موجود فقط به‌روزرسانی می‌شوند.");
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
