<?php

namespace Tests\Feature;

use App\Models\SearchMiss;
use App\Models\Tour;
use App\Models\User;
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

    public function test_searches_without_results_are_grouped_as_potential_keywords_for_admin(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->get(route('search.index', ['q' => 'تور مریخ']))->assertOk();
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.11'])
            ->get(route('search.index', ['q' => 'تور‌مریخ']))->assertOk();

        $this->assertSame(2, SearchMiss::count());

        $this->actingAs(User::factory()->create())
            ->get(route('admin.dashboard', ['period' => 'all']))
            ->assertOk()
            ->assertSee('کیوردهای دارای پتانسیل اجرای تور')
            ->assertSee('تور مریخ')
            ->assertSeeInOrder(['تور مریخ', '2', '2']);
    }

    public function test_successful_search_is_not_recorded_as_a_miss(): void
    {
        $this->tour('تور شیراز', 'successful-shiraz', 'توضیحات');

        $this->get(route('search.index', ['q' => 'شیراز']))->assertOk();

        $this->assertDatabaseCount('search_misses', 0);
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
