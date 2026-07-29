<?php

namespace Tests\Unit;

use App\Models\Tour;
use App\Services\Crawlers\MarketplaceHtmlCrawler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MarketplaceHtmlCrawlerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_uses_stable_destination_page_instead_of_ephemeral_offer_url(): void
    {
        Http::fake([
            'https://93.184.216.34/tour' => Http::response(<<<'HTML'
                <html lang="fa"><body>
                    <a href="/تور-باتومی">تور باتومی</a>
                    <article>
                        <a href="/TourInfo/123-تور-باتومی?t=999">تور باتومی ۳ شب</a>
                        <span class="price">۳۹,۸۹۰,۰۰۰ تومان</span>
                    </article>
                </body></html>
                HTML),
        ]);
        $tour = Tour::create([
            'title' => 'تور باتومی',
            'slug' => 'stable-batumi',
            'description' => '...',
            'is_active' => true,
        ]);
        $source = $tour->priceSources()->create([
            'provider_name' => 'بازار نمونه',
            'source_url' => 'https://93.184.216.34/tour',
            'buy_url' => 'https://93.184.216.34/TourInfo/old?t=1',
            'extraction_type' => 'marketplace_html',
            'selector' => 'باتومی',
            'currency' => 'تومان',
            'is_active' => true,
        ]);

        $result = app(MarketplaceHtmlCrawler::class)->crawl($source);

        $this->assertSame(39_890_000, $result->price);
        $this->assertSame('https://93.184.216.34/تور-باتومی', $result->buyUrl);
        $this->assertSame(
            'https://93.184.216.34/TourInfo/123-تور-باتومی?t=999',
            $result->details['offer_url']
        );
    }

    public function test_it_probes_a_confirmed_stable_destination_when_homepage_only_has_ephemeral_offers(): void
    {
        Http::fake(function ($request) {
            if ($request->url() === 'https://93.184.216.34/tour') {
                return Http::response(<<<'HTML'
                    <article>
                        <a href="/TourInfo/123-تور-باتومی?t=999">تور باتومی ۳ شب</a>
                        <span class="price">۳۹,۸۹۰,۰۰۰ تومان</span>
                    </article>
                    HTML);
            }
            if (rawurldecode($request->url()) === 'https://93.184.216.34/تور-باتومی') {
                return Http::response('<html><title>تور باتومی ارزان</title><h1>تور باتومی</h1></html>');
            }

            return Http::response('', 404);
        });
        $tour = Tour::create([
            'title' => 'تور باتومی',
            'slug' => 'probe-stable-batumi',
            'description' => '...',
            'is_active' => true,
        ]);
        $source = $tour->priceSources()->create([
            'provider_name' => 'بازار نمونه',
            'source_url' => 'https://93.184.216.34/tour',
            'extraction_type' => 'marketplace_html',
            'selector' => 'باتومی',
            'currency' => 'تومان',
            'is_active' => true,
        ]);

        $result = app(MarketplaceHtmlCrawler::class)->crawl($source);

        $stableUrl = 'https://93.184.216.34/'.rawurlencode('تور-باتومی');
        $this->assertSame($stableUrl, $result->buyUrl);
        $this->assertSame($result->buyUrl, $result->details['stable_destination_url']);
    }
}
