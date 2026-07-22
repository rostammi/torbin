<?php

namespace App\Jobs;

use App\Models\SyncRun;
use App\Models\TourSuggestion;
use App\Services\Discovery\TourProvisioner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProvisionSuggestedTour implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public int $tries = 2;

    public function __construct(public int $suggestionId, public int $runId) {}

    public function handle(TourProvisioner $provisioner): void
    {
        $run = SyncRun::findOrFail($this->runId);
        try {
            $result = $provisioner->provision(TourSuggestion::findOrFail($this->suggestionId));
            $run->update([
                'status' => 'success', 'successful' => 1, 'details' => [
                    'tour_id' => $result['tour']->id,
                    'action' => $result['created'] ? 'created' : 'updated',
                    'sources' => $result['sources'],
                    'crawled' => $result['crawled'],
                    'content_crawled' => $result['content_crawled'],
                ], 'finished_at' => now(),
            ]);
        } catch (Throwable $exception) {
            TourSuggestion::whereKey($this->suggestionId)->update(['status' => 'failed']);
            $run->update(['status' => 'failed', 'failed' => 1, 'error' => $exception->getMessage(), 'finished_at' => now()]);

            throw $exception;
        }
    }
}
