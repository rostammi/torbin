<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\AddTourImages;
use App\Jobs\CrawlMissingTourImages;
use App\Jobs\ProvisionAllSuggestedTours;
use App\Jobs\ProvisionSuggestedTour;
use App\Jobs\RefreshTourImages;
use App\Jobs\RunAutomationSync;
use App\Jobs\ScanComparisonSource;
use App\Models\PriceSource;
use App\Models\SyncRun;
use App\Models\Tour;
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
        $suggestionsByCategory = TourSuggestion::query()
            ->where('status', 'pending')
            ->selectRaw('category, COUNT(*) as aggregate')
            ->groupBy('category')
            ->pluck('aggregate', 'category');
        $stats = [
            'sources' => PriceSource::where('is_active', true)->count(),
            'stale_prices' => PriceSource::where('is_active', true)->where(fn ($q) => $q->whereNull('last_checked_at')->orWhere('last_checked_at', '<', now()->subHour()))->count(),
            'stale_content' => PriceSource::where('is_active', true)->where(fn ($q) => $q->whereNull('content_checked_at')->orWhere('content_checked_at', '<', now()->subDay()))->count(),
            'suggestions_by_category' => $suggestionsByCategory,
            'missing_images_by_category' => Tour::query()
                ->where(fn ($query) => $query->whereNull('cover_image')->orWhere('cover_image', ''))
                ->selectRaw('category, COUNT(*) as aggregate')
                ->groupBy('category')
                ->pluck('aggregate', 'category'),
        ];

        return view('admin.sync.index', compact('runs', 'stats'));
    }

    public function run(Request $request): RedirectResponse
    {
        $data = $request->validate(['type' => ['required', Rule::in([
            'discover_tours', 'discover_hotels', 'discover_stays', 'discover_visas',
            'prices', 'content', 'images', 'images_tours', 'images_hotels', 'images_stays', 'images_visas', 'all',
        ])]]);
        $imageCategories = [
            'images' => null,
            'images_tours' => 'tour',
            'images_hotels' => 'hotel',
            'images_stays' => 'stay',
            'images_visas' => 'visa',
        ];
        $isImageRun = array_key_exists($data['type'], $imageCategories);
        $conflictingImageTypes = $data['type'] === 'images'
            ? array_keys($imageCategories)
            : ['images', $data['type']];

        if ($isImageRun && SyncRun::query()
            ->whereIn('type', $conflictingImageTypes)
            ->where('status', 'running')
            ->whereNull('finished_at')
            ->exists()) {
            return back()->with('error', 'دریافت تصاویر این دسته از قبل در صف یا در حال اجراست.');
        }

        $run = SyncRun::create(['user_id' => auth()->id(), 'type' => $data['type'], 'started_at' => now()]);
        if ($isImageRun) {
            CrawlMissingTourImages::dispatch($run->id, $imageCategories[$data['type']]);
        } else {
            RunAutomationSync::dispatch($run->id);
        }

        return back()->with('success', 'عملیات در صف اجرا قرار گرفت؛ وضعیت آن در جدول همین صفحه به‌روز می‌شود.');
    }

    public function cancel(SyncRun $syncRun): RedirectResponse
    {
        if (! $syncRun->canCancel()) {
            return back()->with('error', 'این عملیات دیگر در حال اجرا نیست.');
        }

        $syncRun->update([
            'status' => 'cancelled',
            'error' => 'عملیات توسط مدیر لغو شد.',
            'finished_at' => now(),
        ]);

        return back()->with('success', 'درخواست لغو ثبت شد؛ پردازش پس از پایان آیتم جاری متوقف می‌شود.');
    }

    public function retry(SyncRun $syncRun): RedirectResponse
    {
        if (! $syncRun->canRetry()) {
            return back()->with('error', 'تلاش مجدد خودکار برای این نوع عملیات در دسترس نیست.');
        }

        $failedOnly = $syncRun->status === 'partial';
        $details = $syncRun->details ?? [];
        $newRun = SyncRun::create([
            'user_id' => auth()->id(),
            'type' => $syncRun->type,
            'total' => $failedOnly ? $syncRun->failed : 0,
            'details' => ['retry_of' => $syncRun->id, 'failed_only' => $failedOnly],
            'started_at' => now(),
        ]);

        match ($syncRun->type) {
            'images', 'images_tours', 'images_hotels', 'images_stays', 'images_visas' => CrawlMissingTourImages::dispatch(
                $newRun->id,
                $this->imageCategories()[$syncRun->type],
                $failedOnly ? data_get($details, 'images.failed_tour_ids', []) : [],
            ),
            'provision_all_tours' => ProvisionAllSuggestedTours::dispatch(
                $newRun->id,
                data_get($details, 'category'),
                '%_catalog',
                $failedOnly,
                false,
                $failedOnly ? data_get($details, 'failed_suggestion_ids', []) : [],
            ),
            'provision_tour' => ProvisionSuggestedTour::dispatch((int) data_get($details, 'suggestion_id'), $newRun->id),
            'scan_comparison_source' => ScanComparisonSource::dispatch((int) data_get($details, 'source_id'), $newRun->id),
            'add_tour_images' => AddTourImages::dispatch((int) data_get($details, 'tour_id'), $newRun->id),
            'refresh_tour_images' => RefreshTourImages::dispatch((int) data_get($details, 'tour_id'), $newRun->id),
            default => RunAutomationSync::dispatch($newRun->id, $failedOnly ? $this->failedTargets($syncRun) : []),
        };

        return back()->with('success', $failedOnly
            ? 'تلاش مجدد برای موارد ناموفق در صف قرار گرفت.'
            : 'عملیات برای تلاش مجدد در صف قرار گرفت.');
    }

    private function imageCategories(): array
    {
        return [
            'images' => null,
            'images_tours' => 'tour',
            'images_hotels' => 'hotel',
            'images_stays' => 'stay',
            'images_visas' => 'visa',
        ];
    }

    private function failedTargets(SyncRun $run): array
    {
        return [
            'prices' => data_get($run->details, 'prices.failed_tour_ids', []),
            'content' => data_get($run->details, 'content.failed_source_ids', []),
            'images' => data_get($run->details, 'images.failed_tour_ids', []),
        ];
    }
}
