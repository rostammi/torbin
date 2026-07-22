<?php

namespace Tests\Feature;

use App\Models\Advertisement;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdvertisementsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_an_advertisement_with_an_image(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create();

        $this->actingAs($admin)->post(route('admin.advertisements.store'), [
            'name' => 'کمپین نوروز',
            'advertiser_name' => 'آژانس نمونه',
            'placement' => 'home_slider',
            'title' => 'تور نوروزی ویژه',
            'subtitle' => 'ظرفیت محدود',
            'image' => UploadedFile::fake()->createWithContent(
                'banner.png',
                base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='),
            ),
            'destination_url' => 'https://agency.example/tours',
            'cta_text' => 'رزرو تور',
            'priority' => 50,
            'contract_amount' => 25_000_000,
            'contract_currency' => 'تومان',
            'is_active' => '1',
        ])->assertRedirect(route('admin.advertisements.index'));

        $advertisement = Advertisement::firstOrFail();
        Storage::disk('public')->assertExists($advertisement->image_path);
        $this->assertSame('home_slider', $advertisement->placement);

        $this->actingAs($admin)->put(route('admin.advertisements.update', $advertisement), [
            'name' => 'کمپین نوروز و تابستان',
            'advertiser_name' => 'آژانس نمونه',
            'placement' => 'search_top',
            'title' => 'تور ویژه',
            'destination_url' => 'https://agency.example/tours',
            'cta_text' => 'مشاهده',
            'priority' => 60,
            'contract_currency' => 'تومان',
            'is_active' => '1',
        ])->assertRedirect();
        $this->assertDatabaseHas('advertisements', ['name' => 'کمپین نوروز و تابستان', 'placement' => 'search_top']);

        $this->actingAs($admin)->delete(route('admin.advertisements.destroy', $advertisement))->assertRedirect(route('admin.advertisements.index'));
        Storage::disk('public')->assertMissing($advertisement->image_path);
        $this->assertDatabaseEmpty('advertisements');
    }

    public function test_advertisements_render_in_all_public_placements_and_count_impressions(): void
    {
        $tour = Tour::create([
            'title' => 'تور کیش',
            'slug' => 'kish-ads',
            'description' => 'تور کیش با پرواز',
            'is_active' => true,
        ]);
        foreach (Advertisement::PLACEMENTS as $placement => $label) {
            Advertisement::create([
                'name' => "کمپین {$placement}",
                'advertiser_name' => 'آژانس تبلیغ‌دهنده',
                'placement' => $placement,
                'title' => "تبلیغ {$label}",
                'destination_url' => 'https://agency.example/offer',
                'cta_text' => 'مشاهده تور',
                'is_active' => true,
            ]);
        }

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('تبلیغ اسلایدر صفحه اصلی')
            ->assertSee('تبلیغ بنر بین کارت‌های صفحه اصلی');
        $this->get(route('search.index', ['q' => 'کیش']))
            ->assertOk()
            ->assertSee('تبلیغ بنر بالای صفحه جست‌وجو')
            ->assertSee('تبلیغ کادر داخل نتایج جست‌وجو');
        $this->get(route('tours.show', $tour))
            ->assertOk()
            ->assertSee('تبلیغ بنر بالای ترند قیمت')
            ->assertSee('تبلیغ بنر بعد از پیشنهادهای قیمت');

        $this->assertTrue(Advertisement::all()->every(fn (Advertisement $advertisement) => $advertisement->impressions > 0));
    }

    public function test_expired_ad_is_hidden_and_clicks_are_counted(): void
    {
        $expired = Advertisement::create([
            'name' => 'منقضی',
            'advertiser_name' => 'آژانس قدیمی',
            'placement' => 'search_top',
            'title' => 'این تبلیغ نباید دیده شود',
            'destination_url' => 'https://agency.example/expired',
            'cta_text' => 'مشاهده',
            'ends_at' => now()->subMinute(),
            'is_active' => true,
        ]);
        $active = Advertisement::create([
            'name' => 'فعال',
            'advertiser_name' => 'آژانس فعال',
            'placement' => 'search_top',
            'title' => 'تبلیغ فعال',
            'destination_url' => 'https://agency.example/active',
            'cta_text' => 'مشاهده',
            'is_active' => true,
        ]);

        $this->get(route('search.index'))
            ->assertOk()
            ->assertSee('تبلیغ فعال')
            ->assertDontSee('این تبلیغ نباید دیده شود');

        $this->get(route('advertisements.click', $active))->assertRedirect('https://agency.example/active');
        $this->assertSame(1, $active->fresh()->clicks);
        $this->assertSame(0, $expired->fresh()->impressions);
    }

    public function test_guest_cannot_access_advertisement_management(): void
    {
        $this->get(route('admin.advertisements.index'))->assertRedirect('/login');
    }
}
