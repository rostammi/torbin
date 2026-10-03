<?php

namespace App\Jobs\SyncCenter;

use App\Jobs\RunAutomationSync;
use Illuminate\Container\Container;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

abstract class AutomationSyncJob implements ShouldQueue
{
    use Queueable;

    public int $timeout;

    public int $tries = 1;

    public bool $failOnTimeout = true;

    public function __construct(public int $runId, public array $retryTargets = [])
    {
        $this->timeout = (int) config('crawler.sync_job_timeout', 1800);
        $this->onQueue('sync');
    }

    public function handle(Container $container): void
    {
        $container->call([new RunAutomationSync($this->runId, $this->retryTargets), 'handle']);
    }

    public function failed(?Throwable $exception): void
    {
        (new RunAutomationSync($this->runId, $this->retryTargets))->failed($exception);
    }
}
