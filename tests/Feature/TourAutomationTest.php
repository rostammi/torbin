<?php

namespace Tests\Feature;

use App\Jobs\ProvisionAllSuggestedTours;
use App\Models\SyncRun;
use App\Models\Tour;
use App\Models\TourSuggestion;
use App\Models\User;
use App\Services\Discovery\PopularTourDiscovery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TourAutomationTest extends TestCase
{
    use RefreshDatabase;

    public function test_discovery_builds_at_least_one_hundred_catalog_suggestions_for_each_region(): void
    {
        Http::fake();

        $result = app(PopularTourDiscovery::class)->discover(120);

        $this->assertSame(240, $result['total']);
        $this->assertSame(120, $result['domestic_total']);
        $this->assertSame(120, $result['foreign_total']);
        $this->assertDatabaseHas('tour_suggestions', [
            'keyword' => 'تور کیش',
            'source' => 'destination_catalog',
        ]);
        $this->assertSame(120, TourSuggestion::where('metadata->region', 'domestic')->count());
        $this->assertSame(120, TourSuggestion::where('metadata->region', 'foreign')->count());
        $this->assertSame(0, TourSuggestion::where('source', '!=', 'destination_catalog')->count());
        Http::assertNothingSent();
    }

    public function test_suggestion_page_has_domestic_and_foreign_tabs_with_compact_pagination(): void
    {
        app(PopularTourDiscovery::class)->discover(100);
        $admin = User::factory()->create();

        $domestic = $this->actingAs($admin)->get(route('admin.suggestions.index'))
            ->assertOk()
            ->assertSee('مقصدهای داخلی')
            ->assertSee('مقصدهای خارجی')
            ->assertSee('100 پیشنهاد')
            ->assertSee('ساخت/به‌روزرسانی همه')
            ->assertSee('قبلی')
            ->assertSee('بعدی')
            ->assertDontSee('pagination.previous')
            ->assertDontSee('pagination.next')
            ->assertViewHas('region', 'domestic')
            ->assertViewHas('suggestions', fn ($suggestions) => $suggestions->every(
                fn (TourSuggestion $suggestion) => data_get($suggestion->metadata, 'region') === 'domestic'
            ));
        $domestic->assertDontSee('<svg', false);

        $this->actingAs($admin)->get(route('admin.suggestions.index', ['region' => 'foreign']))
            ->assertOk()
            ->assertViewHas('region', 'foreign')
            ->assertViewHas('suggestions', fn ($suggestions) => $suggestions->every(
                fn (TourSuggestion $suggestion) => data_get($suggestion->metadata, 'region') === 'foreign'
            ));
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

    public function test_build_all_creates_new_tours_and_only_updates_existing_tours(): void
    {
        config()->set('crawler.providers', collect(range(1, 4))->map(fn ($number) => [
            'name' => "ارائه‌دهنده {$number}",
            'type' => 'structured',
            'url' => 'https://93.184.216.34/tours',
        ])->all());
        Http::fake(['93.184.216.34/*' => Http::response(<<<'HTML'
            <html><head><script type="application/ld+json">
            {"@type":"Product","name":"تور مقصد","offers":{"@type":"Offer","price":"14900000","url":"https://93.184.216.34/buy"},"aggregateRating":{"@type":"AggregateRating","ratingValue":4.8,"ratingCount":120}}
            </script></head><body><main><h2>راهنمای کامل سفر</h2><p>برنامه سفر و خدمات تور</p></main></body></html>
            HTML)]);

        $existingTour = Tour::create([
            'title' => 'عنوان ویرایش‌شده تور موجود',
            'slug' => 'existing-tour',
            'description' => 'محتوای دستی که نباید بازنویسی شود.',
            'is_active' => true,
        ]);
        $existingSuggestion = TourSuggestion::create([
            'keyword' => 'تور کیش',
            'suggested_title' => 'تور کیش | مقایسه قیمت',
            'destination' => 'کیش',
            'trend_score' => 90,
            'source' => 'destination_catalog',
            'status' => 'created',
            'tour_id' => $existingTour->id,
            'metadata' => ['region' => 'domestic'],
        ]);
        $newSuggestion = TourSuggestion::create([
            'keyword' => 'تور استانبول',
            'suggested_title' => 'تور استانبول | مقایسه قیمت',
            'destination' => 'استانبول',
            'trend_score' => 88,
            'source' => 'destination_catalog',
            'status' => 'pending',
            'metadata' => ['region' => 'foreign'],
        ]);

        $this->actingAs(User::factory()->create())
            ->post(route('admin.suggestions.store-all'))
            ->assertRedirect()
            ->assertSessionHas('success');

        $run = SyncRun::where('type', 'provision_all_tours')->sole();
        $this->assertSame('success', $run->status);
        $this->assertSame(2, $run->total);
        $this->assertSame(2, $run->successful);
        $this->assertSame(1, $run->details['created']);
        $this->assertSame(1, $run->details['updated']);
        $this->assertSame(2, Tour::count());
        $this->assertSame($existingTour->id, $existingSuggestion->fresh()->tour_id);
        $this->assertSame('عنوان ویرایش‌شده تور موجود', $existingTour->fresh()->title);
        $this->assertNotNull($newSuggestion->fresh()->tour_id);

        foreach ([$existingSuggestion->fresh()->tour, $newSuggestion->fresh()->tour] as $tour) {
            $this->assertSame(4, $tour->priceSources()->count());
            $this->assertSame(4, $tour->priceSources()->where('latest_price', 14_900_000)->count());
            $this->assertSame(4.8, $tour->priceSources()->first()->latest_rating);
            $this->assertNotNull($tour->priceSources()->first()->content_checked_at);
        }
    }

    public function test_build_all_does_not_queue_a_second_running_job(): void
    {
        Queue::fake();
        TourSuggestion::create([
            'keyword' => 'تور شیراز',
            'suggested_title' => 'تور شیراز | مقایسه قیمت',
            'destination' => 'شیراز',
            'trend_score' => 80,
            'source' => 'destination_catalog',
            'status' => 'pending',
            'metadata' => ['region' => 'domestic'],
        ]);
        SyncRun::create([
            'type' => 'provision_all_tours',
            'status' => 'running',
            'total' => 1,
            'started_at' => now(),
        ]);

        $this->actingAs(User::factory()->create())
            ->post(route('admin.suggestions.store-all'))
            ->assertRedirect()
            ->assertSessionHas('error');

        Queue::assertNotPushed(ProvisionAllSuggestedTours::class);
        $this->assertSame(1, SyncRun::where('type', 'provision_all_tours')->count());
    }
}
