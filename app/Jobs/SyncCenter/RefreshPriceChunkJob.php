<?php

namespace App\Jobs\SyncCenter;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Compatibility bridge for chunks queued by the previous deployment. */
class RefreshPriceChunkJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 60;

    public int $tries = 2;

    public function __construct(
        public int $runId,
        public array $tourIds = [],
        public int $offset = 0,
    ) {
        $this->onQueue('sync');
    }

    public function handle(): void
    {
        RefreshPricesJob::dispatch($this->runId);
    }
}
