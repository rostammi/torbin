<?php

namespace Tests\Feature;

use App\Models\PriceSource;
use App\Models\Tour;
use App\Services\PriceCrawler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PriceCrawlerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_extracts_and_normalizes_a_persian_price(): void
    {
        Http::fake(['*' => Http::response('<div class="price">۱۲,۳۴۵,۰۰۰ تومان</div>')]);
        $tour = Tour::create([
            'title' => 'تور تست', 'slug' => 'test', 'description' => '...', 'is_active' => true,
        ]);
        $source = PriceSource::create([
            'tour_id' => $tour->id,
            'provider_name' => 'فروشنده تست',
            'source_url' => 'https://93.184.216.34/tour',
            'extraction_type' => 'regex',
            'selector' => '/price[^>]*>([^<]+)/i',
            'price_multiplier' => 1,
        ]);

        $this->assertTrue(app(PriceCrawler::class)->crawl($source));
        $this->assertSame(12_345_000, $source->fresh()->latest_price);
        $this->assertDatabaseHas('price_histories', ['price_source_id' => $source->id, 'price' => 12_345_000]);
    }
}
