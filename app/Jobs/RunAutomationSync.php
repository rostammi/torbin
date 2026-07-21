<?php

namespace App\Jobs;

use App\Models\PriceSource;
use App\Models\SyncRun;
use App\Services\Discovery\PopularTourDiscovery;
use App\Services\PriceCrawler;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class RunAutomationSync implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;

    public int $tries = 1;

    public function __construct(public int $runId) {}

    public function handle(PriceCrawler $crawler, PopularTourDiscovery $discovery): void
    {
        $run = SyncRun::findOrFail($this->runId);
        try {
            $details = [];
            $total = $successful = 0;
            if (in_array($run->type, ['discover_tours', 'all'], true)) {
                $result = $discovery->discover();
                $details['discovery'] = $result;
                $total += $result['total'];
                $successful += $result['total'];
            }
            if (in_array($run->type, ['prices', 'all'], true)) {
                $sources = PriceSource::where('is_active', true)->where('extraction_type', '!=', 'manual')->get();
                $ok = $sources->filter(fn ($source) => $crawler->crawl($source))->count();
                $details['prices'] = ['total' => $sources->count(), 'successful' => $ok];
                $total += $sources->count();
                $successful += $ok;
            }
            if (in_array($run->type, ['content', 'all'], true)) {
                $sources = PriceSource::where('is_active', true)->get();
                $ok = $sources->filter(fn ($source) => $crawler->crawlContent($source, true))->count();
                $details['content'] = ['total' => $sources->count(), 'successful' => $ok];
                $total += $sources->count();
                $successful += $ok;
            }
            $run->update([
                'status' => $successful === $total ? 'success' : 'partial', 'total' => $total,
                'successful' => $successful, 'failed' => $total - $successful, 'details' => $details, 'finished_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $run->update(['status' => 'failed', 'error' => $exception->getMessage(), 'finished_at' => now()]);

            throw $exception;
        }
    }
}
