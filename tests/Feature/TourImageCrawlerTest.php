<?php

namespace Tests\Feature;

use App\Models\SyncRun;
use App\Models\Tour;
use App\Models\TourSuggestion;
use App\Models\User;
use App\Services\Discovery\PopularTourDiscovery;
use App\Services\Images\TourImageCrawler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TourImageCrawlerTest extends TestCase
{
    use RefreshDatabase;

    public function test_image_sync_adds_valid_cover_gallery_and_attribution_only_to_tours_without_images(): void
    {
        Storage::fake('public');
        config()->set('crawler.images.count', 2);
        config()->set('crawler.images.min_width', 1);
        config()->set('crawler.images.min_height', 1);
        config()->set('crawler.images.min_aspect_ratio', 1);

        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
        Http::fake([
            'commons.wikimedia.org/*' => Http::response([
                'query' => ['pages' => [
                    $this->commonsImage('File:Kish coast.jpg', 'https://upload.wikimedia.org/kish-coast.png', 'عکاس اول'),
                    $this->commonsImage('File:Kish beach.jpg', 'https://upload.wikimedia.org/kish-beach.png', 'عکاس دوم'),
                ]],
            ]),
            'upload.wikimedia.org/*' => Http::sequence()
                ->push($png, 200, ['Content-Type' => 'image/png'])
                ->push($png, 200, ['Content-Type' => 'image/png']),
        ]);

        $tour = Tour::create([
            'title' => 'تور کیش ارزان',
            'slug' => 'kish-images',
            'description' => 'توضیحات',
            'is_active' => true,
        ]);
        TourSuggestion::create([
            'keyword' => 'تور کیش ارزان',
            'suggested_title' => 'تور کیش ارزان',
            'destination' => 'کیش',
            'trend_score' => 90,
            'source' => 'destination_catalog',
            'status' => 'created',
            'tour_id' => $tour->id,
        ]);
        $tourWithImage = Tour::create([
            'title' => 'تور دارای تصویر',
            'slug' => 'already-has-image',
            'description' => 'توضیحات',
            'cover_image' => 'tours/manual/existing.jpg',
            'is_active' => true,
        ]);
        $hotelWithoutImage = Tour::create([
            'category' => 'hotel',
            'title' => 'هتل بدون تصویر',
            'slug' => 'hotel-without-image',
            'description' => 'توضیحات',
            'is_active' => true,
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('admin.sync.index'))
            ->assertOk()
            ->assertSee('تصاویر تورهای بدون عکس')
            ->assertSee('تصاویر هتل‌های بدون عکس')
            ->assertSee('تصاویر اقامتگاه‌های بدون عکس')
            ->assertSee('تصاویر ویزاهای بدون عکس')
            ->assertSee('1 تور بدون عکس')
            ->assertSee('1 هتل بدون عکس');

        $this->actingAs(User::factory()->create())
            ->post(route('admin.sync.run'), ['type' => 'images_tours'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $tour->refresh();
        $run = SyncRun::where('type', 'images_tours')->sole();
        $this->assertSame('success', $run->status);
        $this->assertSame(1, $run->total);
        $this->assertSame(1, $run->successful);
        $this->assertSame(2, $run->details['images']['downloaded']);
        $this->assertNotNull($tour->cover_image);
        $this->assertCount(1, $tour->gallery);
        $this->assertCount(2, $tour->image_sources);
        Storage::disk('public')->assertExists($tour->cover_image);
        Storage::disk('public')->assertExists($tour->gallery[0]);
        $this->assertSame('tours/manual/existing.jpg', $tourWithImage->fresh()->cover_image);
        $this->assertNull($hotelWithoutImage->fresh()->cover_image);

        $this->get(route('tours.show', $tour))
            ->assertOk()
            ->assertSee('منبع و مجوز تصاویر')
            ->assertSee('عکاس اول')
            ->assertSee('CC BY-SA 4.0');

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'commons.wikimedia.org')
            && $request['gsrsearch'] === 'Kish Island Iran');
    }

    public function test_default_image_quality_threshold_is_hd(): void
    {
        $this->assertSame(1280, config('crawler.images.min_width'));
        $this->assertSame(720, config('crawler.images.min_height'));
        $this->assertSame(1.2, config('crawler.images.min_aspect_ratio'));
        $this->assertSame([], array_values(array_diff(
            [...PopularTourDiscovery::DOMESTIC_DESTINATIONS, ...PopularTourDiscovery::FOREIGN_DESTINATIONS],
            array_keys(config('crawler.images.aliases')),
        )));
    }

    public function test_largest_undersized_image_is_scaled_and_center_cropped_when_no_valid_image_exists(): void
    {
        Storage::fake('public');
        config()->set('crawler.images.count', 2);
        config()->set('crawler.images.min_width', 4);
        config()->set('crawler.images.min_height', 2);
        config()->set('crawler.images.min_aspect_ratio', 1.2);

        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
        $smaller = $this->commonsImage('File:Small fallback.jpg', 'https://upload.wikimedia.org/small-fallback.png', 'عکاس کوچک');
        $smaller['imageinfo'][0]['width'] = 1;
        $smaller['imageinfo'][0]['height'] = 1;
        $largest = $this->commonsImage('File:Largest fallback.jpg', 'https://upload.wikimedia.org/largest-fallback.png', 'عکاس بزرگ');
        $largest['imageinfo'][0]['width'] = 3;
        $largest['imageinfo'][0]['height'] = 1;

        Http::fake([
            'commons.wikimedia.org/*' => Http::response(['query' => ['pages' => [$smaller, $largest]]]),
            'upload.wikimedia.org/largest-fallback.png' => Http::response($png, 200, ['Content-Type' => 'image/png']),
            'upload.wikimedia.org/small-fallback.png' => Http::response($png, 200, ['Content-Type' => 'image/png']),
        ]);

        $tour = Tour::create([
            'title' => 'صفحه بدون تصویر بزرگ',
            'slug' => 'undersized-image-fallback',
            'description' => 'توضیحات',
            'is_active' => true,
        ]);

        $result = app(TourImageCrawler::class)->crawl($tour);

        $tour->refresh();
        $storedImage = Storage::disk('public')->get($tour->cover_image);
        $size = getimagesizefromstring($storedImage);
        $this->assertSame(1, $result['downloaded']);
        $this->assertSame([4, 2], [$size[0], $size[1]]);
        $this->assertTrue($tour->image_sources[0]['upscaled']);
        $this->assertSame(1, $tour->image_sources[0]['original_width']);
        $this->assertSame(1, $tour->image_sources[0]['original_height']);
        $this->assertStringContainsString('Largest_fallback.jpg', $tour->image_sources[0]['page_url']);
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'small-fallback.png'));
    }

    public function test_stay_with_a_long_persian_title_uses_an_english_semantic_fallback_query(): void
    {
        Storage::fake('public');
        config()->set('crawler.images.min_width', 4);
        config()->set('crawler.images.min_height', 2);
        config()->set('crawler.images.min_aspect_ratio', 1.2);

        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
        $fallback = $this->commonsImage('File:Wooden cabin forest.jpg', 'https://upload.wikimedia.org/wooden-cabin.png', 'عکاس کلبه');
        $fallback['imageinfo'][0]['width'] = 3;
        $fallback['imageinfo'][0]['height'] = 1;

        Http::fake(function (Request $request) use ($fallback, $png) {
            if (str_contains($request->url(), 'upload.wikimedia.org')) {
                return Http::response($png, 200, ['Content-Type' => 'image/png']);
            }

            return Http::response([
                'query' => ['pages' => $request['gsrsearch'] === 'wooden cabin forest' ? [$fallback] : []],
            ]);
        });

        $stay = Tour::create([
            'category' => 'stay',
            'title' => 'اقامتگاه کلبه چوبی و جنگلی | مقایسه قیمت و رزرو',
            'slug' => 'wooden-forest-cabin',
            'description' => 'توضیحات',
            'is_active' => true,
        ]);
        TourSuggestion::create([
            'category' => 'stay',
            'keyword' => 'اقامتگاه کلبه چوبی و جنگلی',
            'suggested_title' => $stay->title,
            'destination' => 'کلبه چوبی و جنگلی',
            'trend_score' => 50,
            'source' => 'geyt_reference',
            'status' => 'created',
            'tour_id' => $stay->id,
        ]);

        $result = app(TourImageCrawler::class)->crawl($stay);

        $stay->refresh();
        $this->assertSame(1, $result['downloaded']);
        $this->assertNotNull($stay->cover_image);
        $this->assertTrue($stay->image_sources[0]['upscaled']);
        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'commons.wikimedia.org')
            && $request['gsrsearch'] === 'wooden cabin forest');
    }

    public function test_image_sync_cannot_be_queued_twice_while_a_run_is_active(): void
    {
        Queue::fake();
        SyncRun::create([
            'type' => 'images',
            'status' => 'running',
            'started_at' => now(),
        ]);

        $this->actingAs(User::factory()->create())
            ->post(route('admin.sync.run'), ['type' => 'images'])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(1, SyncRun::where('type', 'images')->count());
        Queue::assertNothingPushed();
    }

    public function test_admin_can_replace_one_tours_images_without_deleting_old_files_early(): void
    {
        Storage::fake('public');
        config()->set('crawler.images.count', 2);
        config()->set('crawler.images.min_width', 1);
        config()->set('crawler.images.min_height', 1);
        config()->set('crawler.images.min_aspect_ratio', 1);

        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
        Http::fake([
            'commons.wikimedia.org/*' => Http::response([
                'query' => ['pages' => [
                    $this->commonsImage('File:New Kish coast.jpg', 'https://upload.wikimedia.org/new-cover.png', 'عکاس جدید اول'),
                    $this->commonsImage('File:New Kish beach.jpg', 'https://upload.wikimedia.org/new-gallery.png', 'عکاس جدید دوم'),
                ]],
            ]),
            'upload.wikimedia.org/*' => Http::sequence()
                ->push($png, 200, ['Content-Type' => 'image/png'])
                ->push($png, 200, ['Content-Type' => 'image/png']),
        ]);

        Storage::disk('public')->put('tours/old-cover.png', $png);
        Storage::disk('public')->put('tours/old-gallery.png', $png);
        $tour = Tour::create([
            'title' => 'تور کیش با عکس قدیمی',
            'slug' => 'kish-refresh-images',
            'description' => 'توضیحات',
            'cover_image' => 'tours/old-cover.png',
            'gallery' => ['tours/old-gallery.png'],
            'is_active' => true,
        ]);
        TourSuggestion::create([
            'keyword' => 'تور کیش با عکس قدیمی',
            'suggested_title' => 'تور کیش',
            'destination' => 'کیش',
            'trend_score' => 90,
            'source' => 'destination_catalog',
            'status' => 'created',
            'tour_id' => $tour->id,
        ]);

        $this->actingAs(User::factory()->create())
            ->post(route('admin.tours.refresh-images', $tour))
            ->assertRedirect()
            ->assertSessionHas('success');

        $tour->refresh();
        $run = SyncRun::where('type', 'refresh_tour_images')->sole();
        $this->assertSame('success', $run->status);
        $this->assertSame($tour->id, $run->details['tour_id']);
        $this->assertSame(2, $run->details['downloaded']);
        $this->assertNotSame('tours/old-cover.png', $tour->cover_image);
        $this->assertCount(1, $tour->gallery);
        $this->assertCount(2, $tour->image_sources);
        Storage::disk('public')->assertExists($tour->cover_image);
        Storage::disk('public')->assertExists($tour->gallery[0]);
        Storage::disk('public')->assertMissing('tours/old-cover.png');
        Storage::disk('public')->assertMissing('tours/old-gallery.png');
    }

    public function test_admin_can_append_three_crawled_images_without_replacing_existing_images(): void
    {
        Storage::fake('public');
        config()->set('crawler.images.min_width', 1);
        config()->set('crawler.images.min_height', 1);
        config()->set('crawler.images.min_aspect_ratio', 1);

        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
        $searchResponse = [
            'query' => ['pages' => [
                $this->commonsImage('File:Kish one.jpg', 'https://upload.wikimedia.org/kish-one.png', 'عکاس اول'),
                $this->commonsImage('File:Kish two.jpg', 'https://upload.wikimedia.org/kish-two.png', 'عکاس دوم'),
                $this->commonsImage('File:Kish three.jpg', 'https://upload.wikimedia.org/kish-three.png', 'عکاس سوم'),
                $this->commonsImage('File:Kish four.jpg', 'https://upload.wikimedia.org/kish-four.png', 'عکاس چهارم'),
            ]],
        ];
        Http::fake([
            'commons.wikimedia.org/*' => fn () => Http::response($searchResponse),
            'upload.wikimedia.org/*' => fn () => Http::response($png, 200, ['Content-Type' => 'image/png']),
        ]);

        Storage::disk('public')->put('tours/existing-cover.png', $png);
        Storage::disk('public')->put('tours/existing-gallery.png', $png);
        $tour = Tour::create([
            'title' => 'تور کیش',
            'slug' => 'kish-append-images',
            'description' => 'توضیحات',
            'cover_image' => 'tours/existing-cover.png',
            'gallery' => ['tours/existing-gallery.png'],
            'is_active' => true,
        ]);
        TourSuggestion::create([
            'keyword' => 'تور کیش',
            'suggested_title' => 'تور کیش',
            'destination' => 'کیش',
            'trend_score' => 90,
            'source' => 'destination_catalog',
            'status' => 'created',
            'tour_id' => $tour->id,
        ]);

        $this->actingAs(User::factory()->create())
            ->post(route('admin.tours.add-images', $tour))
            ->assertRedirect()
            ->assertSessionHas('success');

        $tour->refresh();
        $run = SyncRun::where('type', 'add_tour_images')->sole();
        $this->assertSame('success', $run->status);
        $this->assertSame(3, $run->details['downloaded']);
        $this->assertSame('tours/existing-cover.png', $tour->cover_image);
        $this->assertCount(4, $tour->gallery);
        $this->assertSame('tours/existing-gallery.png', $tour->gallery[0]);
        $this->assertCount(3, $tour->image_sources);
        Storage::disk('public')->assertExists('tours/existing-cover.png');
        Storage::disk('public')->assertExists('tours/existing-gallery.png');
        foreach (array_slice($tour->gallery, 1) as $path) {
            Storage::disk('public')->assertExists($path);
        }
    }

    private function commonsImage(string $title, string $url, string $artist): array
    {
        return [
            'title' => $title,
            'imageinfo' => [[
                'thumburl' => $url,
                'width' => 1600,
                'height' => 900,
                'mime' => 'image/png',
                'extmetadata' => [
                    'Artist' => ['value' => $artist],
                    'Credit' => ['value' => 'Wikimedia Commons'],
                    'LicenseShortName' => ['value' => 'CC BY-SA 4.0'],
                    'LicenseUrl' => ['value' => 'https://creativecommons.org/licenses/by-sa/4.0/'],
                ],
            ]],
        ];
    }
}
