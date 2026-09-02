<?php

namespace Tests\Feature;

use App\Models\LegacyRedirect;
use App\Models\Tour;
use App\Services\Images\GeytCoverImageImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GeytCoverImageImporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_geyt_cover_becomes_first_image_without_removing_existing_images(): void
    {
        Storage::fake('public');
        config(['seo.sitemap.auto_refresh' => false]);
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
        $pageUrl = 'https://geyt.ir/tour/kish/';
        $sourceImage = 'https://geyt.ir/storage/service/46/cover.webp';
        Http::fake([
            $pageUrl => Http::response('<meta property="og:image" content="'.$sourceImage.'">'),
            $sourceImage => Http::response($png, 200, ['Content-Type' => 'image/png']),
        ]);
        Storage::disk('public')->put('tours/current-cover.webp', 'current-cover');
        Storage::disk('public')->put('tours/current-gallery.webp', 'current-gallery');
        $tour = Tour::create([
            'title' => 'تور کیش',
            'slug' => 'kish-current',
            'description' => '...',
            'cover_image' => 'tours/current-cover.webp',
            'gallery' => ['tours/current-gallery.webp'],
            'is_active' => true,
        ]);
        LegacyRedirect::create([
            'old_path' => '/tour/kish/',
            'source_url' => $pageUrl,
            'source_title' => 'تور کیش',
            'tour_id' => $tour->id,
            'match_type' => 'automatic',
        ]);

        $first = app(GeytCoverImageImporter::class)->import($tour);
        $tour->refresh();

        $this->assertSame('imported', $first['status']);
        $this->assertStringStartsWith('tours/geyt-covers/'.$tour->id.'/', $tour->cover_image);
        $this->assertStringEndsWith('.webp', $tour->cover_image);
        $this->assertSame(['tours/current-cover.webp', 'tours/current-gallery.webp'], $tour->gallery);
        $this->assertSame($tour->cover_image, data_get($tour->image_sources, '0.path'));
        $this->assertSame('geyt_cover', data_get($tour->image_sources, '0.source'));
        Storage::disk('public')->assertExists($tour->cover_image);
        Storage::disk('public')->assertExists('tours/current-cover.webp');
        Storage::disk('public')->assertExists('tours/current-gallery.webp');
        $this->assertSame(IMAGETYPE_WEBP, getimagesizefromstring(Storage::disk('public')->get($tour->cover_image))[2]);

        $second = app(GeytCoverImageImporter::class)->import($tour);
        $tour->refresh();

        $this->assertSame('reused', $second['status']);
        $this->assertSame(['tours/current-cover.webp', 'tours/current-gallery.webp'], $tour->gallery);
        $this->assertCount(1, collect($tour->image_sources)->where('source', 'geyt_cover'));
        Http::assertSentCount(3);
    }
}
