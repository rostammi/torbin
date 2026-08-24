<?php

namespace App\Services;

use App\Jobs\SyncCenter\DiscoverHotelsJob;
use App\Jobs\SyncCenter\DiscoverStaysJob;
use App\Jobs\SyncCenter\DiscoverToursJob;
use App\Jobs\SyncCenter\DiscoverVisasJob;
use App\Jobs\SyncCenter\DownloadAllMissingImagesJob;
use App\Jobs\SyncCenter\DownloadMissingHotelImagesJob;
use App\Jobs\SyncCenter\DownloadMissingStayImagesJob;
use App\Jobs\SyncCenter\DownloadMissingTourImagesJob;
use App\Jobs\SyncCenter\DownloadMissingVisaImagesJob;
use App\Jobs\SyncCenter\RefreshContentJob;
use App\Jobs\SyncCenter\RefreshPricesJob;
use App\Jobs\SyncCenter\RunFullSyncJob;
use InvalidArgumentException;

class SyncCenterJobDispatcher
{
    private const JOBS = [
        'discover_tours' => DiscoverToursJob::class,
        'discover_hotels' => DiscoverHotelsJob::class,
        'discover_stays' => DiscoverStaysJob::class,
        'discover_visas' => DiscoverVisasJob::class,
        'prices' => RefreshPricesJob::class,
        'content' => RefreshContentJob::class,
        'images' => DownloadAllMissingImagesJob::class,
        'images_tours' => DownloadMissingTourImagesJob::class,
        'images_hotels' => DownloadMissingHotelImagesJob::class,
        'images_stays' => DownloadMissingStayImagesJob::class,
        'images_visas' => DownloadMissingVisaImagesJob::class,
        'all' => RunFullSyncJob::class,
    ];

    public function dispatch(string $type, int $runId, array $retryTargets = []): void
    {
        $job = self::JOBS[$type] ?? throw new InvalidArgumentException("Unknown sync-center action [{$type}].");

        if (str_starts_with($type, 'images')) {
            $job::dispatch($runId, data_get($retryTargets, 'images', []));

            return;
        }

        $job::dispatch($runId, $retryTargets);
    }
}
