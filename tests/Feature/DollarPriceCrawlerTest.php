<?php

namespace Tests\Feature;

use App\Models\PriceSource;
use App\Models\Tour;
use App\Services\Currency\TgjuDollarRate;
use App\Services\PriceCrawler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DollarPriceCrawlerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::clear();
    }

    public function test_it_converts_a_numeric_usd_json_price_to_tomans_with_the_tgju_rate(): void
    {
        Http::fake([
            'www.tgju.org/*' => Http::response('<span data-col="info.last_trade.PDrCotVal">1,000,000</span>'),
            '93.184.216.34/*' => Http::response(['offer' => ['price' => 250.5]]),
        ]);
        $source = $this->source('json', 'usd', 'offer.price');

        $this->assertTrue(app(PriceCrawler::class)->crawl($source));
        $source->refresh();

        $this->assertSame(25_050_000, $source->latest_price);
        $this->assertSame('تومان', $source->currency);
        $this->assertSame(100_000, $source->latest_details['usd_rate_toman']);
        $this->assertSame(250.5, $source->latest_details['source_usd_price']);
    }

    public function test_it_adds_the_local_part_of_a_mixed_dollar_and_toman_price(): void
    {
        Http::fake([
            'www.tgju.org/*' => Http::response('<h3>نرخ فعلی: 1,000,000</h3><p>واحد پولی: ریال</p>'),
            '93.184.216.34/*' => Http::response('<div class="price">250 دلار + 3,500,000 تومان</div>'),
        ]);
        $source = $this->source('regex', 'mixed', '/price[^>]*>([^<]+)/i');

        $this->assertTrue(app(PriceCrawler::class)->crawl($source));
        $source->refresh();

        $this->assertSame(28_500_000, $source->latest_price);
        $this->assertSame(3_500_000, $source->latest_details['source_local_price_toman']);
    }

    public function test_it_uses_the_last_known_rate_when_tgju_is_temporarily_unavailable(): void
    {
        Cache::put('exchange-rate:tgju:usd-toman:last-known', 99_000, now()->addDay());
        Http::fake(['www.tgju.org/*' => Http::response('', 503)]);

        $this->assertSame(99_000, app(TgjuDollarRate::class)->toman());
    }

    public function test_marketplace_html_auto_detects_a_mixed_price(): void
    {
        Http::fake([
            'www.tgju.org/*' => Http::response('<span data-col="info.last_trade.PDrCotVal">1,000,000</span>'),
            '93.184.216.34/*' => Http::response(<<<'HTML'
                <article>
                    <a href="/offer/shiraz">پیشنهاد شیراز <span class="price">$200 + 5,000,000 تومان</span></a>
                </article>
                HTML),
        ]);
        $source = $this->source('marketplace_html', 'auto', 'شیراز');

        $this->assertTrue(app(PriceCrawler::class)->crawl($source));
        $this->assertSame(25_000_000, $source->fresh()->latest_price);
    }

    private function source(string $type, string $sourceCurrency, string $selector): PriceSource
    {
        $tour = Tour::create([
            'title' => 'پیشنهاد تست دلاری',
            'slug' => 'usd-offer-'.str()->random(8),
            'description' => 'توضیحات',
            'is_active' => true,
        ]);

        return $tour->priceSources()->create([
            'provider_name' => 'فروشنده دلاری',
            'source_url' => 'https://93.184.216.34/offer',
            'buy_url' => 'https://93.184.216.34/offer',
            'extraction_type' => $type,
            'selector' => $selector,
            'price_multiplier' => 1,
            'currency' => 'تومان',
            'source_currency' => $sourceCurrency,
            'is_active' => true,
        ]);
    }
}
