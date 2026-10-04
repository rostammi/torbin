<?php

namespace Tests\Feature;

use App\Models\ContactClick;
use App\Models\SiteSetting;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryContactPhonesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sets_a_different_contact_phone_for_each_comparison_category(): void
    {
        $phones = ['tour' => '021-11111111', 'hotel' => '021-22222222', 'stay' => '021-33333333', 'visa' => '021-44444444'];

        $admin = User::factory()->create();
        $this->actingAs($admin)
            ->get(route('admin.agencies.index'))
            ->assertOk()
            ->assertSee('شماره تماس دسته‌بندی‌ها')
            ->assertSee('comparison_contact_phones[tour]', false)
            ->assertSee('comparison_contact_phones[hotel]', false)
            ->assertSee('comparison_contact_phones[stay]', false)
            ->assertSee('comparison_contact_phones[visa]', false);

        $this->actingAs($admin)
            ->put(route('admin.agencies.comparison-contact'), ['comparison_contact_phones' => $phones])
            ->assertRedirect()
            ->assertSessionHas('success');

        foreach ($phones as $category => $phone) {
            $this->assertSame($phone, SiteSetting::comparisonContactPhone($category));
        }
    }

    public function test_comparison_and_click_tracking_use_the_phone_of_the_tour_category(): void
    {
        SiteSetting::create(['key' => SiteSetting::contactPhoneKey('hotel'), 'value' => '021-22222222']);
        SiteSetting::create(['key' => SiteSetting::contactPhoneKey('tour'), 'value' => '021-11111111']);
        $hotel = Tour::create(['category' => 'hotel', 'title' => 'هتل تست', 'slug' => 'category-phone-hotel', 'description' => '...', 'is_active' => true]);
        $source = $hotel->priceSources()->create([
            'provider_name' => 'منبع بدون شماره', 'source_url' => 'https://example.com/hotel',
            'extraction_type' => 'manual', 'latest_price' => 0, 'is_active' => true,
        ]);

        $this->get($hotel->publicUrl())
            ->assertOk()
            ->assertSee('021-22222222')
            ->assertDontSee('021-11111111');
        $this->post(route('contact.click', $source))->assertNoContent();
        $this->assertDatabaseHas('contact_clicks', [
            'price_source_id' => $source->id,
            'contact_type' => ContactClick::TYPE_GENERAL,
            'phone' => '021-22222222',
        ]);
    }
}
