<?php

namespace App\Services;

use App\Models\SyncRun;
use Illuminate\Support\Facades\Cache;
use Throwable;

class ScheduledSyncDispatcher
{
    public function __construct(private readonly SyncCenterJobDispatcher $dispatcher) {}

    public function dispatchDailyPriceRefreshIfDue(): ?SyncRun
    {
        $now = now();
        $scheduledAt = $now->copy()->startOfDay()->setTimeFromTimeString(
            (string) config('crawler.daily_price_refresh_at', '03:00'),
        );
        if ($now->lt($scheduledAt)) {
            return null;
        }

        $date = $now->toDateString();

        return Cache::lock("sync:daily-prices:{$date}", 60)->block(5, function () use ($date) {
            $existing = SyncRun::query()
                ->where('type', 'prices')
                ->where('details->scheduled_date', $date)
                ->first();
            if ($existing) {
                return $existing;
            }

            $run = SyncRun::create([
                'type' => 'prices',
                'details' => [
                    'trigger' => 'daily_cron',
                    'scheduled_date' => $date,
                ],
                'started_at' => now(),
            ]);

            try {
                $this->dispatcher->dispatch('prices', $run->id);
            } catch (Throwable $exception) {
                $run->delete();

                throw $exception;
            }

            return $run;
        });
    }
}
