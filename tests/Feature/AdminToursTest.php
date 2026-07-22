<?php

namespace Tests\Feature;

use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminToursTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_admin(): void
    {
        $this->get('/admin/tours')->assertRedirect('/login');
    }

    public function test_admin_can_create_a_tour(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/admin/tours', [
            'title' => 'تور شیراز',
            'slug' => 'shiraz',
            'description' => 'برنامه کامل سفر',
            'is_active' => '1',
        ])->assertRedirect('/admin/tours/shiraz/edit');

        $this->assertDatabaseHas('tours', ['slug' => 'shiraz', 'is_active' => true]);
    }

    public function test_admin_can_attach_all_ten_comparison_sites_to_a_tour(): void
    {
        $tour = Tour::create([
            'title' => 'تور کیش',
            'slug' => 'kish-sources',
            'description' => 'توضیحات',
            'is_active' => true,
        ]);

        $this->actingAs(User::factory()->create())
            ->post(route('admin.sources.official', $tour))
            ->assertRedirect();

        $this->assertSame(10, $tour->priceSources()->count());
        $this->assertSame(7, $tour->priceSources()->where('extraction_type', 'marketplace_html')->count());
        $this->assertSame(['کیش'], $tour->priceSources()->pluck('selector')->unique()->values()->all());
    }

    public function test_tour_index_uses_compact_persian_pagination(): void
    {
        foreach (range(1, 16) as $number) {
            Tour::create([
                'title' => "تور آزمایشی {$number}",
                'slug' => "test-tour-{$number}",
                'description' => 'توضیحات تور آزمایشی',
                'is_active' => true,
            ]);
        }

        $response = $this->actingAs(User::factory()->create())
            ->get(route('admin.tours.index'))
            ->assertOk()
            ->assertSee('نمایش 1 تا 15 از 16 نتیجه')
            ->assertSee('قبلی')
            ->assertSee('بعدی')
            ->assertDontSee('pagination.previous')
            ->assertDontSee('pagination.next');

        $response->assertDontSee('<svg', false);
    }
}
