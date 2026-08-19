<?php

namespace App\Jobs;

use App\Models\SyncRun;
use App\Models\Tour;
use App\Services\Images\TourImageCrawler;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class CrawlMissingTourImages implements ShouldQueue
{
    use Queueable;

    public int $timeout = 86400;

    public int $tries = 1;

    public bool $failOnTimeout = true;

    public function __construct(
        public int $runId,
        public ?string $category = null,
        public array $targetTourIds = [],
    ) {}

    public function handle(TourImageCrawler $crawler): void
    {
        $run = SyncRun::findOrFail($this->runId);
        if ($run->status === 'cancelled') {
            return;
        }
        $query = Tour::query()
            ->where(fn ($query) => $query->whereNull('cover_image')->orWhere('cover_image', ''))
            ->when($this->category, fn ($query) => $query->where('category', $this->category))
            ->when($this->targetTourIds, fn ($query) => $query->whereKey($this->targetTourIds))
            ->orderBy('id');

        $run->update([
            'status' => 'running',
            'total' => (clone $query)->count(),
            'successful' => 0,
            'failed' => 0,
            'error' => null,
        ]);

        $details = ['downloaded' => 0, 'failures' => [], 'failed_tour_ids' => []];

        try {
            foreach ($query->cursor() as $tour) {
                if ($run->fresh()->status === 'cancelled') {
                    return;
                }
                try {
                    $result = $crawler->crawl($tour);
                    $details['downloaded'] += $result['downloaded'];
                    $run->increment('successful');
                } catch (Throwable $exception) {
                    $run->increment('failed');
                    $details['failed_tour_ids'][] = $tour->id;
                    if (count($details['failures']) < 25) {
                        $details['failures'][] = [
                            'tour_id' => $tour->id,
                            'title' => $tour->title,
                            'error' => mb_substr($exception->getMessage(), 0, 500),
                        ];
                    }
                    report($exception);
                }
            }

            $run->refresh();
            if ($run->status === 'cancelled') {
                return;
            }
            $run->update([
                'status' => $run->failed > 0 ? 'partial' : 'success',
                'details' => ['images' => $details],
                'error' => $run->failed > 0 ? "{$run->failed} صفحه مقایسه بدون تصویر باقی ماند." : null,
                'finished_at' => now(),
            ]);
        } catch (Throwable $exception) {
            if ($run->fresh()->status === 'cancelled') {
                return;
            }
            $run->refresh()->update([
                'status' => 'failed',
                'details' => ['images' => $details],
                'error' => mb_substr($exception->getMessage(), 0, 1000),
                'finished_at' => now(),
            ]);

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
                'error' => mb_substr($exception?->getMessage() ?? 'اجرای دریافت تصاویر متوقف شد.', 0, 1000),
                'finished_at' => now(),
            ]);
    }
}
