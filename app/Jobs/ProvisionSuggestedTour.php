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
        if ($run->status === 'cancelled') {
            return;
        }
        try {
            $result = $provisioner->provision(TourSuggestion::findOrFail($this->suggestionId));
            if ($run->fresh()->status === 'cancelled') {
                return;
            }
            $run->update([
                'status' => 'success', 'successful' => 1, 'details' => [
                    'suggestion_id' => $this->suggestionId,
                    'tour_id' => $result['tour']->id,
                    'action' => $result['created'] ? 'created' : 'updated',
                    'sources' => $result['sources'],
                    'crawled' => $result['crawled'],
                    'content_crawled' => $result['content_crawled'],
                    'prices_found' => $result['prices_found'],
                    'fallback_checked' => $result['fallback_checked'],
                    'failed_sources_retained' => $result['failed_sources_retained'],
                    'images_downloaded' => $result['images_downloaded'],
                ], 'finished_at' => now(),
            ]);
        } catch (Throwable $exception) {
            if ($run->fresh()->status === 'cancelled') {
                return;
            }
            TourSuggestion::whereKey($this->suggestionId)->update(['status' => 'failed']);
            $run->update(['status' => 'failed', 'failed' => 1, 'error' => $exception->getMessage(), 'finished_at' => now()]);

            throw $exception;
        }
    }
}
