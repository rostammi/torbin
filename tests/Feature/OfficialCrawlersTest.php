<?php

namespace Tests\Feature;

use App\Models\PriceSource;
use App\Models\Tour;
use App\Services\PriceCrawler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OfficialCrawlersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-07-20 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_alibaba_stores_the_cheapest_price_and_converts_rials_to_tomans(): void
    {
        Http::fake([
            'ws.alibaba.ir/*' => Http::response(['result' => ['items' => [
                ['minPersonPrice' => 93_280_000, 'url' => '/tour/expensive'],
                ['minPersonPrice' => 85_000_000, 'url' => '/tour/cheapest'],
            ]]]),
        ]);
        $source = $this->source('alibaba', 'https://www.alibaba.ir/tour/iran-tehran/iran-shiraz?rooms=2');

        $this->assertTrue(app(PriceCrawler::class)->crawl($source));
        $this->assertSame(8_500_000, $source->fresh()->latest_price);
        $this->assertSame('https://www.alibaba.ir/tour/cheapest', $source->fresh()->buy_url);
    }

    public function test_alibaba_resolves_destination_from_the_tour_title(): void
    {
        Http::fake([
            '*available/sources*' => Http::response(['result' => [[
                'places' => [['name' => 'شیراز', 'domainCode' => 'iran-shiraz']],
            ]]]),
            '*available/erp/*' => Http::response(['result' => ['items' => [
                ['minPersonPrice' => 90_000_000, 'url' => '/tour/shiraz'],
            ]]]),
        ]);
        $source = $this->source('alibaba', 'https://www.alibaba.ir/tour');

        $this->assertTrue(app(PriceCrawler::class)->crawl($source));
        $this->assertSame(9_000_000, $source->fresh()->latest_price);
    }

    public function test_safarmarket_combines_both_apis_and_normalizes_their_units(): void
    {
        Http::fake([
            'ttourapi.safarmarket.com/*' => Http::response(['data' => [
                'cards' => [['price' => 28_000_000]], 'suggested_cards' => [],
            ]]),
            'safarmarket.com/tourApi/*' => Http::response(['data' => [
                'cards' => [], 'suggested_cards' => [[
                    'price' => 250_000_000,
                    'residency_name' => 'هتل زندیه',
                    'residency_star' => 5,
                    'vendor_name' => 'آژانس نمونه',
                    'date_dep' => '1405/05/01',
                    'date_arr' => '1405/05/04',
                ]],
            ]]),
        ]);
        $source = $this->source('safarmarket', 'https://safarmarket.com/tours2/19981/18911/allTime/allBudgets/');

        $this->assertTrue(app(PriceCrawler::class)->crawl($source));
        $source->refresh();
        $this->assertSame(25_000_000, $source->latest_price);
        $this->assertSame(5.0, $source->latest_rating);
        $this->assertSame('hotel_stars', $source->rating_type);
        $this->assertSame('هتل زندیه', $source->latest_details['hotel']);
        $this->assertDatabaseHas('price_histories', [
            'price_source_id' => $source->id,
            'price' => 25_000_000,
            'rating_type' => 'hotel_stars',
            'offer_title' => 'هتل زندیه',
            'departure_at' => '1405/05/01',
        ]);

        $this->assertTrue(app(PriceCrawler::class)->crawl($source));
        $this->assertSame(2, $source->history()->count(), 'Each successful crawl must create a new snapshot.');
    }

    public function test_flytoday_ignores_expired_tours_and_stores_zero_when_none_are_active(): void
    {
        $body = '<script>self.__next_f.push([1,"'.
            '{\\"title\\":\\"تور شیراز\\",\\"subtitle\\":\\"سفر\\",\\"price\\":\\"120،000،000 ریال\\",\\"lastDate\\":\\"2026-07-19\\"}'.
            '"])</script>';
        Http::fake(['www.flytoday.ir/*' => Http::response($body)]);
        $source = $this->source('flytoday', 'https://www.flytoday.ir/packagetour');

        $this->assertTrue(app(PriceCrawler::class)->crawl($source));
        $this->assertSame(0, $source->fresh()->latest_price);
        $this->assertSame('empty', $source->fresh()->last_status);
    }

    public function test_flytoday_uses_the_lowest_active_rial_price(): void
    {
        $body = '<script>self.__next_f.push([1,"'.
            '{\\"title\\":\\"تور شیراز\\",\\"subtitle\\":\\"اول\\",\\"price\\":\\"190،000،000 ریال\\",\\"lastDate\\":\\"2026-07-22\\"},'.
            '{\\"title\\":\\"تور شیراز ویژه\\",\\"subtitle\\":\\"دوم\\",\\"price\\":\\"175،000،000 ریال\\",\\"lastDate\\":\\"2026-08-01\\"}'.
            '"])</script>';
        Http::fake(['www.flytoday.ir/*' => Http::response($body)]);
        $source = $this->source('flytoday', 'https://www.flytoday.ir/packagetour');

        $this->assertTrue(app(PriceCrawler::class)->crawl($source));
        $this->assertSame(17_500_000, $source->fresh()->latest_price);
    }

    private function source(string $type, string $url): PriceSource
    {
        $tour = Tour::create([
            'title' => 'تور شیراز',
            'slug' => 'shiraz-'.str()->random(8),
            'description' => 'توضیحات',
            'is_active' => true,
        ]);

        return $tour->priceSources()->create([
            'provider_name' => $type,
            'source_url' => $url,
            'buy_url' => $url,
            'extraction_type' => $type,
            'currency' => 'تومان',
            'is_active' => true,
        ]);
    }
}
