<?php

namespace Tests\Feature;

use App\Models\PriceSource;
use App\Models\Tour;
use App\Models\TourSuggestion;
use App\Services\TourDestinationConsolidator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TourDestinationConsolidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_destination_pages_are_merged_into_one_canonical_tour(): void
    {
        Schema::table('tour_suggestions', function (Blueprint $table) {
            $table->dropUnique(['category', 'destination']);
        });

        $canonical = Tour::create([
            'title' => 'تور آنتالیا',
            'slug' => 'antalya-tour',
            'description' => 'صفحه اصلی',
            'cover_image' => 'tours/antalya-main.jpg',
            'gallery' => ['tours/antalya-second.jpg'],
            'is_active' => true,
        ]);
        $duplicate = Tour::create([
            'title' => 'تور آنتالیا لحظه آخری',
            'slug' => 'last-minute-antalya-tour',
            'description' => 'صفحه تکراری',
            'cover_image' => 'tours/antalya-last-minute.jpg',
            'is_active' => true,
        ]);
        $orphan = Tour::create([
            'title' => 'تور آنتالیا ارزان',
            'slug' => 'cheap-antalya-tour',
            'description' => 'صفحه تکراری بدون پیشنهاد',
            'is_active' => true,
        ]);

        TourSuggestion::create([
            'keyword' => 'تور آنتالیا',
            'suggested_title' => 'تور آنتالیا',
            'destination' => 'آنتالیا',
            'trend_score' => 90,
            'source' => 'destination_catalog',
            'status' => 'created',
            'tour_id' => $canonical->id,
            'metadata' => ['region' => 'foreign', 'variant' => 'main'],
        ]);
        TourSuggestion::create([
            'keyword' => 'تور آنتالیا لحظه آخری',
            'suggested_title' => 'تور آنتالیا لحظه آخری',
            'destination' => 'آنتالیا',
            'trend_score' => 80,
            'source' => 'destination_catalog',
            'status' => 'created',
            'tour_id' => $duplicate->id,
            'metadata' => ['region' => 'foreign', 'variant' => 'last_minute'],
        ]);
        PriceSource::create([
            'tour_id' => $canonical->id,
            'provider_name' => 'آژانس اصلی',
            'source_url' => 'https://main.example/tour',
            'latest_price' => 20_000_000,
            'last_status' => 'success',
        ]);
        PriceSource::create([
            'tour_id' => $duplicate->id,
            'provider_name' => 'آژانس دوم',
            'source_url' => 'https://second.example/tour',
            'latest_price' => 18_000_000,
            'last_status' => 'success',
        ]);

        $result = app(TourDestinationConsolidator::class)->consolidate('آنتالیا');

        $this->assertSame(['merged_tours' => 2, 'removed_suggestions' => 1], $result);
        $this->assertSame(1, Tour::count());
        $this->assertSame(1, TourSuggestion::where('destination', 'آنتالیا')->count());
        $this->assertSame(2, $canonical->fresh()->priceSources()->count());
        $this->assertSame([
            'tours/antalya-second.jpg',
            'tours/antalya-last-minute.jpg',
        ], $canonical->fresh()->gallery);
        $this->assertContains('تور آنتالیا لحظه آخری', $canonical->fresh()->seo_keywords);
        $this->assertFalse(Schema::hasTable('tour_slug_redirects'));
        $this->get('/tour/last-minute-antalya-tour/')->assertNotFound();
        $this->get('/tour/'.$orphan->slug.'/')->assertNotFound();
    }
}
