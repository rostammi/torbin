<?php

namespace App\Jobs;

use App\Models\Advertisement;
use App\Models\SyncRun;
use App\Models\Tour;
use App\Services\Images\AdvertisementImageWebpMigrator;
use App\Services\Images\TourImageWebpMigrator;
use App\Services\Images\UnreferencedPublicImageWebpMigrator;
use App\Services\Seo\SitemapGenerator;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ConvertTourImagesToWebp implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 86_400;

    public int $tries = 1;

    public int $uniqueFor = 86_400;

    public bool $failOnTimeout = true;

    public function __construct(public int $runId) {}

    public function uniqueId(): string
    {
        return 'convert-tour-images-to-webp';
    }

    public function handle(
        TourImageWebpMigrator $tourMigrator,
        AdvertisementImageWebpMigrator $advertisementMigrator,
        UnreferencedPublicImageWebpMigrator $unreferencedMigrator,
        SitemapGenerator $sitemap,
    ): void {
        $run = SyncRun::findOrFail($this->runId);
        $tourQuery = Tour::query()
            ->where(fn ($query) => $query->whereNotNull('cover_image')->orWhereNotNull('gallery'))
            ->orderBy('id');
        $advertisementQuery = Advertisement::query()->whereNotNull('image_path')->orderBy('id');
        $autoRefreshSitemap = config('seo.sitemap.auto_refresh', true);
        config(['seo.sitemap.auto_refresh' => false]);

        try {
        $run->update([
            'status' => 'running',
            'total' => (clone $tourQuery)->count() + (clone $advertisementQuery)->count(),
            'successful' => 0,
            'failed' => 0,
            'error' => null,
        ]);
        $details = ['converted' => 0, 'skipped' => 0, 'deleted' => 0, 'failures' => []];

        try {
            foreach ($tourQuery->cursor() as $tour) {
                if ($run->fresh()->status === 'cancelled') {
                    return;
                }

                try {
                    $result = $tourMigrator->migrate($tour);
                    foreach (['converted', 'skipped', 'deleted'] as $key) {
                        $details[$key] += $result[$key];
                    }
                    $run->increment('successful');
                } catch (Throwable $exception) {
                    $run->increment('failed');
                    if (count($details['failures']) < 25) {
                        $details['failures'][] = [
                            'tour_id' => $tour->id,
                            'entity' => 'tour',
                            'title' => $tour->title,
                            'error' => mb_substr($exception->getMessage(), 0, 500),
                        ];
                    }
                    report($exception);
                }
            }

            foreach ($advertisementQuery->cursor() as $advertisement) {
                if ($run->fresh()->status === 'cancelled') {
                    return;
                }

                try {
                    $result = $advertisementMigrator->migrate($advertisement);
                    foreach (['converted', 'skipped', 'deleted'] as $key) {
                        $details[$key] += $result[$key];
                    }
                    $run->increment('successful');
                } catch (Throwable $exception) {
                    $run->increment('failed');
                    if (count($details['failures']) < 25) {
                        $details['failures'][] = [
                            'advertisement_id' => $advertisement->id,
                            'entity' => 'advertisement',
                            'title' => $advertisement->name,
                            'error' => mb_substr($exception->getMessage(), 0, 500),
                        ];
                    }
                    report($exception);
                }
            }

            $unreferenced = $unreferencedMigrator->migrate();
            foreach (['converted', 'deleted'] as $key) {
                $details[$key] += $unreferenced[$key];
            }
            $details['unreferenced'] = $unreferenced;

            $run->refresh();
            if ($run->status === 'cancelled') {
                return;
            }
            $run->update([
                'status' => $run->failed > 0 ? 'partial' : 'success',
                'details' => ['webp' => $details],
                'error' => $run->failed > 0
                    ? "تبدیل تصاویر {$run->failed} پیشنهاد ناموفق بود؛ فایل‌های اصلی آن‌ها حفظ شدند."
                    : "{$details['converted']} تصویر پیشنهاد و تبلیغ به WebP تبدیل و {$details['deleted']} فایل قدیمی حذف شد.",
                'finished_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $run->refresh()->update([
                'status' => 'failed',
                'details' => ['webp' => $details],
                'error' => mb_substr($exception->getMessage(), 0, 1000),
                'finished_at' => now(),
            ]);

            throw $exception;
        }
        } finally {
            config(['seo.sitemap.auto_refresh' => $autoRefreshSitemap]);
            if ($autoRefreshSitemap) {
                try {
                    $sitemap->write();
                } catch (Throwable $exception) {
                    report($exception);
                }
            }
        }
    }

    public function failed(?Throwable $exception): void
    {
        SyncRun::query()
            ->whereKey($this->runId)
            ->whereNull('finished_at')
            ->update([
                'status' => 'failed',
                'error' => mb_substr($exception?->getMessage() ?? 'تبدیل تصاویر به WebP متوقف شد.', 0, 1000),
                'finished_at' => now(),
            ]);
    }
}
