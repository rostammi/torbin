<?php

namespace App\Jobs;

use App\Models\ComparisonSource;
use App\Models\SyncRun;
use App\Services\Discovery\ComparisonSourceScanner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ScanComparisonSource implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public int $tries = 1;

    public function __construct(public int $sourceId, public int $runId) {}

    public function handle(ComparisonSourceScanner $scanner): void
    {
        $run = SyncRun::findOrFail($this->runId);
        if ($run->status === 'cancelled') {
            return;
        }
        $source = ComparisonSource::findOrFail($this->sourceId);
        $run->update(['status' => 'running', 'started_at' => $run->started_at ?: now()]);

        try {
            $summary = $scanner->scan($source);
            if ($run->fresh()->status === 'cancelled') {
                return;
            }
            $run->update([
                'status' => 'success',
                'successful' => 1,
                'details' => ['source_id' => $source->id, 'summary' => $summary],
                'finished_at' => now(),
            ]);
        } catch (Throwable $exception) {
            if ($run->fresh()->status === 'cancelled') {
                return;
            }
            $source->update([
                'last_status' => 'failed',
                'last_error' => mb_substr($exception->getMessage(), 0, 2000),
                'last_scanned_at' => now(),
            ]);
            $run->update([
                'status' => 'failed',
                'failed' => 1,
                'error' => mb_substr($exception->getMessage(), 0, 2000),
                'finished_at' => now(),
            ]);

            throw $exception;
        }
    }
}
