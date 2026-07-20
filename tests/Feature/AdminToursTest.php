<?php

namespace Tests\Feature;

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
}
