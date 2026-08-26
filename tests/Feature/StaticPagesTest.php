<?php

namespace Tests\Feature;

use App\Models\StaticPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaticPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_static_pages_are_public_and_linked_from_footer(): void
    {
        $this->assertSame(url('/about-us').'/', StaticPage::where('slug', 'about-us')->sole()->publicUrl());
        $this->assertSame(url('/contact-us').'/', StaticPage::where('slug', 'contact-us')->sole()->publicUrl());
        $this->assertSame(url('/faq').'/', StaticPage::where('slug', 'faq')->sole()->publicUrl());

        $this->get(route('pages.about'))
            ->assertOk()
            ->assertSee('گیت فروشگاه اینترنتی نیست')
            ->assertSee('مأموریت ما');
        $this->get(route('pages.contact'))
            ->assertOk()
            ->assertSee('۰۹۱۹۹۰۱۰۲۱۶')
            ->assertSee('info@geyt.ir');
        $this->get(route('pages.faq'))
            ->assertOk()
            ->assertSee('آیا استفاده از گیت هزینه دارد؟')
            ->assertSee('"@type":"FAQPage"', false)
            ->assertSee('"@type":"Question"', false)
            ->assertSee('"@type":"Answer"', false);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee(route('pages.about'), false)
            ->assertSee(route('pages.contact'), false)
            ->assertSee(route('pages.faq'), false);
    }

    public function test_admin_can_edit_and_unpublish_a_static_page(): void
    {
        $page = StaticPage::where('slug', 'about-us')->sole();
        $admin = User::factory()->create();

        $this->actingAs($admin)
            ->put(route('admin.static-pages.update', $page), [
                'title' => 'درباره گیت',
                'content' => '<h2>داستان تازه گیت</h2><p>متن قابل ویرایش</p>',
            ])
            ->assertRedirect(route('admin.static-pages.edit', $page));

        $this->assertDatabaseHas('static_pages', [
            'slug' => 'about-us',
            'title' => 'درباره گیت',
            'is_published' => false,
        ]);
        $this->get(route('pages.about'))->assertNotFound();
    }

    public function test_mag_pages_are_seeded_at_their_original_urls_with_local_content_images(): void
    {
        foreach ([
            'worldwide-tours' => ['تور خارجی', 'worldwide-tours.jpg'],
            'accommodation' => ['رزرو اقامتگاه', 'accommodation.webp'],
            'domestic-tours' => ['تور داخلی', 'domestic-tours.jpg'],
            'domestic-hotels' => ['رزرو هتل داخلی', 'domestic-hotels.jpeg'],
            'worldwide-hotels' => ['رزرو هتل خارجی', 'worldwide-hotels.jpg'],
        ] as $slug => [$title, $image]) {
            $page = StaticPage::where('slug', $slug)->sole();

            $this->assertSame(url('/mag/'.$slug).'/', $page->publicUrl());
            $this->get('/mag/'.$slug.'/')
                ->assertOk()
                ->assertSee($title)
                ->assertSee('/images/mag/'.$image, false)
                ->assertSee('"@type":"BlogPosting"', false);
        }
    }

    public function test_mag_index_lists_published_mag_pages_and_is_linked_from_header(): void
    {
        StaticPage::where('slug', 'worldwide-hotels')->update(['is_published' => false]);

        $this->get(route('mag.index'))
            ->assertOk()
            ->assertSee('راهنمای سفر و اقامت')
            ->assertSee('تور خارجی')
            ->assertSee('/mag/worldwide-tours/', false)
            ->assertDontSee('رزرو هتل خارجی');

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('href="'.route('mag.index').'/'.'">مگ</a>', false);
    }

    public function test_public_static_pages_add_meaningful_alt_text_to_content_images(): void
    {
        $page = StaticPage::where('slug', 'about-us')->sole();
        $page->update([
            'title' => 'درباره گیت',
            'content' => '<img src="/missing-alt.jpg"><img src="/empty-alt.jpg" alt=""><img src="/custom-alt.jpg" alt="متن اختصاصی">',
            'is_published' => true,
        ]);

        $this->get(route('pages.about'))
            ->assertOk()
            ->assertSee('src="/missing-alt.jpg" alt="تصویر مرتبط با درباره گیت"', false)
            ->assertSee('src="/empty-alt.jpg" alt="تصویر مرتبط با درباره گیت"', false)
            ->assertSee('src="/custom-alt.jpg" alt="متن اختصاصی"', false);
    }

    public function test_mag_pages_are_visible_in_static_page_management(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)
            ->get(route('admin.static-pages.index'))
            ->assertOk()
            ->assertSee('/mag/worldwide-tours/')
            ->assertSee('/mag/accommodation/')
            ->assertSee('/mag/domestic-tours/')
            ->assertSee('/mag/domestic-hotels/')
            ->assertSee('/mag/worldwide-hotels/');
    }

    public function test_guest_cannot_manage_static_pages(): void
    {
        $this->get(route('admin.static-pages.index'))->assertRedirect('/login');
    }
}
