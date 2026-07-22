<?php

namespace App\Jobs;

use App\Models\SyncRun;
use App\Models\TourSuggestion;
use App\Services\Discovery\TourProvisioner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProvisionAllSuggestedTours implements ShouldQueue
{
    use Queueable;

    public int $timeout = 86400;

    public int $tries = 1;

    public bool $failOnTimeout = true;

    public function __construct(public int $runId) {}

    public function handle(TourProvisioner $provisioner): void
    {
        $run = SyncRun::findOrFail($this->runId);
        $query = TourSuggestion::query()
            ->where('source', 'destination_catalog')
            ->orderBy('id');

        $run->update([
            'status' => 'running',
            'total' => (clone $query)->count(),
            'successful' => 0,
            'failed' => 0,
            'error' => null,
        ]);

        $summary = [
            'created' => 0,
            'updated' => 0,
            'sources' => 0,
            'prices_crawled' => 0,
            'contents_crawled' => 0,
            'failures' => [],
        ];

        try {
            foreach ($query->cursor() as $suggestion) {
                try {
                    $result = $provisioner->provision($suggestion);
                    $summary[$result['created'] ? 'created' : 'updated']++;
                    $summary['sources'] += $result['sources'];
                    $summary['prices_crawled'] += $result['crawled'];
                    $summary['contents_crawled'] += $result['content_crawled'];
                    $run->increment('successful');
                } catch (Throwable $exception) {
                    $suggestion->update(['status' => 'failed']);
                    $run->increment('failed');

                    if (count($summary['failures']) < 25) {
                        $summary['failures'][] = [
                            'suggestion_id' => $suggestion->id,
                            'keyword' => $suggestion->keyword,
                            'error' => mb_substr($exception->getMessage(), 0, 500),
                        ];
                    }

                    report($exception);
                }
            }

            $run->refresh();
            $run->update([
                'status' => $run->failed > 0 ? 'partial' : 'success',
                'details' => $summary,
                'error' => $run->failed > 0 ? "{$run->failed} پیشنهاد ناموفق بود." : null,
                'finished_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $run->refresh()->update([
                'status' => 'failed',
                'details' => $summary,
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
                'error' => mb_substr($exception?->getMessage() ?? 'اجرای جاب متوقف شد.', 0, 1000),
                'finished_at' => now(),
            ]);
    }
}
