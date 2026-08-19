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

    public function __construct(
        public int $runId,
        public ?string $category = null,
        public string $sourcePattern = '%_catalog',
        public bool $pendingOnly = false,
        public bool $referenceOnly = false,
        public array $targetSuggestionIds = [],
    ) {}

    public function handle(TourProvisioner $provisioner): void
    {
        $run = SyncRun::findOrFail($this->runId);
        if ($run->status === 'cancelled') {
            return;
        }
        $query = TourSuggestion::query()
            ->where('source', 'like', $this->sourcePattern)
            ->when($this->referenceOnly, fn ($query) => $query->whereNotNull('metadata->geyt_references'))
            ->when($this->pendingOnly, fn ($query) => $query->whereIn('status', ['pending', 'failed']))
            ->when($this->category, fn ($query) => $query->where('category', $this->category))
            ->when($this->targetSuggestionIds, fn ($query) => $query->whereKey($this->targetSuggestionIds))
            ->orderBy('id');

        $run->update([
            'status' => 'running',
            'total' => (clone $query)->count(),
            'successful' => 0,
            'failed' => 0,
            'error' => null,
        ]);

        $summary = [
            'category' => $this->category,
            'created' => 0,
            'updated' => 0,
            'sources' => 0,
            'prices_crawled' => 0,
            'prices_found' => 0,
            'fallback_checked' => 0,
            'failed_sources_retained' => 0,
            'contents_crawled' => 0,
            'images_downloaded' => 0,
            'failures' => [],
            'failed_suggestion_ids' => [],
        ];

        try {
            foreach ($query->cursor() as $suggestion) {
                if ($run->fresh()->status === 'cancelled') {
                    return;
                }
                try {
                    $result = $provisioner->provision($suggestion);
                    $summary[$result['created'] ? 'created' : 'updated']++;
                    $summary['sources'] += $result['sources'];
                    $summary['prices_crawled'] += $result['crawled'];
                    $summary['prices_found'] += $result['prices_found'];
                    $summary['fallback_checked'] += $result['fallback_checked'];
                    $summary['failed_sources_retained'] += $result['failed_sources_retained'];
                    $summary['contents_crawled'] += $result['content_crawled'];
                    $summary['images_downloaded'] += $result['images_downloaded'];
                    $run->increment('successful');
                } catch (Throwable $exception) {
                    $suggestion->update(['status' => 'failed']);
                    $run->increment('failed');
                    $summary['failed_suggestion_ids'][] = $suggestion->id;

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
            if ($run->status === 'cancelled') {
                return;
            }
            $run->update([
                'status' => $run->failed > 0 ? 'partial' : 'success',
                'details' => $summary,
                'error' => $run->failed > 0 ? "{$run->failed} پیشنهاد ناموفق بود." : null,
                'finished_at' => now(),
            ]);
        } catch (Throwable $exception) {
            if ($run->fresh()->status === 'cancelled') {
                return;
            }
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
