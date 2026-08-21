<?php

namespace Tests\Feature;

use App\Models\Tour;
use App\Models\TourSuggestion;
use App\Services\TourSlugGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TourSlugTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_tour_gets_a_readable_destination_slug_and_old_url_stops_resolving(): void
    {
        $tour = Tour::create([
            'title' => 'تور مسقط | مقایسه قیمت و خرید از معتبرترین آژانس‌ها',
            'slug' => 'tor-mskt',
            'description' => 'توضیحات',
            'is_active' => true,
        ]);
        TourSuggestion::create([
            'keyword' => 'تور مسقط',
            'suggested_title' => $tour->title,
            'destination' => 'مسقط',
            'trend_score' => 80,
            'source' => 'destination_catalog',
            'status' => 'created',
            'tour_id' => $tour->id,
            'metadata' => ['region' => 'foreign', 'variant' => 'main'],
        ]);

        $this->assertSame(1, app(TourSlugGenerator::class)->refreshAll());
        $this->assertSame('muscat-tour', $tour->fresh()->slug);
        $this->assertFalse(Schema::hasTable('tour_slug_redirects'));
        $this->get('/tour/tor-mskt/')->assertNotFound();
        $this->get('/tour/muscat-tour/')->assertOk();
        $this->get('/tours/muscat-tour')->assertNotFound();
    }

    public function test_variants_receive_distinct_search_friendly_slugs(): void
    {
        $generator = app(TourSlugGenerator::class);

        foreach ([
            'main' => 'muscat-tour',
            'cheap' => 'cheap-muscat-tour',
            'last_minute' => 'last-minute-muscat-tour',
            'installment' => 'installment-muscat-tour',
            'air' => 'air-muscat-tour',
            'from_tehran' => 'muscat-tour-from-tehran',
        ] as $variant => $expected) {
            $suggestion = new TourSuggestion([
                'keyword' => 'تور مسقط',
                'destination' => 'مسقط',
                'metadata' => ['variant' => $variant],
            ]);

            $this->assertSame($expected, $generator->forSuggestion($suggestion));
        }
    }
}
