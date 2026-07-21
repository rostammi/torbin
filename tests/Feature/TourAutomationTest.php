<?php

namespace Tests\Feature;

use App\Models\TourSuggestion;
use App\Models\User;
use App\Services\Discovery\PopularTourDiscovery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TourAutomationTest extends TestCase
{
    use RefreshDatabase;

    public function test_discovery_always_builds_at_least_one_hundred_suggestions_and_prioritizes_trends(): void
    {
        Http::fake([
            'trends.google.com/*' => Http::response(<<<'XML'
                <?xml version="1.0"?><rss xmlns:ht="https://trends.google.com/trending/rss"><channel>
                <item><title>تور کیش</title><ht:approx_traffic>20K+</ht:approx_traffic></item>
                </channel></rss>
                XML),
        ]);

        $result = app(PopularTourDiscovery::class)->discover(120);

        $this->assertGreaterThanOrEqual(100, $result['total']);
        $this->assertDatabaseHas('tour_suggestions', [
            'keyword' => 'تور کیش',
            'source' => 'google_trends',
        ]);
    }

    public function test_admin_can_create_an_active_tour_with_four_automated_providers_in_one_click(): void
    {
        config()->set('crawler.providers', collect(range(1, 4))->map(fn ($number) => [
            'name' => "ارائه‌دهنده {$number}",
            'type' => 'structured',
            'url' => 'https://93.184.216.34/tours',
        ])->all());
        Http::fake(['93.184.216.34/*' => Http::response(<<<'HTML'
            <html><head><script type="application/ld+json">
            {"@type":"Product","name":"تور کیش","offers":{"@type":"Offer","price":"12500000","url":"https://93.184.216.34/buy"},"aggregateRating":{"@type":"AggregateRating","ratingValue":4.6,"ratingCount":82}}
            </script></head><body><main><h2>راهنمای سفر به کیش</h2></main></body></html>
            HTML)]);
        $suggestion = TourSuggestion::create([
            'keyword' => 'تور کیش',
            'suggested_title' => 'تور کیش | مقایسه قیمت',
            'destination' => 'کیش',
            'trend_score' => 90,
            'source' => 'google_trends',
            'status' => 'pending',
        ]);

        $response = $this->actingAs(User::factory()->create())
            ->post(route('admin.suggestions.store', $suggestion));

        $tour = $suggestion->fresh()->tour;
        $response->assertRedirect(route('admin.tours.edit', $tour));
        $this->assertTrue($tour->is_active);
        $this->assertSame(4, $tour->priceSources()->count());
        $this->assertSame(4, $tour->priceSources()->where('latest_price', 12_500_000)->count());
        $this->assertSame(4.6, $tour->priceSources()->first()->latest_rating);
        $this->assertDatabaseHas('sync_runs', ['type' => 'provision_tour', 'status' => 'success']);
    }
}
