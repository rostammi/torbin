<?php

namespace Tests\Feature;

use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

    public function test_admin_can_upload_manual_images_as_first_images_and_reorder_the_complete_gallery(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('tours/old-cover.jpg', 'old cover');
        Storage::disk('public')->put('tours/old-gallery.jpg', 'old gallery');
        $tour = Tour::create([
            'title' => 'تور تصویری',
            'slug' => 'manual-image-order',
            'description' => 'توضیحات',
            'cover_image' => 'tours/old-cover.jpg',
            'gallery' => ['tours/old-gallery.jpg'],
            'is_active' => true,
        ]);
        $admin = User::factory()->create();
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');

        $this->actingAs($admin)
            ->post(route('admin.tours.upload-images', $tour), [
                'images' => [
                    UploadedFile::fake()->createWithContent('manual-first.png', $png),
                    UploadedFile::fake()->createWithContent('manual-second.png', $png),
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $tour->refresh();
        $firstManual = $tour->cover_image;
        $secondManual = $tour->gallery[0];
        $this->assertStringStartsWith('tours/manual/'.$tour->id.'/', $firstManual);
        $this->assertSame([$secondManual, 'tours/old-cover.jpg', 'tours/old-gallery.jpg'], $tour->gallery);
        $this->assertSame(['آپلود دستی', 'آپلود دستی'], collect($tour->image_sources)->pluck('artist')->all());
        Storage::disk('public')->assertExists($firstManual);
        Storage::disk('public')->assertExists($secondManual);

        $newOrder = ['tours/old-gallery.jpg', $firstManual, $secondManual, 'tours/old-cover.jpg'];
        $this->actingAs($admin)
            ->put(route('admin.tours.reorder-images', $tour), ['images' => $newOrder])
            ->assertRedirect()
            ->assertSessionHas('success');

        $tour->refresh();
        $this->assertSame($newOrder[0], $tour->cover_image);
        $this->assertSame(array_slice($newOrder, 1), $tour->gallery);

        $this->actingAs($admin)
            ->get(route('admin.tours.edit', $tour))
            ->assertOk()
            ->assertSee('تصاویر و ترتیب نمایش')
            ->assertSee('افزودن ۳ عکس خودکار')
            ->assertSee('آپلود و قراردادن در ابتدا')
            ->assertSee(Storage::url($newOrder[0]));
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
            ->assertSee('افزودن ۳ عکس')
            ->assertDontSee('pagination.previous')
            ->assertDontSee('pagination.next');

        $response->assertDontSee('<svg', false);
    }

    public function test_tour_index_shows_the_first_image_and_clearly_marks_pages_without_images(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('tours/list-cover.jpg', 'cover');

        Tour::create([
            'title' => 'صفحه دارای عکس',
            'slug' => 'page-with-image',
            'description' => 'توضیحات',
            'cover_image' => 'tours/list-cover.jpg',
            'is_active' => true,
        ]);
        Tour::create([
            'title' => 'صفحه بدون عکس',
            'slug' => 'page-without-image',
            'description' => 'توضیحات',
            'is_active' => true,
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('admin.tours.index'))
            ->assertOk()
            ->assertSee('تصویر اول')
            ->assertSee(Storage::url('tours/list-cover.jpg'))
            ->assertSee('alt="تصویر اول صفحه دارای عکس"', false)
            ->assertSee('بدون عکس')
            ->assertSee('class="missing-image-button"', false);
    }
}
