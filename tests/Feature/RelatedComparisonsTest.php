<?php

namespace Tests\Feature;

use App\Models\Tour;
use App\Services\RelatedComparisons;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RelatedComparisonsTest extends TestCase
{
    use RefreshDatabase;

    public function test_foreign_tour_prioritizes_same_destination_hotel_and_stay_then_country_visa(): void
    {
        $current = $this->comparison('tour', 'استانبول', 'foreign', 'تور استانبول');
        $hotel = $this->comparison('hotel', 'استانبول', 'foreign', 'هتل استانبول');
        $stay = $this->comparison('stay', 'استانبول', 'foreign', 'اقامتگاه استانبول');
        $visa = $this->comparison('visa', 'ترکیه', 'foreign', 'ویزای ترکیه');
        $nearbyTour = $this->comparison('tour', 'آنتالیا', 'foreign', 'تور آنتالیا');
        $this->comparison('hotel', 'مشهد', 'domestic', 'هتل نامرتبط مشهد');

        $related = app(RelatedComparisons::class)->for($current);

        $this->assertSame(4, $related->count());
        $this->assertSame([$hotel->id, $stay->id, $visa->id, $nearbyTour->id], $related->pluck('id')->all());
    }

    public function test_hotel_suggests_tour_to_the_same_destination_first(): void
    {
        $hotel = $this->comparison('hotel', 'استانبول', 'foreign', 'هتل استانبول');
        $tour = $this->comparison('tour', 'استانبول', 'foreign', 'تور استانبول');
        $visa = $this->comparison('visa', 'ترکیه', 'foreign', 'ویزای ترکیه');

        $related = app(RelatedComparisons::class)->for($hotel);

        $this->assertSame($tour->id, $related->first()->id);
        $this->assertTrue($related->pluck('id')->contains($visa->id));
    }

    public function test_domestic_tour_suggests_same_destination_services_and_nearby_tours_only(): void
    {
        $current = $this->comparison('tour', 'رشت', 'domestic', 'تور رشت');
        $hotel = $this->comparison('hotel', 'رشت', 'domestic', 'هتل رشت');
        $masal = $this->comparison('tour', 'ماسال', 'domestic', 'تور ماسال');
        $lahijan = $this->comparison('tour', 'لاهیجان', 'domestic', 'تور لاهیجان');
        $unrelated = $this->comparison('tour', 'شیراز', 'domestic', 'تور شیراز');

        $related = app(RelatedComparisons::class)->for($current);

        $this->assertSame($hotel->id, $related->first()->id);
        $this->assertTrue($related->pluck('id')->contains($masal->id));
        $this->assertTrue($related->pluck('id')->contains($lahijan->id));
        $this->assertFalse($related->pluck('id')->contains($unrelated->id));
    }

    public function test_related_cards_render_before_the_price_history(): void
    {
        $current = $this->comparison('hotel', 'مشهد', 'domestic', 'هتل مشهد');
        $related = $this->comparison('tour', 'مشهد', 'domestic', 'تور مشهد');

        $this->get(route('hotels.show', $current))
            ->assertOk()
            ->assertSee($related->title)
            ->assertSee('category-badge', false)
            ->assertSeeInOrder(['مقایسه‌های مرتبط', $related->title, 'سابقه قیمت']);
    }

    private function comparison(string $category, string $destination, string $region, string $title): Tour
    {
        $tour = Tour::create([
            'category' => $category,
            'title' => $title,
            'slug' => str()->slug($category.'-'.$destination.'-'.str()->random(5)),
            'description' => 'صفحه مقایسه '.$title,
            'is_active' => true,
        ]);
        $tour->suggestions()->create([
            'category' => $category,
            'keyword' => $title,
            'suggested_title' => $title,
            'destination' => $destination,
            'status' => 'built',
            'metadata' => ['region' => $region],
        ]);
        $tour->priceSources()->create([
            'provider_name' => 'آژانس '.$tour->id,
            'source_url' => 'https://example.com/'.$tour->id,
            'latest_price' => 10_000_000 + $tour->id,
            'currency' => 'تومان',
            'is_active' => true,
        ]);

        return $tour;
    }
}
