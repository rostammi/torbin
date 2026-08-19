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
        $buildableCount = TourSuggestion::query()
            ->where('category', $category)
            ->where(fn ($query) => $query->where('status', '!=', 'created')->orWhereNull('status'))
            ->count();
        $updatableCount = TourSuggestion::query()
            ->where('category', $category)
            ->where('status', 'created')
            ->count();
        $bulkRun = SyncRun::query()->where('type', 'provision_all_tours')->latest()->first();

        return view('admin.suggestions.index', compact(
            'suggestions', 'status', 'region', 'category', 'regionCounts', 'categoryCounts',
            'buildableCount', 'updatableCount', 'bulkRun'
        ));
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
        $mode = $request->string('mode')->toString() === 'update' ? 'update' : 'create';
        $running = SyncRun::query()
            ->where('type', 'provision_all_tours')
            ->where('status', 'running')
            ->whereNull('finished_at')
            ->latest()
            ->first();

        if ($running) {
            return back()->with('error', 'یک عملیات گروهی ساخت یا به‌روزرسانی از قبل در حال اجراست.');
        }

        $total = TourSuggestion::query()
            ->when($category, fn ($query) => $query->where('category', $category))
            ->when($mode === 'create', fn ($query) => $query->where(
                fn ($query) => $query->where('status', '!=', 'created')->orWhereNull('status')
            ))
            ->when($mode === 'update', fn ($query) => $query->where('status', 'created'))
            ->count();
        if ($total === 0) {
            return back()->with('error', $mode === 'create'
                ? 'پیشنهاد ساخته‌نشده‌ای در این دسته وجود ندارد.'
                : 'صفحه ساخته‌شده‌ای در این دسته وجود ندارد.');
        }

        $run = SyncRun::create([
            'user_id' => auth()->id(),
            'type' => 'provision_all_tours',
            'total' => $total,
            'details' => ['category' => $category, 'mode' => $mode, 'source_pattern' => null],
            'started_at' => now(),
        ]);

        try {
            ProvisionAllSuggestedTours::dispatch($run->id, $category, null, false, false, [], $mode);

            return back()->with('success', $mode === 'create'
                ? "ساخت {$total} پیشنهاد ساخته‌نشده در صف قرار گرفت."
                : "به‌روزرسانی {$total} صفحه ساخته‌شده در صف قرار گرفت.");
        } catch (Throwable $exception) {
            $run->update([
                'status' => 'failed',
                'error' => $exception->getMessage(),
                'finished_at' => now(),
            ]);
            report($exception);

            return back()->with('error', ($mode === 'create'
                ? 'شروع ساخت پیشنهادها ناموفق بود: '
                : 'شروع به‌روزرسانی صفحات ناموفق بود: ').$exception->getMessage());
        }
    }
}
