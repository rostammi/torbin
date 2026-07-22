<?php

namespace App\Jobs;

use App\Models\PriceSource;
use App\Models\SyncRun;
use App\Models\Tour;
use App\Services\Discovery\PopularTourDiscovery;
use App\Services\Images\TourImageCrawler;
use App\Services\PriceCrawler;
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

    public function handle(PriceCrawler $crawler, PopularTourDiscovery $discovery, TourImageCrawler $images): void
    {
        $run = SyncRun::findOrFail($this->runId);
        try {
            $details = [];
            $total = $successful = 0;
            if (in_array($run->type, ['discover_tours', 'all'], true)) {
                $result = $discovery->discover();
                $details['discovery'] = $result;
                $total += $result['total'];
                $successful += $result['total'];
            }
            if (in_array($run->type, ['prices', 'all'], true)) {
                $sources = PriceSource::where('is_active', true)->where('extraction_type', '!=', 'manual')->get();
                $ok = $sources->filter(fn ($source) => $crawler->crawl($source))->count();
                $details['prices'] = ['total' => $sources->count(), 'successful' => $ok];
                $total += $sources->count();
                $successful += $ok;
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
