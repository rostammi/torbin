<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_navigation_is_grouped_without_hiding_management_links(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('مشاهده سایت')
            ->assertSee('مدیریت مقایسه')
            ->assertSee('عملیات و پیگیری')
            ->assertSee('محتوا و درآمد')
            ->assertSee(route('admin.tours.index'), false)
            ->assertSee(route('admin.comparison-sources.index'), false)
            ->assertSee(route('admin.sync.index'), false)
            ->assertSee(route('admin.contact-requests.index'), false)
            ->assertSee(route('admin.advertisements.index'), false)
            ->assertSee(route('admin.static-pages.index'), false);

        $this->assertSame(4, substr_count($response->getContent(), '<details class="nav-menu'));
    }

    public function test_agency_navigation_remains_direct_and_has_no_admin_groups(): void
    {
        $agency = Agency::create(['name' => 'آژانس آزمایشی منو']);

        $response = $this->actingAs(User::factory()->create([
            'role' => 'agency',
            'agency_id' => $agency->id,
        ]))
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('تورها')
            ->assertSee('هتل‌ها')
            ->assertSee('اقامتگاه‌ها')
            ->assertSee('داشبورد')
            ->assertDontSee('مدیریت مقایسه')
            ->assertDontSee('مرکز همگام‌سازی');

        $this->assertStringNotContainsString('<details class="nav-menu', $response->getContent());
    }
}
