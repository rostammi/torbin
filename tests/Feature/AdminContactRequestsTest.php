<?php

namespace Tests\Feature;

use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminContactRequestsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_see_the_submission_origin_and_mark_a_number_as_contacted(): void
    {
        $tour = Tour::create([
            'title' => 'هتل بدون قیمت',
            'slug' => 'admin-contact-hotel',
            'category' => 'hotel',
            'description' => 'توضیحات',
            'is_active' => true,
        ]);
        $token = Str::random(48);
        $contactRequest = $tour->priceAlerts()->create([
            'phone' => '09123456789',
            'phone_hash' => hash_hmac('sha256', '09123456789', config('app.key')),
            'unsubscribe_token' => $token,
            'unsubscribe_token_hash' => hash('sha256', $token),
            'target_price' => null,
            'currency' => 'تومان',
            'origin' => 'no_price_contact',
            'contact_status' => 'pending',
            'is_active' => false,
        ]);
        $admin = User::factory()->create();

        $this->actingAs($admin)
            ->get(route('admin.contact-requests.index'))
            ->assertOk()
            ->assertSee('09123456789')
            ->assertSee('هتل بدون قیمت')
            ->assertSee('درخواست تماس؛ بدون قیمت آنلاین')
            ->assertSee('در انتظار تماس');

        $this->actingAs($admin)
            ->put(route('admin.contact-requests.update', $contactRequest), [
                'contact_status' => 'contacted',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $contactRequest->refresh();
        $this->assertSame('contacted', $contactRequest->contact_status);
        $this->assertNotNull($contactRequest->contacted_at);
    }

    public function test_agency_user_cannot_access_contact_requests(): void
    {
        $agencyUser = User::factory()->create(['role' => 'agency']);

        $this->actingAs($agencyUser)
            ->get(route('admin.contact-requests.index'))
            ->assertForbidden();
    }
}
