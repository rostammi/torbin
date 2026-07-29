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
            ->assertSee('آیا استفاده از گیت هزینه دارد؟');

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

    public function test_guest_cannot_manage_static_pages(): void
    {
        $this->get(route('admin.static-pages.index'))->assertRedirect('/login');
    }
}
