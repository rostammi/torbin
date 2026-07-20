<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\OutboundClick;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgencyDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_agency_sees_its_own_tour_metrics_and_not_other_agencies_data(): void
    {
        [$agency, $tour, $source] = $this->agencyTour('آژانس اول', 'شیراز');
        [$otherAgency, $otherTour, $otherSource] = $this->agencyTour('آژانس دوم', 'کیش');
        $user = User::factory()->create(['role' => 'agency', 'agency_id' => $agency->id]);

        foreach (range(1, 20) as $index) {
            $tour->pageViews()->create(['viewed_at' => now()]);
        }
        foreach (range(1, 5) as $index) {
            $this->click($agency, $tour, $source, 1_000);
        }
        foreach (range(1, 3) as $index) {
            $this->click($otherAgency, $otherTour, $otherSource, 2_000);
        }

        $this->actingAs($user)->get(route('admin.dashboard', ['period' => 'all']))
            ->assertOk()
            ->assertSee('داشبورد آژانس اول')
            ->assertSee('تور شیراز')
            ->assertDontSee('تور کیش')
            ->assertSee('25.00٪')
            ->assertSee('5,000');
    }

    public function test_agency_cannot_access_management_routes(): void
    {
        [$agency] = $this->agencyTour('آژانس اول', 'شیراز');
        $user = User::factory()->create(['role' => 'agency', 'agency_id' => $agency->id]);

        $this->actingAs($user)->get(route('admin.tours.index'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.agencies.index'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.dashboard'))->assertOk();
    }

    public function test_agency_login_redirects_to_shared_dashboard(): void
    {
        [$agency] = $this->agencyTour('آژانس اول', 'شیراز');
        User::factory()->create([
            'email' => 'agency-login@example.com',
            'password' => 'password',
            'role' => 'agency',
            'agency_id' => $agency->id,
        ]);

        $this->post(route('login.store'), [
            'email' => 'agency-login@example.com',
            'password' => 'password',
        ])->assertRedirect(route('admin.dashboard'));
    }

    public function test_admin_can_create_an_agency_login_account(): void
    {
        [$agency] = $this->agencyTour('آژانس اول', 'شیراز');

        $this->actingAs(User::factory()->create())
            ->post(route('admin.agencies.access', $agency), [
                'email' => 'agency@example.com',
                'password' => 'strong-password',
                'password_confirmation' => 'strong-password',
            ])->assertRedirect();

        $this->assertDatabaseHas('users', [
            'email' => 'agency@example.com', 'role' => 'agency', 'agency_id' => $agency->id,
        ]);
    }

    public function test_opening_a_public_tour_page_records_a_view(): void
    {
        [, $tour] = $this->agencyTour('آژانس اول', 'شیراز');

        $this->get(route('tours.show', $tour))->assertOk();

        $this->assertDatabaseHas('tour_page_views', ['tour_id' => $tour->id]);
    }

    private function agencyTour(string $agencyName, string $destination): array
    {
        $tour = Tour::create([
            'title' => "تور {$destination}",
            'slug' => 'dashboard-'.str()->random(10),
            'description' => '...',
            'is_active' => true,
        ]);
        $source = $tour->priceSources()->create([
            'provider_name' => $agencyName,
            'source_url' => 'https://example.com/tour',
            'buy_url' => 'https://example.com/buy',
            'extraction_type' => 'manual',
            'latest_price' => 8_000_000,
            'is_active' => true,
        ]);
        $source->agency->update(['balance' => 100_000, 'cost_per_click' => 1_000]);

        return [$source->agency->fresh(), $tour, $source];
    }

    private function click(Agency $agency, Tour $tour, $source, int $cost): void
    {
        OutboundClick::create([
            'agency_id' => $agency->id,
            'price_source_id' => $source->id,
            'tour_id' => $tour->id,
            'charged_amount' => $cost,
            'currency' => 'تومان',
            'status' => 'charged',
            'destination_url' => 'https://example.com/buy',
            'clicked_at' => now(),
        ]);
    }
}
