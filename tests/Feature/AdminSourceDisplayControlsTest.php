<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Models\Tour;
use App\Models\User;
use App\Services\PriceCrawler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminSourceDisplayControlsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_updates_display_settings_once_for_every_offer_from_an_agency(): void
    {
        $firstSource = $this->tour()->priceSources()->create([
            'provider_name' => 'علی‌بابا', 'source_url' => 'https://example.com/one',
            'extraction_type' => 'manual', 'latest_price' => 12_000_000, 'is_active' => true,
        ]);
        $secondSource = $this->tour()->priceSources()->create([
            'provider_name' => 'علی‌بابا', 'source_url' => 'https://example.com/two',
            'extraction_type' => 'manual', 'latest_price' => 13_000_000, 'is_active' => true,
        ]);

        $this->actingAs(User::factory()->create())
            ->put(route('admin.agencies.update', $firstSource->agency), [
                'name' => 'علی‌بابا',
                'cost_per_click' => 1000,
                'contact_priority' => 50,
                'display_priority' => 25,
                'contact_phone' => '021-88776655',
                'is_contact_only' => true,
                'is_pinned' => true,
            ])
            ->assertRedirect();

        foreach ([$firstSource->fresh(), $secondSource->fresh()] as $source) {
            $this->assertTrue($source->is_contact_only);
            $this->assertTrue($source->is_pinned);
            $this->assertTrue($source->is_featured);
            $this->assertSame(25, $source->display_priority);
            $this->assertSame('021-88776655', $source->contact_phone);
        }
    }

    public function test_pinned_source_stays_first_after_price_crawl_and_uses_its_contact_number(): void
    {
        SiteSetting::query()->create(['key' => SiteSetting::CONTACT_PHONE, 'value' => '09190000000']);
        $tour = $this->tour();
        $cheap = $tour->priceSources()->create([
            'provider_name' => 'منبع ارزان',
            'source_url' => 'https://example.com/cheap',
            'buy_url' => 'https://example.com/cheap-buy',
            'extraction_type' => 'manual',
            'latest_price' => 5_000_000,
            'display_priority' => 100,
            'is_active' => true,
        ]);
        $pinned = $tour->priceSources()->create([
            'provider_name' => 'منبع ویژه تماس',
            'source_url' => 'https://93.184.216.34/offer',
            'buy_url' => 'https://93.184.216.34/buy',
            'extraction_type' => 'json',
            'selector' => 'price',
            'latest_price' => 9_000_000,
            'is_contact_only' => true,
            'contact_phone' => '021-11223344',
            'is_pinned' => true,
            'is_featured' => true,
            'display_priority' => 100,
            'is_active' => true,
        ]);
        $priority = $tour->priceSources()->create([
            'provider_name' => 'منبع دارای اولویت',
            'source_url' => 'https://example.com/priority',
            'buy_url' => 'https://example.com/priority-buy',
            'extraction_type' => 'manual',
            'latest_price' => 8_000_000,
            'display_priority' => 10,
            'is_active' => true,
        ]);
        $cheap->agency->update(['balance' => 100_000]);
        $pinned->agency->update(['balance' => 100_000]);
        $priority->agency->update(['balance' => 100_000]);

        Http::fake([
            '93.184.216.34/*' => Http::response(['price' => 15_000_000]),
        ]);
        $this->assertTrue(app(PriceCrawler::class)->crawl($pinned));

        $response = $this->get($tour->publicUrl())->assertOk();
        $response->assertSeeInOrder(['منبع ویژه تماس', 'منبع دارای اولویت', 'منبع ارزان']);
        $response->assertSee('پیشنهاد ویژه');
        $response->assertSee('15,000,000');
        $response->assertSee('href="tel:02111223344" hidden', false);
        $response->assertSee('>021-11223344</a>', false);
        $response->assertDontSee(route('outbound.click', $pinned), false);

        $this->get(route('outbound.click', $pinned))
            ->assertRedirect($tour->publicUrl())
            ->assertSessionHas('error');
        $this->assertDatabaseCount('outbound_clicks', 0);

        $this->assertTrue($pinned->fresh()->is_pinned);
        $this->assertTrue($pinned->fresh()->is_featured);
        $this->assertSame(
            [$pinned->id, $priority->id, $cheap->id],
            $tour->publicComparisonSources()->pluck('id')->all(),
        );
    }

    public function test_pinning_another_source_moves_the_previous_one_out_of_the_pinned_position(): void
    {
        $tour = $this->tour();
        $first = $tour->priceSources()->create([
            'provider_name' => 'منبع اول', 'source_url' => 'https://example.com/first',
            'extraction_type' => 'manual', 'latest_price' => 1_000_000,
            'is_pinned' => true, 'is_featured' => true,
        ]);
        $second = $tour->priceSources()->create([
            'provider_name' => 'منبع دوم', 'source_url' => 'https://example.com/second',
            'extraction_type' => 'manual', 'latest_price' => 2_000_000,
        ]);

        $second->update(['is_pinned' => true]);

        $this->assertFalse($first->fresh()->is_pinned);
        $this->assertTrue($second->fresh()->is_pinned);
        $this->assertTrue($second->fresh()->is_featured);
    }

    private function tour(): Tour
    {
        return Tour::create([
            'title' => 'تور کنترل نمایش',
            'slug' => 'source-display-'.str()->random(8),
            'description' => 'توضیحات',
            'is_active' => true,
        ]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'provider_name' => 'منبع تست',
            'source_url' => 'https://example.com/offer',
            'buy_url' => 'https://example.com/buy',
            'extraction_type' => 'manual',
            'price_multiplier' => 1,
            'latest_price' => 10_000_000,
            'currency' => 'تومان',
            'source_currency' => 'auto',
            'is_active' => true,
            'display_priority' => 100,
        ], $overrides);
    }
}
