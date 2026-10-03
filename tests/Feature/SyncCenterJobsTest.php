<?php

namespace Tests\Feature;

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
use App\Models\SyncRun;
use App\Models\Tour;
use App\Models\User;
use App\Services\ScheduledSyncDispatcher;
use App\Services\TourPriceUpdater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class SyncCenterJobsTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_sync_center_action_dispatches_its_own_job_to_the_sync_queue(): void
    {
        $admin = User::factory()->create();
        $this->actingAs($admin);

        foreach ([
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
        ] as $type => $jobClass) {
            Queue::fake();
            SyncRun::query()->delete();

            $this->post(route('admin.sync.run'), ['type' => $type])
                ->assertRedirect()
                ->assertSessionHas('success');

            $run = SyncRun::where('type', $type)->sole();
            Queue::assertPushed($jobClass, fn ($job) => $job->runId === $run->id && $job->queue === 'sync');
        }
    }

    public function test_shared_hosting_queue_worker_command_is_registered(): void
    {
        $this->assertArrayHasKey('sync:work', Artisan::all());
    }

    public function test_price_refresh_processes_only_one_small_page_per_invocation(): void
    {
        Queue::fake();
        config()->set('crawler.price_sync_pages_per_job', 1);
        foreach (range(1, 3) as $number) {
            Tour::create([
                'title' => "تور آزمایشی {$number}",
                'slug' => "chunked-price-tour-{$number}",
                'description' => 'توضیحات',
                'is_active' => true,
            ]);
        }
        $run = SyncRun::create(['type' => 'prices', 'started_at' => now()]);

        $updater = Mockery::mock(TourPriceUpdater::class);
        $updater->shouldReceive('update')->once()->andReturn([
            'checked' => 3,
            'crawl_successful' => 3,
            'failed_sources_retained' => 0,
            'fallback_checked' => 0,
            'target_met' => true,
            'needs_new_crawler' => false,
            'prices_found' => 3,
        ]);

        (new RefreshPricesJob($run->id))->handle($updater);

        $run->refresh();
        $this->assertSame(3, $run->total);
        $this->assertSame(1, $run->successful);
        $this->assertSame(0, $run->failed);
        $this->assertSame('running', $run->status);
        $this->assertSame(1, Tour::whereNotNull('prices_checked_at')->count());
        Queue::assertNothingPushed();
    }

    public function test_daily_price_refresh_is_queued_only_once_after_the_configured_time(): void
    {
        Carbon::setTestNow('2026-08-24 03:05:00');
        config()->set('crawler.daily_price_refresh_at', '03:00');
        Cache::clear();
        Queue::fake();

        $dispatcher = app(ScheduledSyncDispatcher::class);
        $first = $dispatcher->dispatchDailyPriceRefreshIfDue();
        $second = $dispatcher->dispatchDailyPriceRefreshIfDue();

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, SyncRun::where('type', 'prices')->count());
        $this->assertSame('daily_cron', $first->details['trigger']);
        Queue::assertPushed(RefreshPricesJob::class, 1);

        Carbon::setTestNow();
    }

    public function test_daily_price_refresh_is_not_queued_before_its_time(): void
    {
        Carbon::setTestNow('2026-08-24 02:59:00');
        config()->set('crawler.daily_price_refresh_at', '03:00');
        Cache::clear();
        Queue::fake();

        $this->assertNull(app(ScheduledSyncDispatcher::class)->dispatchDailyPriceRefreshIfDue());
        $this->assertDatabaseCount('sync_runs', 0);
        Queue::assertNothingPushed();

        Carbon::setTestNow();
    }
}
