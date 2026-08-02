<?php

namespace App\Jobs;

use App\Models\SyncRun;
use App\Models\TourSuggestion;
use App\Services\Discovery\TourProvisioner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProvisionReferenceSuggestion implements ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    public int $tries = 1;

    public function __construct(public int $runId, public int $suggestionId) {}

    public function handle(TourProvisioner $provisioner): void
    {
        try {
            $provisioner->provision(TourSuggestion::findOrFail($this->suggestionId));
            SyncRun::whereKey($this->runId)->increment('successful');
        } catch (Throwable $exception) {
            TourSuggestion::whereKey($this->suggestionId)->update(['status' => 'failed']);
            SyncRun::whereKey($this->runId)->increment('failed');
            report($exception);
        } finally {
            $this->finishRunWhenComplete();
        }
    }

    private function finishRunWhenComplete(): void
    {
        DB::transaction(function () {
            $run = SyncRun::query()->lockForUpdate()->find($this->runId);
            if (! $run || $run->finished_at || ($run->successful + $run->failed) < $run->total) {
                return;
            }

            $run->update([
                'status' => $run->failed > 0 ? 'partial' : 'success',
                'error' => $run->failed > 0 ? "{$run->failed} صفحه نیازمند بررسی دوباره است." : null,
                'finished_at' => now(),
            ]);
        });
    }
}
