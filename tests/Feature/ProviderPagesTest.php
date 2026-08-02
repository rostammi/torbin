<?php

namespace Tests\Feature;

use App\Models\StaticPage;
use App\Models\Tour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProviderPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_provider_page_groups_brand_variants_and_lists_all_categories(): void
    {
        $tour = $this->offering('tour', 'تور شیراز علی‌بابا', 'alibaba-shiraz', 'علی‌بابا');
        $hotel = $this->offering('hotel', 'هتل مشهد علی‌بابا', 'alibaba-mashhad-hotel', 'علی بابا هتل');
        $other = $this->offering('tour', 'تور کیش جاباما', 'jabama-kish', 'جاباما');
        $providerUrl = $tour->priceSources->first()->agency->publicUrl();
        $this->assertStringEndsWith('/providers/alibaba', $providerUrl);

        $this->get($providerUrl)
            ->assertOk()
            ->assertSee('صفحه ارائه‌دهنده')
            ->assertSee('علی بابا')
            ->assertSee($tour->title)
            ->assertSee($hotel->title)
            ->assertDontSee($other->title)
            ->assertSee('تورها')
            ->assertSee('هتل‌ها');
    }

    public function test_provider_page_can_be_filtered_by_category_and_hides_unfunded_offers(): void
    {
        $tour = $this->offering('tour', 'تور شیراز علی‌بابا', 'filtered-tour', 'علی‌بابا');
        $hotel = $this->offering('hotel', 'هتل مشهد علی‌بابا', 'filtered-hotel', 'علی بابا هتل');
        $unfunded = $this->offering('visa', 'ویزای دبی علی‌بابا', 'unfunded-visa', 'علی‌بابا ویزا');
        $unfunded->priceSources->first()->agency->update(['balance' => 0]);
        $slug = $tour->priceSources->first()->agency->providerSlug();

        $this->get(route('providers.show', [$slug, 'category' => 'hotel']))
            ->assertOk()
            ->assertSee($hotel->title)
            ->assertDontSee($tour->title)
            ->assertDontSee($unfunded->title);
    }

    public function test_provider_name_on_comparison_page_links_to_its_public_page(): void
    {
        $tour = $this->offering('tour', 'تور قشم', 'provider-link', 'علی‌بابا');
        $agency = $tour->priceSources->first()->agency;

        $this->get($tour->publicUrl())
            ->assertOk()
            ->assertSee('href="'.$agency->publicUrl().'"', false);
    }

    public function test_rendered_external_links_always_get_nofollow_and_noopener(): void
    {
        StaticPage::updateOrCreate(['slug' => 'about-us'], [
            'title' => 'درباره ما',
            'content' => '<p><a href="https://example.org/path" rel="license">منبع بیرونی</a> <a href="/faq">لینک داخلی</a></p>',
            'is_published' => true,
        ]);

        $this->get(route('pages.about'))
            ->assertOk()
            ->assertSee('rel="license nofollow noopener"', false)
            ->assertSee('<a href="/faq">لینک داخلی</a>', false);
    }

    private function offering(string $category, string $title, string $slug, string $provider): Tour
    {
        $tour = Tour::create([
            'category' => $category,
            'title' => $title,
            'slug' => $slug,
            'description' => 'مقایسه قیمت',
            'is_active' => true,
        ]);
        $tour->priceSources()->create([
            'provider_name' => $provider,
            'source_url' => 'https://example.com/'.$slug,
            'buy_url' => 'https://example.com/'.$slug,
            'extraction_type' => 'manual',
            'latest_price' => 5_000_000,
            'currency' => 'تومان',
            'is_active' => true,
        ]);

        return $tour->load('priceSources.agency');
    }
}
