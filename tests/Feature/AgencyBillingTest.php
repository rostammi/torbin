<?php

namespace Tests\Feature;

use App\Jobs\RecoverPriceSourceLink;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AgencyBillingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake(['https://agency.example/*' => Http::response('', 200)]);
    }

    public function test_buy_click_is_counted_charged_and_redirected(): void
    {
        [$tour, $source] = $this->pricedSource();
        $source->agency->update(['balance' => 10_000, 'cost_per_click' => 2_000]);

        $this->get(route('outbound.click', $source))
            ->assertRedirect('https://agency.example/buy');

        $this->assertSame(8_000, $source->agency->fresh()->balance);
        $this->assertDatabaseHas('outbound_clicks', [
            'agency_id' => $source->agency_id,
            'price_source_id' => $source->id,
            'tour_id' => $tour->id,
            'charged_amount' => 2_000,
            'status' => 'charged',
        ]);
        $this->assertDatabaseHas('agency_credit_transactions', [
            'agency_id' => $source->agency_id,
            'amount' => -2_000,
            'balance_after' => 8_000,
            'type' => 'click_charge',
        ]);
    }

    public function test_click_is_counted_but_not_redirected_when_credit_is_insufficient(): void
    {
        [$tour, $source] = $this->pricedSource();
        $source->agency->update(['balance' => 1_000, 'cost_per_click' => 2_000]);

        $this->get(route('outbound.click', $source))
            ->assertRedirect(route('tours.show', $tour))
            ->assertSessionHas('error');

        $this->assertSame(1_000, $source->agency->fresh()->balance);
        $this->assertDatabaseHas('outbound_clicks', [
            'agency_id' => $source->agency_id,
            'charged_amount' => 0,
            'status' => 'insufficient_credit',
        ]);
    }

    public function test_broken_destination_is_hidden_recovery_is_queued_and_user_returns_to_tour_without_charge(): void
    {
        Queue::fake();
        [$tour, $source] = $this->pricedSource();
        $source->update([
            'source_url' => 'https://93.184.216.34/tours',
            'buy_url' => 'https://93.184.216.34/dead-offer',
        ]);
        $source->agency->update(['balance' => 10_000, 'cost_per_click' => 2_000]);
        Http::fake(['https://93.184.216.34/*' => Http::response('', 404)]);

        $this->get(route('outbound.click', $source))
            ->assertRedirect($tour->publicUrl())
            ->assertSessionHas('error', fn (string $message) => str_contains($message, 'یکی از لینک‌های دیگر'));

        $source->refresh();
        $this->assertFalse($source->is_active);
        $this->assertSame('broken_link', $source->last_status);
        $this->assertSame(['https://93.184.216.34/dead-offer'], $source->rejected_urls);
        $this->assertSame(10_000, $source->agency->fresh()->balance);
        $this->assertDatabaseCount('outbound_clicks', 0);
        Queue::assertPushed(RecoverPriceSourceLink::class, fn ($job) => $job->sourceId === $source->id);

        $this->get($tour->publicUrl())
            ->assertOk()
            ->assertDontSee($source->provider_name);
    }

    public function test_redirect_to_a_broken_page_is_not_accepted_as_a_valid_destination(): void
    {
        Queue::fake();
        [$tour, $source] = $this->pricedSource();
        $source->update([
            'source_url' => 'https://93.184.216.34/tours',
            'buy_url' => 'https://93.184.216.34/redirecting-offer',
        ]);
        Http::fake(function ($request) {
            return str_contains($request->url(), 'redirecting-offer')
                ? Http::response('', 302, ['Location' => '/gone-offer'])
                : Http::response('', 404);
        });

        $this->get(route('outbound.click', $source))
            ->assertRedirect($tour->publicUrl())
            ->assertSessionHas('error');

        $this->assertFalse($source->fresh()->is_active);
        $this->assertContains(
            'https://93.184.216.34/redirecting-offer',
            $source->fresh()->rejected_urls,
        );
        Queue::assertPushed(RecoverPriceSourceLink::class);
    }

    public function test_admin_can_set_click_cost_and_adjust_credit_with_a_ledger(): void
    {
        [, $source] = $this->pricedSource();
        $agency = $source->agency;
        $agency->update(['balance' => 0]);
        $admin = User::factory()->create();

        $this->actingAs($admin)->put(route('admin.agencies.update', $agency), [
            'cost_per_click' => 1_500,
        ])->assertRedirect();
        $this->assertSame(1_500, $agency->fresh()->cost_per_click);

        $this->post(route('admin.agencies.balance', $agency), [
            'type' => 'credit', 'amount' => 20_000, 'note' => 'شارژ اولیه',
        ])->assertRedirect();
        $this->post(route('admin.agencies.balance', $agency), [
            'type' => 'debit', 'amount' => 3_000, 'note' => 'اصلاح حساب',
        ])->assertRedirect();

        $this->assertSame(17_000, $agency->fresh()->balance);
        $this->assertDatabaseHas('agency_credit_transactions', [
            'agency_id' => $agency->id, 'user_id' => $admin->id, 'amount' => 20_000, 'type' => 'manual_credit',
        ]);
        $this->assertDatabaseHas('agency_credit_transactions', [
            'agency_id' => $agency->id, 'user_id' => $admin->id, 'amount' => -3_000, 'type' => 'manual_debit',
        ]);
        $this->get(route('admin.agencies.index'))
            ->assertOk()
            ->assertSee('آژانس نمونه')
            ->assertSee('17,000');
    }

    public function test_public_page_uses_internal_tracking_link(): void
    {
        [$tour, $source] = $this->pricedSource();
        $source->agency->update(['balance' => 1_000]);

        $this->get(route('tours.show', $tour))
            ->assertOk()
            ->assertSee(route('outbound.click', $source), false)
            ->assertDontSee('href="https://agency.example/buy"', false);
    }

    public function test_agency_with_zero_credit_is_hidden_from_comparison(): void
    {
        [$tour, $source] = $this->pricedSource();
        $source->agency->update(['balance' => 0, 'cost_per_click' => 2_000]);

        $this->get(route('tours.show', $tour))
            ->assertOk()
            ->assertDontSee($source->provider_name)
            ->assertSee('هنوز قیمت معتبری برای این تور ثبت نشده است');
    }

    public function test_free_source_with_zero_credit_is_also_hidden_from_comparison(): void
    {
        [$tour, $source] = $this->pricedSource();
        $source->agency->update(['balance' => 0, 'cost_per_click' => 0]);

        $this->get(route('tours.show', $tour))
            ->assertOk()
            ->assertDontSee($source->provider_name)
            ->assertSee('هنوز قیمت معتبری برای این تور ثبت نشده است');
    }

    public function test_new_agency_gets_default_credit_and_click_cost(): void
    {
        [, $source] = $this->pricedSource();

        $this->assertSame(1_000_000, $source->agency->balance);
        $this->assertSame(1_000, $source->agency->cost_per_click);
    }

    private function pricedSource(): array
    {
        $tour = Tour::create([
            'title' => 'تور شیراز', 'slug' => 'billing-shiraz', 'description' => '...', 'is_active' => true,
        ]);
        $source = $tour->priceSources()->create([
            'provider_name' => 'آژانس نمونه',
            'source_url' => 'https://agency.example/tour',
            'buy_url' => 'https://agency.example/buy',
            'extraction_type' => 'manual',
            'latest_price' => 8_000_000,
            'currency' => 'تومان',
            'is_active' => true,
        ]);

        return [$tour, $source->fresh('agency')];
    }
}
