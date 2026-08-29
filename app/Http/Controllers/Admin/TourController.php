<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\AddTourImages;
use App\Jobs\ConvertTourImagesToWebp;
use App\Jobs\RefreshTourImages;
use App\Models\SyncRun;
use App\Models\Tour;
use App\Services\Alerts\PriceAlertNotifier;
use App\Services\Images\TourImageManager;
use App\Services\Images\WebpImageConverter;
use App\Services\PriceCrawler;
use App\Services\TourPriceUpdater;
use App\Services\TourSlugGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class TourController extends Controller
{
    public function __construct(private readonly WebpImageConverter $webp) {}

    public function index(Request $request): View
    {
        $category = array_key_exists($request->string('category')->toString(), config('comparison.categories'))
            ? $request->string('category')->toString()
            : null;
        $tours = Tour::withCount([
            'priceSources',
            'priceSources as priced_sources_count' => fn ($query) => $query
                ->where('is_active', true)
                ->where('latest_price', '>', 0),
        ])->when($category, fn ($query) => $query->where('category', $category))
            ->latest()->paginate(15)->withQueryString();

        return view('admin.tours.index', compact('tours', 'category'));
    }

    public function create(): View
    {
        return view('admin.tours.create', ['tour' => new Tour(['category' => request('category', 'tour')])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        try {
            $tour = Tour::create($data);
        } catch (Throwable $exception) {
            $this->deleteNewImages($data);

            throw $exception;
        }

        return redirect()->route('admin.tours.edit', $tour)->with('success', 'صفحه مقایسه ساخته شد؛ حالا منابع قیمت را اضافه کنید.');
    }

    public function edit(Tour $tour): View
    {
        $tour->load(['priceSources' => fn ($query) => $query
            ->orderByDesc('is_pinned')
            ->orderBy('display_priority')
            ->orderBy('latest_price')]);

        return view('admin.tours.edit', compact('tour'));
    }

    public function update(Request $request, Tour $tour): RedirectResponse
    {
        $oldCover = $tour->cover_image;
        $oldGallery = collect($tour->gallery ?? []);
        $data = $this->validated($request, $tour);

        try {
            $tour->update($data);
        } catch (Throwable $exception) {
            $this->deleteNewImages($data, collect([$oldCover])->concat($oldGallery)->filter()->all());

            throw $exception;
        }

        if (isset($data['cover_image']) && $oldCover && $oldCover !== $data['cover_image']) {
            Storage::disk('public')->delete($oldCover);
        }

        return redirect()->route('admin.tours.edit', $tour)->with('success', 'اطلاعات صفحه مقایسه ذخیره شد.');
    }

    public function destroy(Tour $tour): RedirectResponse
    {
        if ($tour->cover_image) {
            Storage::disk('public')->delete($tour->cover_image);
        }
        Storage::disk('public')->delete($tour->gallery ?? []);
        $tour->delete();

        return redirect()->route('admin.tours.index')->with('success', 'پیشنهاد حذف شد.');
    }

    public function crawl(Tour $tour, TourPriceUpdater $updater, PriceAlertNotifier $alerts): RedirectResponse
    {
        $result = $updater->update($tour);
        $notified = $alerts->notifyForTour($tour);
        $fallback = $result['fallback_checked'] > 0
            ? " و {$result['fallback_checked']} سایت جایگزین"
            : '';
        $target = $result['target_met']
            ? "{$result['prices_found']} قیمت معتبر پیدا شد"
            : "فقط {$result['prices_found']} قیمت پیدا شد و افزودن کراولر جدید لازم است";
        $retained = $result['failed_sources_retained'] > 0
            ? "؛ {$result['failed_sources_retained']} منبع بدون قیمت حفظ شد"
            : '';

        return back()->with(
            $result['target_met'] ? 'success' : 'error',
            "قیمت این پیشنهاد از {$result['primary_checked']} سایت اصلی{$fallback} بررسی شد؛ {$target}{$retained} و {$notified} هشدار ارسال شد."
        );
    }

    public function crawlContent(Tour $tour, PriceCrawler $crawler): RedirectResponse
    {
        $sources = $tour->priceSources()->where('is_active', true)->get();
        $success = $sources->filter(fn ($source) => $crawler->crawlContent($source, true))->count();

        return back()->with('success', "بررسی محتوا تمام شد: {$success} منبع از {$sources->count()} منبع موفق بود.");
    }

    public function refreshImages(Tour $tour): RedirectResponse
    {
        $running = SyncRun::query()
            ->where('type', 'refresh_tour_images')
            ->where('status', 'running')
            ->whereNull('finished_at')
            ->where('details->tour_id', $tour->id)
            ->exists();

        if ($running) {
            return back()->with('error', 'تعویض تصاویر این پیشنهاد از قبل در صف یا در حال اجراست.');
        }

        $run = SyncRun::create([
            'user_id' => auth()->id(),
            'type' => 'refresh_tour_images',
            'total' => 1,
            'details' => ['tour_id' => $tour->id],
            'started_at' => now(),
        ]);

        try {
            RefreshTourImages::dispatch($tour->id, $run->id);

            return back()->with('success', "تعویض تصاویر {$tour->title} در صف قرار گرفت؛ تا دریافت موفق تصاویر جدید، عکس‌های فعلی حفظ می‌شوند.");
        } catch (Throwable $exception) {
            $run->update([
                'status' => 'failed',
                'failed' => 1,
                'error' => mb_substr($exception->getMessage(), 0, 1000),
                'finished_at' => now(),
            ]);
            report($exception);

            return back()->with('error', 'شروع تعویض تصاویر ناموفق بود: '.$exception->getMessage());
        }
    }

    public function addImages(Tour $tour): RedirectResponse
    {
        $running = SyncRun::query()
            ->whereIn('type', ['add_tour_images', 'refresh_tour_images'])
            ->where('status', 'running')
            ->whereNull('finished_at')
            ->where('details->tour_id', $tour->id)
            ->exists();

        if ($running) {
            return back()->with('error', 'عملیات تصاویر این پیشنهاد از قبل در صف یا در حال اجراست.');
        }

        $run = SyncRun::create([
            'user_id' => auth()->id(),
            'type' => 'add_tour_images',
            'total' => 1,
            'details' => ['tour_id' => $tour->id],
            'started_at' => now(),
        ]);

        try {
            AddTourImages::dispatch($tour->id, $run->id);

            return back()->with('success', "افزودن ۳ تصویر جدید به {$tour->title} در صف قرار گرفت؛ تصاویر فعلی حفظ می‌شوند.");
        } catch (Throwable $exception) {
            $run->update([
                'status' => 'failed',
                'failed' => 1,
                'error' => mb_substr($exception->getMessage(), 0, 1000),
                'finished_at' => now(),
            ]);
            report($exception);

            return back()->with('error', 'شروع افزودن تصاویر ناموفق بود: '.$exception->getMessage());
        }
    }

    public function convertImagesToWebp(): RedirectResponse
    {
        $running = SyncRun::query()
            ->where('type', 'images_webp')
            ->where('status', 'running')
            ->whereNull('finished_at')
            ->exists();

        if ($running) {
            return back()->with('error', 'تبدیل تصاویر قبلی به WebP از قبل در صف یا در حال اجراست.');
        }

        $run = SyncRun::create([
            'user_id' => auth()->id(),
            'type' => 'images_webp',
            'started_at' => now(),
        ]);
        ConvertTourImagesToWebp::dispatch($run->id);

        return back()->with('success', 'تبدیل تصاویر قبلی به WebP در صف قرار گرفت؛ فایل قدیمی فقط پس از تبدیل موفق حذف می‌شود.');
    }

    public function uploadImages(Request $request, Tour $tour, TourImageManager $images): RedirectResponse
    {
        $data = $request->validate([
            'images' => ['required', 'array', 'min:1', 'max:12'],
            'images.*' => ['required', 'image', 'max:8192'],
        ]);

        $uploaded = $images->prependUploads($tour, $data['images']);

        return back()->with('success', count($uploaded).' تصویر دستی اضافه شد؛ نخستین تصویر آپلودشده عکس اول پیشنهاد است.');
    }

    public function reorderImages(Request $request, Tour $tour, TourImageManager $images): RedirectResponse
    {
        $data = $request->validate([
            'images' => ['required', 'array', 'min:1'],
            'images.*' => ['required', 'string', 'max:1000'],
        ]);

        $images->reorder($tour, $data['images']);

        return back()->with('success', 'ترتیب نمایش تصاویر ذخیره شد.');
    }

    private function validated(Request $request, ?Tour $tour = null): array
    {
        $requestedSlug = trim((string) $request->input('slug'));
        $request->merge([
            'category' => $request->input('category', $tour?->category ?: 'tour'),
            'slug' => $requestedSlug !== ''
                ? Str::slug($requestedSlug)
                : app(TourSlugGenerator::class)->unique(
                    app(TourSlugGenerator::class)->fromTitle(
                        (string) $request->input('title'),
                        (string) $request->input('category', $tour?->category ?: 'tour'),
                    ),
                    $tour,
                ),
        ]);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'category' => ['required', Rule::in(array_keys(config('comparison.categories')))],
            'slug' => ['required', 'string', 'max:160', Rule::unique('tours')->ignore($tour)],
            'excerpt' => ['nullable', 'string', 'max:300'],
            'description' => ['required', 'string'],
            'cover_image' => ['nullable', 'image', 'max:5120'],
            'gallery' => ['nullable', 'array', 'max:12'],
            'gallery.*' => ['image', 'max:8192'],
            'video_url' => ['nullable', 'url', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');

        if ($request->hasFile('cover_image')) {
            $data['cover_image'] = $this->webp->storeUpload($request->file('cover_image'), 'tours/covers');
        } else {
            unset($data['cover_image']);
        }

        $gallery = $tour?->gallery ?? [];
        foreach ($request->file('gallery', []) as $image) {
            $gallery[] = $this->webp->storeUpload($image, 'tours/gallery');
        }
        $data['gallery'] = $gallery;

        return $data;
    }

    private function deleteNewImages(array $data, array $preserve = []): void
    {
        $paths = collect([$data['cover_image'] ?? null])
            ->concat($data['gallery'] ?? [])
            ->filter()
            ->diff($preserve)
            ->values()
            ->all();

        Storage::disk('public')->delete($paths);
    }
}
