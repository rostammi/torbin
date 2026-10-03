<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminListSearchAndSortTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_tour_list_can_be_searched_and_sorted_in_both_directions(): void
    {
        $admin = User::factory()->create();
        foreach (['آبادان', 'یزد'] as $title) {
            Tour::create([
                'title' => $title,
                'slug' => 'tour-'.md5($title),
                'description' => 'توضیحات',
                'is_active' => true,
            ]);
        }

        $this->actingAs($admin)->get(route('admin.tours.index', ['q' => 'آبادان']))
            ->assertOk()
            ->assertSee('آبادان')
            ->assertDontSee('یزد');

        $this->actingAs($admin)->get(route('admin.tours.index', ['sort' => 'title', 'direction' => 'asc']))
            ->assertOk()
            ->assertSeeInOrder(['آبادان', 'یزد']);

        $this->actingAs($admin)->get(route('admin.tours.index', ['sort' => 'title', 'direction' => 'desc']))
            ->assertOk()
            ->assertSeeInOrder(['یزد', 'آبادان']);
    }

    public function test_every_admin_listing_accepts_its_search_and_allow_listed_sort_parameters(): void
    {
        $admin = User::factory()->create();
        $agency = Agency::create(['name' => 'آژانس آزمون']);
        $urls = [
            route('admin.advertisements.index', ['q' => 'نمونه', 'sort' => 'name', 'direction' => 'asc']),
            route('admin.static-pages.index', ['q' => 'تماس', 'sort' => 'title', 'direction' => 'desc']),
            route('admin.comparison-sources.index', ['q' => 'نمونه', 'sort' => 'last_scan', 'direction' => 'asc']),
            route('admin.suggestions.index', ['q' => 'نمونه', 'sort' => 'trend', 'direction' => 'asc']),
            route('admin.sync.index', ['q' => 'prices', 'sort' => 'started', 'direction' => 'asc']),
            route('admin.contact-requests.index', ['q' => '0912', 'sort' => 'tour', 'direction' => 'asc']),
            route('admin.agencies.index', ['q' => 'نمونه', 'sort' => 'balance', 'direction' => 'desc']),
            route('admin.seo-redirects.index', ['q' => 'قدیمی', 'sort' => 'destination', 'direction' => 'asc']),
            route('admin.dashboard', [
                'q' => 'نمونه', 'sort' => 'views', 'direction' => 'desc',
                'q_contact' => 'گیت', 'sort_contact' => 'clicks', 'direction_contact' => 'asc',
                'q_keyword' => 'تور', 'sort_keyword' => 'visitors', 'direction_keyword' => 'desc',
            ]),
            route('admin.dashboard', ['agency_id' => $agency->id, 'sort' => 'gap', 'direction' => 'asc']),
            route('admin.dashboard', ['sort' => 'conversion', 'direction' => 'desc']),
        ];

        foreach ($urls as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }
}
