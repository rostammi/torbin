<?php

namespace Tests\Feature;

use App\Models\Tour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_suggestions_start_at_three_characters_and_return_at_most_four_items(): void
    {
        foreach (range(1, 6) as $index) {
            $this->tour("تور تست {$index}", "test-{$index}", 'توضیحات سفر');
        }

        $this->getJson(route('search.suggestions', ['q' => 'تو']))
            ->assertOk()
            ->assertJsonCount(0, 'items');

        $response = $this->getJson(route('search.suggestions', ['q' => 'تور']))
            ->assertOk()
            ->assertJsonCount(4, 'items')
            ->assertJsonPath('total', 6);

        $this->assertStringContainsString('/search?q=', $response->json('all_url'));
    }

    public function test_search_matches_tour_title_description_and_funded_agency_name(): void
    {
        $byTitle = $this->tour('تور شیراز ویژه', 'shiraz-special', 'سفر معمولی');
        $byDescription = $this->tour('سفر جنوب', 'south', 'تماشای بهارنارنج در فصل بهار');
        $byAgency = $this->tour('سفر تابستانی', 'summer', 'توضیحات عمومی', 'پرواز نوین');
        $hiddenAgency = $this->tour('سفر زمستانی', 'winter', 'توضیحات عمومی', 'آژانس بدون اعتبار', 0);
        Tour::create(['title' => 'تور غیرفعال شیراز', 'slug' => 'inactive', 'description' => 'بهارنارنج', 'is_active' => false]);

        $this->get(route('search.index', ['q' => 'شیراز']))
            ->assertOk()->assertSee($byTitle->title)->assertDontSee('تور غیرفعال شیراز');
        $this->get(route('search.index', ['q' => 'بهارنارنج']))
            ->assertOk()->assertSee($byDescription->title)->assertDontSee('تور غیرفعال شیراز');
        $this->get(route('search.index', ['q' => 'پرواز نوین']))
            ->assertOk()->assertSee($byAgency->title);
        $this->get(route('search.index', ['q' => 'آژانس بدون اعتبار']))
            ->assertOk()->assertDontSee($hiddenAgency->title)->assertSee('نتیجه‌ای پیدا نشد');
    }

    public function test_header_contains_global_search_box(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('id="site-search"', false)
            ->assertSee(route('search.suggestions'), false);
    }

    private function tour(string $title, string $slug, string $description, string $agency = 'آژانس نمونه', int $balance = 100_000): Tour
    {
        $tour = Tour::create([
            'title' => $title,
            'slug' => $slug,
            'description' => $description,
            'is_active' => true,
        ]);
        $source = $tour->priceSources()->create([
            'provider_name' => $agency,
            'source_url' => 'https://example.com/'.$slug,
            'buy_url' => 'https://example.com/'.$slug,
            'extraction_type' => 'manual',
            'latest_price' => 8_000_000,
            'currency' => 'تومان',
            'is_active' => true,
        ]);
        $source->agency->update(['balance' => $balance]);

        return $tour;
    }
}
