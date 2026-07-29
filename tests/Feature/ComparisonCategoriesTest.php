<?php

namespace Tests\Feature;

use App\Models\Tour;
use App\Models\TourSuggestion;
use App\Services\Discovery\ComparisonCatalogDiscovery;
use App\Services\Discovery\ProviderCatalog;
use App\Services\TourSlugGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComparisonCategoriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_creates_unique_suggestions_for_all_four_categories(): void
    {
        $result = app(ComparisonCatalogDiscovery::class)->discover();

        $this->assertSame(148, $result['total']);
        $this->assertSame(103, TourSuggestion::where('category', 'tour')->count());
        $this->assertSame(15, TourSuggestion::where('category', 'hotel')->count());
        $this->assertSame(15, TourSuggestion::where('category', 'stay')->count());
        $this->assertSame(15, TourSuggestion::where('category', 'visa')->count());
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

        $this->get('/hotels')->assertOk()->assertSee('هتل مشهد');
        $this->get('/hotels/mashhad-hotels')->assertOk()->assertSee('جزئیات هتل');
        $this->get('/tours/mashhad-hotels')->assertNotFound();
        $this->assertSame(url('/hotels/mashhad-hotels'), $hotel->publicUrl());
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
}
