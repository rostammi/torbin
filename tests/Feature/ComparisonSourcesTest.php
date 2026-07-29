<?php

namespace Tests\Feature;

use App\Jobs\ScanComparisonSource;
use App\Models\ComparisonSource;
use App\Models\SyncRun;
use App\Models\TourSuggestion;
use App\Models\User;
use App\Services\Discovery\ComparisonSourceScanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class ComparisonSourcesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_register_one_source_for_multiple_categories_and_queue_its_scan(): void
    {
        Queue::fake();
        $admin = User::factory()->create();

        $this->actingAs($admin)->post(route('admin.comparison-sources.store'), [
            'name' => 'منبع نمونه',
            'homepage_url' => 'https://93.184.216.34/',
            'categories' => ['tour', 'hotel', 'visa'],
            'is_active' => '1',
        ])->assertRedirect(route('admin.comparison-sources.index'));

        $source = ComparisonSource::sole();
        $this->assertSame('https://93.184.216.34', $source->homepage_url);
        $this->assertSame(['tour', 'hotel', 'visa'], $source->categories);

        $automaticRun = SyncRun::where('type', 'scan_comparison_source')->sole();
        $this->assertSame($source->id, data_get($automaticRun->details, 'source_id'));
        Queue::assertPushed(
            ScanComparisonSource::class,
            fn (ScanComparisonSource $job) => $job->sourceId === $source->id && $job->runId === $automaticRun->id
        );
        $automaticRun->update(['status' => 'success', 'finished_at' => now()]);

        $this->actingAs($admin)
            ->post(route('admin.comparison-sources.scan', $source))
            ->assertSessionHas('success');

        $run = SyncRun::where('type', 'scan_comparison_source')->latest('id')->firstOrFail();
        $this->assertSame($source->id, data_get($run->details, 'source_id'));
        Queue::assertPushed(ScanComparisonSource::class, 2);
    }

    public function test_scanner_discovers_comparable_items_in_all_selected_categories_without_duplicates(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://93.184.216.34' => Http::response(<<<'HTML'
                <!doctype html><html lang="fa"><body>
                <a href="/tours/kish">تور کیش از تهران از ۱۰,۰۰۰,۰۰۰ تومان</a>
                <a href="/hotels/mashhad">رزرو هتل مشهد</a>
                <a href="/stays/masal">اقامتگاه بوم گردی ماسال</a>
                <a href="/visa/dubai">ویزای دبی فوری</a>
                <a href="/blog/news">اخبار سفر</a>
                </body></html>
                HTML, 200, ['Content-Type' => 'text/html; charset=utf-8']),
        ]);
        $source = ComparisonSource::create([
            'name' => 'بازار سفر',
            'homepage_url' => 'https://93.184.216.34',
            'homepage_hash' => hash('sha256', 'https://93.184.216.34'),
            'categories' => ['tour', 'hotel', 'stay', 'visa'],
            'is_active' => true,
        ]);

        $first = app(ComparisonSourceScanner::class)->scan($source);
        $second = app(ComparisonSourceScanner::class)->scan($source->fresh());

        $this->assertSame(4, $first['found']);
        $this->assertSame(4, $first['created']);
        $this->assertSame(0, $second['created']);
        $this->assertSame(4, $second['updated']);
        $this->assertSame(4, TourSuggestion::count());
        $this->assertDatabaseHas('tour_suggestions', ['category' => 'tour', 'destination' => 'کیش']);
        $this->assertDatabaseHas('tour_suggestions', ['category' => 'hotel', 'destination' => 'مشهد']);
        $this->assertDatabaseHas('tour_suggestions', ['category' => 'stay', 'destination' => 'ماسال']);
        $this->assertDatabaseHas('tour_suggestions', ['category' => 'visa', 'destination' => 'دبی']);
        $this->assertSame(
            'https://93.184.216.34/tours/kish',
            data_get(TourSuggestion::where('category', 'tour')->sole()->metadata, 'discovery_sources.0.item_url')
        );
    }

    public function test_scanner_rejects_private_or_loopback_homepage_addresses(): void
    {
        Http::preventStrayRequests();
        $source = ComparisonSource::create([
            'name' => 'شبکه داخلی',
            'homepage_url' => 'http://127.0.0.1',
            'homepage_hash' => hash('sha256', 'http://127.0.0.1'),
            'categories' => ['tour'],
            'is_active' => true,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('آدرس‌های داخلی');
        app(ComparisonSourceScanner::class)->scan($source);
    }

    public function test_source_requires_at_least_one_valid_category_and_unique_homepage(): void
    {
        $admin = User::factory()->create();
        ComparisonSource::create([
            'name' => 'موجود',
            'homepage_url' => 'https://example.com',
            'homepage_hash' => hash('sha256', 'https://example.com'),
            'categories' => ['tour'],
            'is_active' => true,
        ]);

        $this->actingAs($admin)->post(route('admin.comparison-sources.store'), [
            'name' => 'تکراری',
            'homepage_url' => 'https://example.com/',
            'categories' => ['unknown'],
        ])->assertSessionHasErrors(['categories.0']);

        $this->actingAs($admin)->post(route('admin.comparison-sources.store'), [
            'name' => 'تکراری',
            'homepage_url' => 'https://example.com/',
            'categories' => ['tour'],
        ])->assertSessionHasErrors(['homepage_url']);
    }
}
