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

    public function __construct(public int $runId) {}

    public function handle(TourImageCrawler $crawler): void
    {
        $run = SyncRun::findOrFail($this->runId);
        $query = Tour::query()
            ->where(fn ($query) => $query->whereNull('cover_image')->orWhere('cover_image', ''))
            ->orderBy('id');

        $run->update([
            'status' => 'running',
            'total' => (clone $query)->count(),
            'successful' => 0,
            'failed' => 0,
            'error' => null,
        ]);

        $details = ['downloaded' => 0, 'failures' => []];

        try {
            foreach ($query->cursor() as $tour) {
                try {
                    $result = $crawler->crawl($tour);
                    $details['downloaded'] += $result['downloaded'];
                    $run->increment('successful');
                } catch (Throwable $exception) {
                    $run->increment('failed');
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
            $run->update([
                'status' => $run->failed > 0 ? 'partial' : 'success',
                'details' => ['images' => $details],
                'error' => $run->failed > 0 ? "{$run->failed} تور بدون تصویر باقی ماند." : null,
                'finished_at' => now(),
            ]);
        } catch (Throwable $exception) {
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
