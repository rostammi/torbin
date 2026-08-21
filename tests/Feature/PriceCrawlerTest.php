<?php

namespace Tests\Feature;

use App\Models\PriceSource;
use App\Models\SyncRun;
use App\Models\Tour;
use App\Models\User;
use App\Services\Crawlers\SourceUrlResolver;
use App\Services\PriceCrawler;
use App\Services\TourPriceUpdater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
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
        $this->get('/tour/test/')->assertOk()->assertSee('راهنمای تکمیلی تور تست')->assertSee('دیدنی‌های مقصد تست');
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

    public function test_failed_source_finds_a_destination_url_on_the_same_site_and_retries_before_deletion(): void
    {
        Http::fake(function (Request $request) {
            $path = parse_url($request->url(), PHP_URL_PATH);

            return match ($path) {
                '/wrong-tour-url' => Http::response('not found', 404),
                '/tours/kish' => Http::response(
                    '<html><head><title>تور کیش</title></head><body><div class="price">۹,۷۵۰,۰۰۰ تومان</div></body></html>'
                ),
                default => Http::response('<a href="/tours/kish">مشاهده و خرید تور کیش</a>'),
            };
        });
        $tour = Tour::create([
            'title' => 'تور کیش',
            'slug' => 'kish-resolved-source',
            'description' => 'توضیحات',
            'is_active' => true,
        ]);
        $source = $tour->priceSources()->create([
            'provider_name' => 'سایت با آدرس قدیمی',
            'source_url' => 'https://93.184.216.34/wrong-tour-url',
            'buy_url' => 'https://93.184.216.34/wrong-tour-url',
            'extraction_type' => 'marketplace_html',
            'selector' => 'کیش',
            'currency' => 'تومان',
            'is_active' => true,
        ]);

        $this->assertSame(
            ['https://93.184.216.34/tours/kish'],
            app(SourceUrlResolver::class)->candidates($source),
        );
        $this->assertTrue(app(PriceCrawler::class)->crawl($source));
        $source->refresh();
        $this->assertSame('https://93.184.216.34/tours/kish', $source->source_url);
        $this->assertSame(9_750_000, $source->latest_price);
        $this->assertSame('success', $source->last_status);
        $this->assertDatabaseHas('price_sources', ['id' => $source->id]);
    }

    public function test_single_tour_price_update_checks_ten_primary_sites_then_fallback_until_three_prices(): void
    {
        $tour = $this->fakeTenPrimaryAndOneFallback();

        $this->actingAs(User::factory()->create())
            ->post(route('admin.tours.crawl', $tour))
            ->assertRedirect()
            ->assertSessionHas('success', fn (string $message) => str_contains($message, '10 سایت اصلی')
                && str_contains($message, '8 منبع بدون قیمت حفظ شد'));

        $tour->refresh();
        $this->assertSame(11, $tour->priceSources()->count());
        $this->assertSame(8, $tour->priceSources()->where('last_status', 'failed')->count());
        $this->assertSame(3, $tour->priceSources()->where('latest_price', '>', 0)->count());
        $this->assertSame(8_450_000, $tour->priceSources()->where('provider_name', 'سفر۲۴ تست')->value('latest_price'));
    }

    public function test_group_price_update_uses_the_same_ten_site_and_fallback_policy_per_tour(): void
    {
        $this->fakeTenPrimaryAndOneFallback();

        $this->actingAs(User::factory()->create())
            ->post(route('admin.sync.run'), ['type' => 'prices'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $run = SyncRun::where('type', 'prices')->sole();
        $this->assertSame(1, $run->details['prices']['tours']);
        $this->assertSame(11, $run->details['prices']['checked']);
        $this->assertSame(1, $run->details['prices']['fallback_checked']);
        $this->assertSame(8, $run->details['prices']['failed_sources_retained']);
        $this->assertSame(1, $run->details['prices']['with_minimum_prices']);
        $this->assertSame([], $run->details['prices']['needs_new_crawler']);
    }

    private function fakeTenPrimaryAndOneFallback(): Tour
    {
        config()->set('crawler.providers', collect(range(1, TourPriceUpdater::PRIMARY_PROVIDER_COUNT))
            ->map(fn (int $number) => [
                'name' => "سایت اصلی {$number}",
                'type' => 'structured',
                'url' => "https://93.184.216.34/primary/{$number}",
            ])->all());
        config()->set('crawler.fallback_providers', [[
            'name' => 'سفر۲۴ تست',
            'type' => 'safar24',
            'url' => 'https://93.184.216.34/fallback',
        ]]);
        Http::fake(function (Request $request) {
            if (preg_match('~/primary/1(?:\?|$)~', $request->url())) {
                return Http::response($this->structuredOffer(10_000_000));
            }
            if (preg_match('~/primary/2(?:\?|$)~', $request->url())) {
                return Http::response($this->structuredOffer(11_000_000));
            }
            if (str_contains($request->url(), '/fallback')) {
                return Http::response('<a href="/tour/kish">تور کیش ۸,۴۵۰,۰۰۰ تومان</a>');
            }

            return Http::response('<html><body>در حال حاضر بدون قیمت</body></html>');
        });

        return Tour::create([
            'title' => 'تور کیش',
            'slug' => 'kish-price-policy-'.str()->random(8),
            'description' => 'توضیحات',
            'is_active' => true,
        ]);
    }

    private function structuredOffer(int $price): string
    {
        return <<<HTML
            <html><head><script type="application/ld+json">
            {"@type":"Product","offers":{"@type":"Offer","price":"{$price}","url":"https://93.184.216.34/buy"}}
            </script></head><body></body></html>
            HTML;
    }
}
