<?php

namespace App\Jobs;

use App\Models\SyncRun;
use App\Models\Tour;
use App\Services\Images\TourImageCrawler;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class RefreshTourImages implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    public int $tries = 1;

    public int $uniqueFor = 1800;

    public bool $failOnTimeout = true;

    public function __construct(public int $tourId, public int $runId) {}

    public function uniqueId(): string
    {
        return 'tour-images-'.$this->tourId;
    }

    public function handle(TourImageCrawler $crawler): void
    {
        $run = SyncRun::findOrFail($this->runId);

        try {
            $result = $crawler->crawl(Tour::findOrFail($this->tourId), true);
            $run->update([
                'status' => 'success',
                'total' => 1,
                'successful' => 1,
                'details' => [
                    'tour_id' => $this->tourId,
                    'downloaded' => $result['downloaded'],
                    'replaced' => true,
                ],
                'finished_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $run->update([
                'status' => 'failed',
                'total' => 1,
                'failed' => 1,
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
                'total' => 1,
                'failed' => 1,
                'error' => mb_substr($exception?->getMessage() ?? 'تعویض تصاویر تور متوقف شد.', 0, 1000),
                'finished_at' => now(),
            ]);
    }
}
