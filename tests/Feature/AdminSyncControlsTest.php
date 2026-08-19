<?php

namespace Tests\Feature;

use App\Jobs\CrawlMissingTourImages;
use App\Models\SyncRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AdminSyncControlsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_cancel_a_running_sync(): void
    {
        $run = SyncRun::create([
            'type' => 'images_hotels',
            'status' => 'running',
            'started_at' => now(),
        ]);
        $admin = User::factory()->create();

        $this->actingAs($admin)
            ->get(route('admin.sync.index'))
            ->assertOk()
            ->assertSee(route('admin.sync.cancel', $run))
            ->assertSee('لغو');

        $this->actingAs($admin)
            ->post(route('admin.sync.cancel', $run))
            ->assertRedirect()
            ->assertSessionHas('success');

        $run->refresh();
        $this->assertSame('cancelled', $run->status);
        $this->assertNotNull($run->finished_at);
        $this->assertSame('عملیات توسط مدیر لغو شد.', $run->error);
    }

    public function test_admin_can_retry_a_failed_sync(): void
    {
        Queue::fake();
        $run = SyncRun::create([
            'type' => 'images_hotels',
            'status' => 'failed',
            'failed' => 1,
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
        ]);
        $admin = User::factory()->create();

        $this->actingAs($admin)
            ->get(route('admin.sync.index'))
            ->assertOk()
            ->assertSee('تلاش مجدد');

        $this->actingAs($admin)
            ->post(route('admin.sync.retry', $run))
            ->assertRedirect()
            ->assertSessionHas('success');

        $retry = SyncRun::query()->whereKeyNot($run->id)->sole();
        $this->assertSame($run->id, $retry->details['retry_of']);
        $this->assertFalse($retry->details['failed_only']);
        Queue::assertPushed(CrawlMissingTourImages::class, fn (CrawlMissingTourImages $job) => $job->runId === $retry->id
            && $job->category === 'hotel'
            && $job->targetTourIds === []);
    }

    public function test_partial_image_sync_retries_only_failed_items(): void
    {
        Queue::fake();
        $run = SyncRun::create([
            'type' => 'images_tours',
            'status' => 'partial',
            'total' => 5,
            'successful' => 3,
            'failed' => 2,
            'details' => ['images' => ['failed_tour_ids' => [17, 29]]],
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
        ]);
        $admin = User::factory()->create();

        $this->actingAs($admin)
            ->get(route('admin.sync.index'))
            ->assertOk()
            ->assertSee('تلاش برای موارد ناموفق');

        $this->actingAs($admin)
            ->post(route('admin.sync.retry', $run))
            ->assertRedirect()
            ->assertSessionHas('success');

        $retry = SyncRun::query()->whereKeyNot($run->id)->sole();
        $this->assertSame(2, $retry->total);
        $this->assertTrue($retry->details['failed_only']);
        Queue::assertPushed(CrawlMissingTourImages::class, fn (CrawlMissingTourImages $job) => $job->runId === $retry->id
            && $job->category === 'tour'
            && $job->targetTourIds === [17, 29]);
    }
}
