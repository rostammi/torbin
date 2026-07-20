<?php

namespace Tests\Feature;

use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgencyBillingTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_admin_can_set_click_cost_and_adjust_credit_with_a_ledger(): void
    {
        [, $source] = $this->pricedSource();
        $agency = $source->agency;
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
        $source->agency->update(['balance' => 1]);

        $this->get(route('tours.show', $tour))
            ->assertOk()
            ->assertSee(route('outbound.click', $source), false)
            ->assertDontSee('href="https://agency.example/buy"', false);
    }

    public function test_agency_with_zero_credit_is_hidden_from_comparison(): void
    {
        [$tour, $source] = $this->pricedSource();

        $this->get(route('tours.show', $tour))
            ->assertOk()
            ->assertDontSee($source->provider_name)
            ->assertSee('هنوز قیمت معتبری برای این تور ثبت نشده است');
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
