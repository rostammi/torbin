<?php

namespace Tests\Feature;

use App\Jobs\RunAutomationSync;
use App\Models\SyncRun;
use App\Models\Tour;
use App\Models\TourSuggestion;
use App\Models\User;
use App\Services\Discovery\ComparisonCatalogDiscovery;
use App\Services\Discovery\ProviderCatalog;
use App\Services\TourSlugGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ComparisonCategoriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_creates_unique_suggestions_for_all_four_categories(): void
    {
        $result = app(ComparisonCatalogDiscovery::class)->discover();

        $this->assertSame(169, $result['reference']['total']);
        $this->assertSame(95, $result['reference']['categories']['tour']['total']);
        $this->assertSame(24, $result['reference']['categories']['hotel']['total']);
        $this->assertSame(21, $result['reference']['categories']['stay']['total']);
        $this->assertSame(29, $result['reference']['categories']['visa']['total']);
        $this->assertGreaterThan(103, TourSuggestion::where('category', 'tour')->count());
        $this->assertGreaterThan(15, TourSuggestion::where('category', 'hotel')->count());
        $this->assertGreaterThan(15, TourSuggestion::where('category', 'stay')->count());
        $this->assertGreaterThan(15, TourSuggestion::where('category', 'visa')->count());
        $this->assertSame(3, TourSuggestion::where('destination', 'کیش')->count());

        $hotel = TourSuggestion::where([
            'category' => 'hotel',
            'destination' => 'مشهد',
        ])->sole();
        $this->assertSame('هتل مشهد', $hotel->keyword);
        $this->assertContains('رزرو هتل مشهد', $hotel->metadata['keywords']);
    }

    public function test_each_category_has_its_own_listing_and_detail_url(): void
    {
        $hotel = Tour::create([
            'category' => 'hotel',
            'title' => 'هتل مشهد',
            'slug' => 'mashhad-hotels',
            'description' => 'مقایسه هتل‌های مشهد',
            'is_active' => true,
        ]);

        $this->get('/category/hotel/')->assertOk()->assertSee('هتل مشهد');
        $this->get('/hotels')->assertNotFound();
        $this->get('/hotel/mashhad-hotels/')->assertOk()->assertSee('جزئیات هتل');
        $this->get('/hotels/mashhad-hotels')->assertNotFound();
        $this->get('/tour/mashhad-hotels/')->assertNotFound();
        $this->assertSame(url('/hotel/mashhad-hotels').'/', $hotel->publicUrl());
    }

    public function test_category_navigation_uses_the_new_urls_without_legacy_routes(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('href="'.url('/category/hotel').'/' .'"', false)
            ->assertSee('href="'.url('/category/visa').'/' .'"', false)
            ->assertSee('href="'.url('/category/accommodation').'/' .'"', false)
            ->assertSee('href="'.url('/category/tour').'/' .'"', false);

        foreach (['/tours', '/hotels', '/stays', '/visas'] as $legacyUrl) {
            $this->get($legacyUrl)->assertNotFound();
        }
    }

    public function test_each_comparison_page_uses_its_singular_public_url(): void
    {
        foreach ([
            'tour' => ['/tour/', '/tours/'],
            'hotel' => ['/hotel/', '/hotels/'],
            'stay' => ['/accommodation/', '/stays/'],
            'visa' => ['/visa/', '/visas/'],
        ] as $category => [$baseUrl, $legacyBaseUrl]) {
            $tour = Tour::create([
                'category' => $category,
                'title' => 'صفحه '.$category,
                'slug' => 'comparison-'.$category,
                'description' => 'توضیحات مقایسه',
                'is_active' => true,
            ]);

            $this->assertSame(url($baseUrl.$tour->slug).'/', $tour->publicUrl());
            $this->get($tour->publicUrl())->assertOk();
            $this->get($legacyBaseUrl.$tour->slug)->assertNotFound();
        }
    }

    public function test_category_pages_use_category_specific_slug_and_providers(): void
    {
        $suggestion = TourSuggestion::create([
            'category' => 'hotel',
            'keyword' => 'هتل مشهد',
            'suggested_title' => 'هتل مشهد | مقایسه قیمت و رزرو',
            'destination' => 'مشهد',
            'source' => 'hotel_catalog',
            'metadata' => ['keywords' => ['هتل مشهد', 'رزرو هتل مشهد']],
        ]);
        $tour = Tour::create([
            'category' => 'hotel',
            'title' => $suggestion->suggested_title,
            'slug' => app(TourSlugGenerator::class)->forSuggestion($suggestion),
            'description' => 'مقایسه قیمت',
        ]);

        app(ProviderCatalog::class)->attach($tour, 'مشهد');

        $this->assertSame('mashhad-hotels', $tour->slug);
        $this->assertSame(10, $tour->priceSources()->count());
        $this->assertDatabaseHas('price_sources', [
            'tour_id' => $tour->id,
            'provider_name' => 'علی‌بابا هتل',
        ]);
        $this->assertDatabaseMissing('price_sources', [
            'tour_id' => $tour->id,
            'provider_name' => 'علی‌بابا',
        ]);
    }

    public function test_sync_center_can_discover_each_category_independently(): void
    {
        Queue::fake();
        $admin = User::factory()->create();

        $this->actingAs($admin)->get(route('admin.sync.index'))
            ->assertOk()
            ->assertSee('اجرای کشف تورها')
            ->assertSee('اجرای کشف هتل‌ها')
            ->assertSee('اجرای کشف اقامتگاه‌ها')
            ->assertSee('اجرای کشف ویزاها')
            ->assertSee('value="discover_tours"', false)
            ->assertSee('value="discover_hotels"', false)
            ->assertSee('value="discover_stays"', false)
            ->assertSee('value="discover_visas"', false);

        $this->post(route('admin.sync.run'), ['type' => 'discover_hotels'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $run = SyncRun::where('type', 'discover_hotels')->sole();
        Queue::assertPushed(RunAutomationSync::class, fn (RunAutomationSync $job) => $job->runId === $run->id);

        app()->call([new RunAutomationSync($run->id), 'handle']);

        $this->assertSame('success', $run->fresh()->status);
        $this->assertGreaterThan(0, TourSuggestion::where('category', 'hotel')->count());
        $this->assertGreaterThan(0, Tour::where('category', 'hotel')->count());
        $this->assertDatabaseHas('tour_suggestions', [
            'category' => 'hotel',
            'destination' => 'آمستردام',
        ]);
        $this->assertSame(0, TourSuggestion::where('category', 'tour')->count());
        $this->assertSame(0, TourSuggestion::where('category', 'stay')->count());
        $this->assertSame(0, TourSuggestion::where('category', 'visa')->count());
        $this->assertSame(0, Tour::where('category', 'tour')->count());
        $this->assertSame(0, Tour::where('category', 'stay')->count());
        $this->assertSame(0, Tour::where('category', 'visa')->count());
    }

    public function test_each_category_discovery_includes_its_geyt_reference_pages(): void
    {
        $discovery = app(ComparisonCatalogDiscovery::class);

        $discovery->discoverCategory('stay');
        $this->assertDatabaseHas('tour_suggestions', [
            'category' => 'stay',
            'destination' => 'اولسبلنگاه',
        ]);
        $this->assertSame(0, TourSuggestion::where('category', 'visa')->count());

        $discovery->discoverCategory('visa');
        $this->assertDatabaseHas('tour_suggestions', [
            'category' => 'visa',
            'destination' => 'اوگاندا',
        ]);
    }
}
