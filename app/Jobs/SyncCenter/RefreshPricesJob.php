<?php

namespace App\Jobs\SyncCenter;

use App\Models\SyncRun;
use App\Models\Tour;
use App\Services\TourPriceUpdater;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class RefreshPricesJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout;

    public int $tries = 2;

    public int $uniqueFor;

    public function __construct(public int $runId, public array $retryTargets = [])
    {
        $this->onQueue('sync');
        $this->timeout = (int) config('crawler.sync_job_timeout', 1800);
        $this->uniqueFor = $this->timeout + 300;
    }

    public function uniqueId(): string
    {
        return 'price-refresh:'.$this->runId;
    }

    public function handle(TourPriceUpdater $updater): void
    {
        $run = SyncRun::findOrFail($this->runId);
        if ($run->status === 'cancelled') {
            return;
        }

        $details = $run->details ?? [];
        $targetIds = $this->retryTargets['prices'] ?? data_get($details, 'price_target_ids', []);
        $cycleStartedAt = Carbon::parse(
            data_get(
                $details,
                'price_cycle_started_at',
                $targetIds !== []
                    ? ($run->started_at ?? now())
                    : ($run->started_at?->copy()->startOfDay() ?? now()->startOfDay()),
            ),
        );

        if (! isset($details['prices'])) {
            $total = $this->dueTours($cycleStartedAt, $targetIds)->count();
            $details['price_cycle_started_at'] = $cycleStartedAt->toIso8601String();
            $details['price_target_ids'] = $targetIds;
            $details['prices'] = $this->emptySummary($total);
            $run->update([
                'status' => 'running',
                'total' => $total,
                'successful' => 0,
                'failed' => 0,
                'details' => $details,
                'error' => null,
                'finished_at' => null,
            ]);
        }

        $tours = $this->dueTours($cycleStartedAt, $targetIds)
            ->limit(max(1, (int) config('crawler.price_sync_pages_per_job', 2)))
            ->get();

        foreach ($tours as $tour) {
            if (SyncRun::whereKey($this->runId)->value('status') === 'cancelled') {
                return;
            }

            try {
                $result = $updater->update($tour, $cycleStartedAt);
                $tour->update(['prices_checked_at' => now()]);
                $this->recordResult($tour, $result);
            } catch (Throwable $exception) {
                // A bad provider or page must not permanently stall the daily cycle.
                $tour->update(['prices_checked_at' => now()]);
                $this->recordFailure($tour, $exception);
                report($exception);
            }
        }

        if (! $this->dueTours($cycleStartedAt, $targetIds)->exists()) {
            $this->finish();
        }
    }

    private function dueTours(Carbon $cycleStartedAt, array $targetIds)
    {
        return Tour::query()
            ->when($targetIds, fn ($query) => $query->whereKey($targetIds))
            ->where(fn ($query) => $query
                ->whereNull('prices_checked_at')
                ->orWhere('prices_checked_at', '<', $cycleStartedAt))
            ->orderByRaw('prices_checked_at IS NULL DESC')
            ->orderBy('prices_checked_at')
            ->orderBy('id');
    }

    private function emptySummary(int $total): array
    {
        return [
            'tours' => $total,
            'checked' => 0,
            'crawl_successful' => 0,
            'failed_sources_retained' => 0,
            'fallback_checked' => 0,
            'with_minimum_prices' => 0,
            'needs_new_crawler' => [],
            'failed_tour_ids' => [],
            'failures' => [],
        ];
    }

    private function recordResult(Tour $tour, array $result): void
    {
        DB::transaction(function () use ($tour, $result) {
            $run = SyncRun::query()->lockForUpdate()->findOrFail($this->runId);
            if ($run->status === 'cancelled') {
                return;
            }

            $details = $run->details ?? [];
            $prices = data_get($details, 'prices', []);
            foreach (['checked', 'crawl_successful', 'failed_sources_retained', 'fallback_checked'] as $key) {
                $prices[$key] = (int) ($prices[$key] ?? 0) + (int) $result[$key];
            }
            $prices['with_minimum_prices'] = (int) ($prices['with_minimum_prices'] ?? 0)
                + (int) $result['target_met'];
            if ($result['needs_new_crawler']) {
                $prices['needs_new_crawler'][] = [
                    'tour_id' => $tour->id,
                    'title' => $tour->title,
                    'prices_found' => $result['prices_found'],
                ];
                $prices['failed_tour_ids'][] = $tour->id;
            }

            $details['prices'] = $prices;
            $run->successful++;
            $run->details = $details;
            $run->save();
        });
    }

    private function recordFailure(Tour $tour, Throwable $exception): void
    {
        DB::transaction(function () use ($tour, $exception) {
            $run = SyncRun::query()->lockForUpdate()->findOrFail($this->runId);
            if ($run->status === 'cancelled') {
                return;
            }

            $details = $run->details ?? [];
            $prices = data_get($details, 'prices', []);
            $prices['failed_tour_ids'][] = $tour->id;
            if (count($prices['failures'] ?? []) < 25) {
                $prices['failures'][] = [
                    'tour_id' => $tour->id,
                    'title' => $tour->title,
                    'error' => mb_substr($exception->getMessage(), 0, 500),
                ];
            }
            $details['prices'] = $prices;
            $run->failed++;
            $run->details = $details;
            $run->save();
        });
    }

    private function finish(): void
    {
        $run = SyncRun::findOrFail($this->runId);
        if ($run->status === 'cancelled') {
            return;
        }

        $missingPrices = count(data_get($run->details, 'prices.failed_tour_ids', []));
        $run->update([
            'status' => $run->failed > 0 || $missingPrices > 0 ? 'partial' : 'success',
            'error' => $run->failed > 0
                ? "پردازش {$run->failed} صفحه با خطا تمام شد."
                : ($missingPrices > 0 ? "برای {$missingPrices} صفحه، حداقل سه قیمت معتبر پیدا نشد." : null),
            'finished_at' => now(),
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        SyncRun::query()
            ->whereKey($this->runId)
            ->whereNull('finished_at')
            ->update([
                'error' => mb_substr($exception?->getMessage() ?? 'اجرای این بخش متوقف شد و در تیک بعدی دوباره تلاش می‌شود.', 0, 1000),
            ]);
    }
}
