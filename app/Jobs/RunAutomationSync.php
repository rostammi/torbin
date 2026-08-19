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

    public function __construct(public int $runId, public array $retryTargets = []) {}

    public function handle(
        PriceCrawler $crawler,
        TourPriceUpdater $priceUpdater,
        ComparisonCatalogDiscovery $discovery,
        GeytReferencePageProvisioner $referencePages,
        TourImageCrawler $images,
    ): void {
        $run = SyncRun::findOrFail($this->runId);
        if ($run->status === 'cancelled') {
            return;
        }

        try {
            $details = [];
            $total = $successful = 0;
            $retryingFailures = $this->retryTargets !== [];
            $discoveryCategories = [
                'discover_tours' => 'tour',
                'discover_hotels' => 'hotel',
                'discover_stays' => 'stay',
                'discover_visas' => 'visa',
            ];
            if (($run->type === 'all' && ! $retryingFailures) || isset($discoveryCategories[$run->type])) {
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
            if (in_array($run->type, ['prices', 'all'], true)
                && (! $retryingFailures || ($this->retryTargets['prices'] ?? []) !== [])) {
                $tours = Tour::query()
                    ->when($this->retryTargets['prices'] ?? [], fn ($query, $ids) => $query->whereKey($ids))
                    ->get();
                $priceSummary = [
                    'tours' => $tours->count(),
                    'checked' => 0,
                    'crawl_successful' => 0,
                    'failed_sources_retained' => 0,
                    'fallback_checked' => 0,
                    'with_minimum_prices' => 0,
                    'needs_new_crawler' => [],
                    'failed_tour_ids' => [],
                ];
                foreach ($tours as $tour) {
                    if ($run->fresh()->status === 'cancelled') {
                        return;
                    }
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
                    if (! $result['target_met']) {
                        $priceSummary['failed_tour_ids'][] = $tour->id;
                    }
                }
                $details['prices'] = $priceSummary;
                $total += $priceSummary['checked'] + $tours->count();
                $successful += $priceSummary['crawl_successful'] + $priceSummary['with_minimum_prices'];
            }
            if (in_array($run->type, ['content', 'all'], true)
                && (! $retryingFailures || ($this->retryTargets['content'] ?? []) !== [])) {
                $sources = PriceSource::where('is_active', true)
                    ->when($this->retryTargets['content'] ?? [], fn ($query, $ids) => $query->whereKey($ids))
                    ->get();
                $failedSourceIds = [];
                $ok = 0;
                foreach ($sources as $source) {
                    if ($run->fresh()->status === 'cancelled') {
                        return;
                    }
                    if ($crawler->crawlContent($source, true)) {
                        $ok++;
                    } else {
                        $failedSourceIds[] = $source->id;
                    }
                }
                $details['content'] = ['total' => $sources->count(), 'successful' => $ok, 'failed_source_ids' => $failedSourceIds];
                $total += $sources->count();
                $successful += $ok;
            }
            if (in_array($run->type, ['images', 'all'], true)
                && (! $retryingFailures || ($this->retryTargets['images'] ?? []) !== [])) {
                $tours = Tour::query()
                    ->where(fn ($query) => $query->whereNull('cover_image')->orWhere('cover_image', ''))
                    ->when($this->retryTargets['images'] ?? [], fn ($query, $ids) => $query->whereKey($ids))
                    ->get();
                $imageSuccess = 0;
                $downloaded = 0;
                $failures = [];
                $failedTourIds = [];
                foreach ($tours as $tour) {
                    if ($run->fresh()->status === 'cancelled') {
                        return;
                    }
                    try {
                        $result = $images->crawl($tour);
                        $imageSuccess++;
                        $downloaded += $result['downloaded'];
                    } catch (Throwable $exception) {
                        $failedTourIds[] = $tour->id;
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
                    'failed_tour_ids' => $failedTourIds,
                ];
                $total += $tours->count();
                $successful += $imageSuccess;
            }
            if ($run->fresh()->status === 'cancelled') {
                return;
            }
            $run->update([
                'status' => $successful === $total ? 'success' : 'partial', 'total' => $total,
                'successful' => $successful, 'failed' => $total - $successful, 'details' => $details, 'finished_at' => now(),
            ]);
        } catch (Throwable $exception) {
            if ($run->fresh()->status === 'cancelled') {
                return;
            }
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
