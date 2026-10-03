<?php

namespace Tests\Feature;

use App\Models\ContactClick;
use App\Models\SiteSetting;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactClickTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_button_clicks_are_recorded_for_agency_and_general_numbers(): void
    {
        SiteSetting::query()->create([
            'key' => SiteSetting::CONTACT_PHONE,
            'value' => '09190000000',
        ]);
        $tour = $this->tour();
        $agencySource = $this->contactSource($tour, 'آژانس شماره‌دار');
        $agencySource->agency->update(['contact_phone' => '021-11223344', 'is_contact_only' => true]);
        $generalSource = $this->contactSource($tour, 'آژانس بدون شماره');
        $generalSource->agency->update(['contact_phone' => null, 'is_contact_only' => true]);

        $this->post(route('contact.click', $agencySource))->assertNoContent();
        $this->post(route('contact.click', $generalSource))->assertNoContent();

        $this->assertDatabaseHas('contact_clicks', [
            'price_source_id' => $agencySource->id,
            'agency_id' => $agencySource->agency_id,
            'contact_type' => ContactClick::TYPE_AGENCY,
            'phone' => '021-11223344',
        ]);
        $this->assertDatabaseHas('contact_clicks', [
            'price_source_id' => $generalSource->id,
            'agency_id' => $generalSource->agency_id,
            'contact_type' => ContactClick::TYPE_GENERAL,
            'phone' => '09190000000',
        ]);
    }

    public function test_non_contact_offer_cannot_register_a_contact_click(): void
    {
        $tour = $this->tour();
        $source = $tour->priceSources()->create([
            'provider_name' => 'آژانس لینک‌دار',
            'source_url' => 'https://example.com/offer',
            'buy_url' => 'https://example.com/buy',
            'extraction_type' => 'manual',
            'latest_price' => 8_000_000,
            'is_active' => true,
        ]);

        $this->post(route('contact.click', $source))->assertNotFound();
        $this->assertDatabaseCount('contact_clicks', 0);
    }

    public function test_dashboard_displays_contact_clicks_separately(): void
    {
        $tour = $this->tour();
        $agencySource = $this->contactSource($tour, 'آژانس شماره‌دار');
        $agencySource->agency->update(['contact_phone' => '021-11223344', 'is_contact_only' => true]);
        $generalSource = $this->contactSource($tour, 'آژانس بدون شماره');
        $generalSource->agency->update(['contact_phone' => null, 'is_contact_only' => true]);

        foreach ([[$agencySource, ContactClick::TYPE_AGENCY, '021-11223344'], [$generalSource, ContactClick::TYPE_GENERAL, '09190000000']] as [$source, $type, $phone]) {
            ContactClick::create([
                'agency_id' => $source->agency_id,
                'price_source_id' => $source->id,
                'tour_id' => $tour->id,
                'contact_type' => $type,
                'phone' => $phone,
                'clicked_at' => now(),
            ]);
        }

        $this->actingAs(User::factory()->create())
            ->get(route('admin.dashboard', ['period' => 'all']))
            ->assertOk()
            ->assertViewHas('contactClicksTotal', 2)
            ->assertViewHas('generalContactClicksTotal', 1)
            ->assertSee('کلیک «تماس بگیرید»')
            ->assertSee('آژانس شماره‌دار')
            ->assertSee('شماره اختصاصی آژانس')
            ->assertSee('شماره عمومی');
    }

    private function tour(): Tour
    {
        return Tour::create([
            'title' => 'تور تماس',
            'slug' => 'contact-click-'.str()->random(8),
            'description' => '...',
            'is_active' => true,
        ]);
    }

    private function contactSource(Tour $tour, string $provider): mixed
    {
        return $tour->priceSources()->create([
            'provider_name' => $provider,
            'source_url' => 'https://example.com/'.str()->random(8),
            'extraction_type' => 'manual',
            'latest_price' => 0,
            'is_active' => true,
        ]);
    }
}
