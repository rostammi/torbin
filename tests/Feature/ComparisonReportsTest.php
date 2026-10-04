<?php

namespace Tests\Feature;

use App\Models\ComparisonReport;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComparisonReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_visitor_can_report_a_problem_on_a_comparison_page(): void
    {
        $tour = $this->tour();

        $this->get($tour->publicUrl())
            ->assertOk()
            ->assertSee('گزارش مشکل در این صفحه')
            ->assertSee('محتوای نامناسب')
            ->assertSee('تصویر نامناسب')
            ->assertSee('قیمت اشتباه');

        $this->from($tour->publicUrl())
            ->post(route('comparison-reports.store', $tour), [
                'report_type' => 'incorrect_price',
                'report_details' => 'قیمت منبع اول به‌روز نیست.',
            ])
            ->assertRedirect($tour->publicUrl())
            ->assertSessionHas('success');

        $this->assertDatabaseHas('comparison_reports', [
            'tour_id' => $tour->id,
            'type' => 'incorrect_price',
            'details' => 'قیمت منبع اول به‌روز نیست.',
            'status' => 'pending',
        ]);
    }

    public function test_admin_can_filter_and_resolve_user_reports(): void
    {
        $report = $this->tour()->comparisonReports()->create([
            'type' => 'inappropriate_image',
            'details' => 'تصویر اصلی مرتبط نیست.',
            'status' => 'pending',
        ]);
        $admin = User::factory()->create();

        $this->actingAs($admin)
            ->get(route('admin.comparison-reports.index', ['type' => 'inappropriate_image']))
            ->assertOk()
            ->assertSee('تصویر اصلی مرتبط نیست.')
            ->assertSee('تصویر نامناسب');

        $this->put(route('admin.comparison-reports.update', $report), ['status' => 'resolved'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('resolved', $report->fresh()->status);
        $this->assertNotNull($report->fresh()->resolved_at);
    }

    public function test_invalid_or_bot_reports_are_rejected(): void
    {
        $tour = $this->tour();
        $this->post(route('comparison-reports.store', $tour), ['report_type' => 'unknown'])
            ->assertSessionHasErrors('report_type');
        $this->post(route('comparison-reports.store', $tour), [
            'report_type' => 'incorrect_price', 'website' => 'spam.example',
        ])->assertSessionHasErrors('website');

        $this->assertDatabaseCount('comparison_reports', 0);
    }

    public function test_agency_user_cannot_access_user_reports(): void
    {
        $agencyUser = User::factory()->create(['role' => 'agency']);

        $this->actingAs($agencyUser)
            ->get(route('admin.comparison-reports.index'))
            ->assertForbidden();
    }

    private function tour(): Tour
    {
        return Tour::create([
            'title' => 'تور قابل گزارش', 'slug' => 'reportable-'.str()->random(6),
            'description' => 'توضیحات', 'is_active' => true,
        ]);
    }
}
