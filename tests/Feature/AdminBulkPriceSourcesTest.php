<?php

namespace Tests\Feature;

use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminBulkPriceSourcesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_add_a_source_to_every_comparison_in_a_category_without_duplicates(): void
    {
        $firstTour = $this->tour('tour', 'تور شیراز');
        $secondTour = $this->tour('tour', 'تور کیش');
        $hotel = $this->tour('hotel', 'هتل شیراز');
        $firstTour->priceSources()->create($this->source(['source_url' => 'https://example.com/existing']));

        $this->actingAs(User::factory()->create())
            ->post(route('admin.sources.bulk.store'), $this->source([
                'target_mode' => 'category',
                'target_category' => 'tour',
            ]))
            ->assertRedirect(route('admin.tours.index'))
            ->assertSessionHas('success', fn ($message) => str_contains($message, '1 صفحهٔ مقایسه اضافه شد')
                && str_contains($message, '1 صفحه به‌دلیل وجود قبلی'));

        $this->assertSame(1, $firstTour->priceSources()->where('provider_name', 'منبع گروهی')->count());
        $this->assertSame(1, $secondTour->priceSources()->where('provider_name', 'منبع گروهی')->count());
        $this->assertSame(0, $hotel->priceSources()->where('provider_name', 'منبع گروهی')->count());
        $this->assertSame('https://example.com/existing', $firstTour->priceSources()->sole()->source_url);
    }

    public function test_admin_can_add_a_source_only_to_selected_comparisons(): void
    {
        $tour = $this->tour('tour', 'تور منتخب');
        $hotel = $this->tour('hotel', 'هتل منتخب');
        $unselected = $this->tour('visa', 'ویزای انتخاب‌نشده');

        $this->actingAs(User::factory()->create())
            ->get(route('admin.sources.bulk.create'))
            ->assertOk()
            ->assertSee('تور منتخب')
            ->assertSee('هتل منتخب');

        $this->post(route('admin.sources.bulk.store'), $this->source([
            'target_mode' => 'selected',
            'tour_ids' => [$tour->id, $hotel->id],
        ]))->assertRedirect(route('admin.tours.index'));

        $this->assertDatabaseHas('price_sources', ['tour_id' => $tour->id, 'provider_name' => 'منبع گروهی']);
        $this->assertDatabaseHas('price_sources', ['tour_id' => $hotel->id, 'provider_name' => 'منبع گروهی']);
        $this->assertDatabaseMissing('price_sources', ['tour_id' => $unselected->id, 'provider_name' => 'منبع گروهی']);
    }

    public function test_bulk_source_requires_at_least_one_matching_comparison(): void
    {
        $this->actingAs(User::factory()->create())
            ->from(route('admin.sources.bulk.create'))
            ->post(route('admin.sources.bulk.store'), $this->source([
                'target_mode' => 'category',
                'target_category' => 'visa',
            ]))
            ->assertRedirect(route('admin.sources.bulk.create'))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('price_sources', 0);
    }

    private function tour(string $category, string $title): Tour
    {
        return Tour::create([
            'category' => $category,
            'title' => $title,
            'slug' => str()->slug($title).'-'.str()->random(5),
            'description' => 'توضیحات',
            'is_active' => true,
        ]);
    }

    private function source(array $overrides = []): array
    {
        return array_merge([
            'provider_name' => 'منبع گروهی',
            'source_url' => 'https://example.com/offers',
            'buy_url' => '',
            'extraction_type' => 'structured',
            'selector' => null,
            'price_multiplier' => 1,
            'latest_price' => null,
            'currency' => 'تومان',
            'source_currency' => 'auto',
            'is_active' => '1',
        ], $overrides);
    }
}
