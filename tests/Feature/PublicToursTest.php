<?php

namespace Tests\Feature;

use App\Models\Tour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicToursTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_lists_only_active_tours_with_the_cheapest_price(): void
    {
        $tour = Tour::create([
            'title' => 'تور شیراز', 'slug' => 'shiraz', 'description' => 'توضیحات', 'is_active' => true,
        ]);
        $tour->priceSources()->createMany([
            ['provider_name' => 'گران', 'source_url' => 'https://example.com/a', 'extraction_type' => 'manual', 'latest_price' => 9000000],
            ['provider_name' => 'ارزان', 'source_url' => 'https://example.com/b', 'extraction_type' => 'manual', 'latest_price' => 7000000],
        ]);
        $tour->priceSources->each(fn ($source) => $source->agency->update(['balance' => 100_000]));
        Tour::create(['title' => 'پیش‌نویس', 'slug' => 'draft', 'description' => '...', 'is_active' => false]);

        $this->get('/')->assertOk()->assertSee('تور شیراز')->assertSee('7,000,000')->assertDontSee('پیش‌نویس');
    }

    public function test_home_uses_compact_persian_pagination(): void
    {
        foreach (range(1, 13) as $number) {
            Tour::create([
                'title' => "تور صفحه اصلی {$number}",
                'slug' => "home-tour-{$number}",
                'description' => 'توضیحات تور',
                'is_active' => true,
            ]);
        }

        $response = $this->get(route('home'))
            ->assertOk()
            ->assertSee('نمایش 1 تا 12 از 13 نتیجه')
            ->assertSee('قبلی')
            ->assertSee('بعدی')
            ->assertDontSee('pagination.previous')
            ->assertDontSee('pagination.next');

        $response->assertDontSee('<svg', false);
    }

    public function test_tour_page_sorts_offers_by_price(): void
    {
        $tour = Tour::create([
            'title' => 'تور کیش', 'slug' => 'kish', 'description' => 'توضیحات', 'is_active' => true,
        ]);
        $tour->priceSources()->createMany([
            ['provider_name' => 'فروشنده دوم', 'source_url' => 'https://example.com/a', 'extraction_type' => 'manual', 'latest_price' => 200, 'is_featured' => true],
            ['provider_name' => 'فروشنده اول', 'source_url' => 'https://example.com/b', 'extraction_type' => 'manual', 'latest_price' => 100],
            ['provider_name' => 'فروشنده بدون قیمت', 'source_url' => 'https://example.com/c', 'extraction_type' => 'manual', 'latest_price' => 0],
        ]);
        $tour->priceSources->each(fn ($source) => $source->agency->update(['balance' => 100_000]));

        $response = $this->get('/tours/kish')->assertOk();
        $response->assertSeeInOrder(['فروشنده اول', 'فروشنده دوم']);
        $response->assertDontSee('فروشنده بدون قیمت');
        $response->assertSee('2 پیشنهاد دارای قیمت');
        $response->assertSee('پیشنهاد ویژه');
    }

    public function test_tour_page_displays_rating_and_price_history(): void
    {
        $tour = Tour::create([
            'title' => 'تور شیراز', 'slug' => 'shiraz-history', 'description' => 'توضیحات', 'is_active' => true,
        ]);
        $source = $tour->priceSources()->create([
            'provider_name' => 'سفرمارکت',
            'source_url' => 'https://example.com/shiraz',
            'extraction_type' => 'manual',
            'latest_price' => 8_500_000,
            'latest_rating' => 4.6,
            'latest_rating_count' => 128,
            'rating_type' => 'user_rating',
        ]);
        $source->agency->update(['balance' => 100_000]);
        $source->history()->createMany([
            ['price' => 9_000_000, 'rating' => 4.5, 'is_available' => true, 'observed_at' => now()->subDay()],
            ['price' => 8_500_000, 'rating' => 4.6, 'is_available' => true, 'observed_at' => now()],
        ]);

        $this->get('/tours/shiraz-history')
            ->assertOk()
            ->assertSee('سابقه قیمت این تور')
            ->assertSee('4.6')
            ->assertSee('128')
            ->assertSee('9,000,000')
            ->assertSee('8,500,000');
    }

    public function test_tour_page_has_one_trend_using_the_daily_minimum_across_agencies(): void
    {
        $tour = Tour::create([
            'title' => 'تور قشم', 'slug' => 'qeshm-trend', 'description' => 'توضیحات', 'is_active' => true,
        ]);
        $first = $tour->priceSources()->create([
            'provider_name' => 'آژانس اول', 'source_url' => 'https://example.com/one',
            'extraction_type' => 'manual', 'latest_price' => 7_000_000, 'is_active' => true,
        ]);
        $second = $tour->priceSources()->create([
            'provider_name' => 'آژانس دوم', 'source_url' => 'https://example.com/two',
            'extraction_type' => 'manual', 'latest_price' => 9_000_000, 'is_active' => true,
        ]);
        $first->agency->update(['balance' => 100_000]);
        $second->agency->update(['balance' => 100_000]);
        $first->history()->createMany([
            ['price' => 10_000_000, 'is_available' => true, 'observed_at' => now()->subDay()],
            ['price' => 7_000_000, 'is_available' => true, 'observed_at' => now()],
        ]);
        $second->history()->createMany([
            ['price' => 8_000_000, 'is_available' => true, 'observed_at' => now()->subDay()],
            ['price' => 9_000_000, 'is_available' => true, 'observed_at' => now()],
        ]);

        $this->get('/tours/qeshm-trend')
            ->assertOk()
            ->assertViewHas('priceTrend', fn ($trend) => $trend->pluck('price')->all() === [8_000_000, 7_000_000])
            ->assertSee('روند کمترین قیمت تور')
            ->assertSee('قیمت (تومان)')
            ->assertSee('تاریخ')
            ->assertSee('آژانس دوم')
            ->assertSee('آژانس اول');
    }
}
