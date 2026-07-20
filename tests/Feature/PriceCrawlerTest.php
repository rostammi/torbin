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
        Http::fake(['*' => Http::response('<main><div class="price">۱۲,۳۴۵,۰۰۰ تومان</div><h2>دیدنی‌های مقصد تست</h2><p>متن متعلق به سایت منبع است و نباید عیناً بازنشر شود.</p></main>')]);
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
        $this->assertSame('دیدنی‌های مقصد تست', $source->fresh()->content_insights[0]['title']);
        $this->assertSame('دیدنی‌های مقصد تست', $tour->fresh()->auto_content['topics'][0]['title']);
        $this->get('/tours/test')->assertOk()->assertSee('راهنمای تکمیلی تور تست')->assertSee('دیدنی‌های مقصد تست');
        $this->assertStringNotContainsString('متن متعلق به سایت منبع است', json_encode($tour->fresh()->auto_content));
    }

    public function test_content_can_be_crawled_for_a_manual_price_source_without_changing_its_status(): void
    {
        Http::fake(['*' => Http::response('<main><h2>بهترین زمان سفر به شیراز</h2></main>', 200, ['Content-Type' => 'text/html'])]);
        $tour = Tour::create([
            'title' => 'تور شیراز', 'slug' => 'manual-shiraz', 'description' => '...', 'is_active' => true,
        ]);
        $source = PriceSource::create([
            'tour_id' => $tour->id,
            'provider_name' => 'آژانس دستی',
            'source_url' => 'https://93.184.216.34/shiraz',
            'buy_url' => 'https://93.184.216.34/shiraz',
            'extraction_type' => 'manual',
            'latest_price' => 8_000_000,
            'last_status' => 'manual',
        ]);

        $this->assertTrue(app(PriceCrawler::class)->crawlContent($source, true));
        $this->assertSame('manual', $source->fresh()->last_status);
        $this->assertSame('بهترین زمان سفر به شیراز', $tour->fresh()->auto_content['topics'][0]['title']);
    }
}
