<?php

namespace Tests\Feature;

use App\Models\PriceSource;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminFeaturedOffersTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_feature_all_offers_from_one_agency(): void
    {
        $firstTour = $this->tour('shiraz');
        $secondTour = $this->tour('kish');
        $alibabaOne = $this->source($firstTour, 'علی‌بابا');
        $alibabaTwo = $this->source($secondTour, 'علی‌بابا');
        $otherAgency = $this->source($firstTour, 'سفرمارکت');

        $this->actingAs(User::factory()->create())
            ->put(route('admin.agencies.featured'), [
                'provider_name' => 'علی‌بابا',
                'is_featured' => true,
            ])
            ->assertRedirect();

        $this->assertTrue($alibabaOne->fresh()->is_featured);
        $this->assertTrue($alibabaTwo->fresh()->is_featured);
        $this->assertFalse($otherAgency->fresh()->is_featured);
    }

    public function test_shared_feature_setting_is_changed_on_the_agency(): void
    {
        $tour = $this->tour('shiraz');
        $source = $this->source($tour, 'علی‌بابا');

        $agency = $source->agency;
        $this->actingAs(User::factory()->create())
            ->put(route('admin.agencies.update', $agency), [
                'name' => $agency->name,
                'cost_per_click' => $agency->cost_per_click,
                'contact_priority' => $agency->contact_priority,
                'display_priority' => $agency->display_priority,
                'is_featured' => true,
            ])
            ->assertRedirect();

        $this->assertTrue($source->fresh()->is_featured);
    }

    public function test_bulk_unfeature_does_not_remove_featured_badge_from_a_pinned_source(): void
    {
        $tour = $this->tour('pinned');
        $source = $this->source($tour, 'علی‌بابا');
        $source->update(['is_pinned' => true]);

        $this->actingAs(User::factory()->create())
            ->put(route('admin.agencies.featured'), [
                'provider_name' => 'علی‌بابا',
                'is_featured' => false,
            ])
            ->assertRedirect();

        $this->assertTrue($source->fresh()->is_featured);
    }

    private function tour(string $slug): Tour
    {
        return Tour::create([
            'title' => "تور {$slug}",
            'slug' => $slug,
            'description' => 'توضیحات',
            'is_active' => true,
        ]);
    }

    private function source(Tour $tour, string $provider): PriceSource
    {
        return $tour->priceSources()->create([
            'provider_name' => $provider,
            'source_url' => 'https://example.com/tour',
            'buy_url' => 'https://example.com/tour',
            'extraction_type' => 'manual',
            'latest_price' => 10_000_000,
            'is_active' => true,
        ]);
    }
}
