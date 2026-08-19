<?php

namespace App\Jobs;

use App\Models\PriceSource;
use App\Models\SyncRun;
use App\Models\Tour;
use App\Services\Discovery\ComparisonCatalogDiscovery;
use App\Services\Discovery\GeytReferencePageProvisioner;
use App\Services\Images\TourImageCrawler;
use App\Services\PriceCrawler;
use App\Services\TourPriceUpdater;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class RunAutomationSync implements ShouldQueue
{
    use Queueable;

    public int $timeout = 86400;

    public int $tries = 1;

    public bool $failOnTimeout = true;

    public function __construct(public int $runId) {}

    public function handle(
        PriceCrawler $crawler,
        TourPriceUpdater $priceUpdater,
        ComparisonCatalogDiscovery $discovery,
        GeytReferencePageProvisioner $referencePages,
        TourImageCrawler $images,
    ): void {
        $run = SyncRun::findOrFail($this->runId);
        try {
            $details = [];
            $total = $successful = 0;
            $discoveryCategories = [
                'discover_tours' => 'tour',
                'discover_hotels' => 'hotel',
                'discover_stays' => 'stay',
                'discover_visas' => 'visa',
            ];
            if ($run->type === 'all' || isset($discoveryCategories[$run->type])) {
                $result = $run->type === 'all'
                    ? $discovery->discover()
                    : $discovery->discoverCategory($discoveryCategories[$run->type]);
                $details['discovery'] = $result;
                $pageResult = $referencePages->provision(
                    $run->type === 'all' ? null : $discoveryCategories[$run->type],
                );
                $details['reference_pages'] = $pageResult;
                $total += $result['total'];
                $successful += $result['total'];
                $total += $pageResult['total'];
                $successful += $pageResult['total'];
            }
            if (in_array($run->type, ['prices', 'all'], true)) {
                $tours = Tour::query()->get();
                $priceSummary = [
                    'tours' => $tours->count(),
                    'checked' => 0,
                    'crawl_successful' => 0,
                    'failed_sources_retained' => 0,
                    'fallback_checked' => 0,
                    'with_minimum_prices' => 0,
                    'needs_new_crawler' => [],
                ];
                foreach ($tours as $tour) {
                    $result = $priceUpdater->update($tour);
                    $priceSummary['checked'] += $result['checked'];
                    $priceSummary['crawl_successful'] += $result['crawl_successful'];
                    $priceSummary['failed_sources_retained'] += $result['failed_sources_retained'];
                    $priceSummary['fallback_checked'] += $result['fallback_checked'];
                    $priceSummary['with_minimum_prices'] += (int) $result['target_met'];
                    if ($result['needs_new_crawler']) {
                        $priceSummary['needs_new_crawler'][] = [
                            'tour_id' => $tour->id,
                            'title' => $tour->title,
                            'prices_found' => $result['prices_found'],
                        ];
                    }
                }
                $details['prices'] = $priceSummary;
                $total += $priceSummary['checked'] + $tours->count();
                $successful += $priceSummary['crawl_successful'] + $priceSummary['with_minimum_prices'];
            }
            if (in_array($run->type, ['content', 'all'], true)) {
                $sources = PriceSource::where('is_active', true)->get();
                $ok = $sources->filter(fn ($source) => $crawler->crawlContent($source, true))->count();
                $details['content'] = ['total' => $sources->count(), 'successful' => $ok];
                $total += $sources->count();
                $successful += $ok;
            }
            if (in_array($run->type, ['images', 'all'], true)) {
                $tours = Tour::query()
                    ->where(fn ($query) => $query->whereNull('cover_image')->orWhere('cover_image', ''))
                    ->get();
                $imageSuccess = 0;
                $downloaded = 0;
                $failures = [];
                foreach ($tours as $tour) {
                    try {
                        $result = $images->crawl($tour);
                        $imageSuccess++;
                        $downloaded += $result['downloaded'];
                    } catch (Throwable $exception) {
                        if (count($failures) < 25) {
                            $failures[] = [
                                'tour_id' => $tour->id,
                                'title' => $tour->title,
                                'error' => mb_substr($exception->getMessage(), 0, 500),
                            ];
                        }
                        report($exception);
                    }
                }
                $details['images'] = [
                    'total' => $tours->count(),
                    'successful' => $imageSuccess,
                    'downloaded' => $downloaded,
                    'failures' => $failures,
                ];
                $total += $tours->count();
                $successful += $imageSuccess;
            }
            $run->update([
                'status' => $successful === $total ? 'success' : 'partial', 'total' => $total,
                'successful' => $successful, 'failed' => $total - $successful, 'details' => $details, 'finished_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $run->update(['status' => 'failed', 'error' => $exception->getMessage(), 'finished_at' => now()]);

            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        SyncRun::query()
            ->whereKey($this->runId)
            ->whereNull('finished_at')
            ->update([
                'status' => 'failed',
                'error' => mb_substr($exception?->getMessage() ?? 'اجرای همگام‌سازی متوقف شد.', 0, 1000),
                'finished_at' => now(),
            ]);
    }
}
