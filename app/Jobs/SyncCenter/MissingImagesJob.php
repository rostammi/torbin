<?php

namespace App\Jobs\SyncCenter;

use App\Jobs\CrawlMissingTourImages;
use Illuminate\Container\Container;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

abstract class MissingImagesJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 86400;

    public int $tries = 1;

    public bool $failOnTimeout = true;

    public function __construct(public int $runId, public array $targetTourIds = [])
    {
        $this->onQueue('sync');
    }

    abstract protected function category(): ?string;

    public function handle(Container $container): void
    {
        $container->call([
            new CrawlMissingTourImages($this->runId, $this->category(), $this->targetTourIds),
            'handle',
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        (new CrawlMissingTourImages($this->runId, $this->category(), $this->targetTourIds))->failed($exception);
    }
}
