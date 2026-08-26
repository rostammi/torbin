<?php

namespace Tests\Feature;

use App\Models\LegacyRedirect;
use App\Models\Tour;
use App\Models\TourSuggestion;
use App\Models\User;
use App\Services\Seo\LegacyRedirectSynchronizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class SeoMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_www_host_redirects_directly_to_apex_canonical_url(): void
    {
        config(['app.url' => 'https://geyt.ir']);
        URL::forceRootUrl('https://geyt.ir');
        URL::forceScheme('https');

        $this->withServerVariables([
            'HTTP_HOST' => 'www.geyt.ir',
            'HTTPS' => 'on',
            'SERVER_PORT' => 443,
        ])->get('/faq?ref=www')
            ->assertStatus(301)
            ->assertRedirect('https://geyt.ir/faq/?ref=www');
    }

    public function test_http_redirects_directly_to_https_canonical_url(): void
    {
        config(['app.url' => 'https://geyt.ir']);
        URL::forceRootUrl('https://geyt.ir');
        URL::forceScheme('https');

        $this->withServerVariables([
            'HTTP_HOST' => 'geyt.ir',
            'HTTPS' => 'off',
            'SERVER_PORT' => 80,
        ])->get('/category/hotel?ref=http')
            ->assertStatus(301)
            ->assertRedirect('https://geyt.ir/category/hotel/?ref=http');

        $secureServer = [
            'HTTP_HOST' => 'geyt.ir',
            'HTTPS' => 'on',
            'SERVER_PORT' => 443,
        ];
        $this->withServerVariables($secureServer)->get('/')
            ->assertOk()
            ->assertSee('<link rel="canonical" href="https://geyt.ir">', false);
        $this->withServerVariables($secureServer)->get('/faq/')
            ->assertOk()
            ->assertSee('<link rel="canonical" href="https://geyt.ir/faq/">', false);
    }

    public function test_old_geyt_detail_url_redirects_directly_to_the_current_canonical_url(): void
    {
        [$tour] = $this->legacySuggestion('hotel', 'هتل آمستردام', 'https://geyt.ir/hotel/amsterdam-old/');

        app(LegacyRedirectSynchronizer::class)->sync();

        $redirect = LegacyRedirect::sole();
        $this->assertSame('/hotel/amsterdam-old/', $redirect->old_path);
        $this->assertSame('automatic', $redirect->match_type);
        $this->assertSame($tour->id, $redirect->tour_id);

        $this->get('/hotel/amsterdam-old/?utm_source=legacy')
            ->assertStatus(301)
            ->assertRedirect($tour->publicUrl());

        $tour->update(['slug' => 'amsterdam-hotels-new']);
        $this->get('/hotel/amsterdam-old/')
            ->assertStatus(301)
            ->assertRedirect($tour->fresh()->publicUrl());

        $this->assertSame(2, $redirect->fresh()->hits);
        $this->assertNotNull($redirect->fresh()->last_hit_at);
    }

    public function test_unknown_reference_is_listed_for_manual_merge_and_can_be_assigned_by_admin(): void
    {
        [, $suggestion] = $this->legacySuggestion('visa', 'ویزای ژاپن', 'https://www.geyt.ir/visa/japan-old/', false);
        $target = Tour::create([
            'category' => 'visa',
            'title' => 'ویزای ژاپن جدید',
            'slug' => 'japan-visa-new',
            'description' => 'مقایسه خدمات ویزا',
            'is_active' => true,
        ]);

        app(LegacyRedirectSynchronizer::class)->sync();
        $redirect = LegacyRedirect::sole();
        $this->assertNull($redirect->tour_id);
        $this->get('/visa/japan-old/')->assertNotFound();

        $admin = User::factory()->create();
        $this->actingAs($admin)
            ->get(route('admin.seo-redirects.index'))
            ->assertOk()
            ->assertSee('japan-old')
            ->assertSee('بدون متناظر');

        $this->put(route('admin.seo-redirects.update', $redirect), ['tour_id' => $target->id])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('manual', $redirect->fresh()->match_type);
        $this->assertSame($target->id, $redirect->fresh()->tour_id);
        $this->get('/visa/japan-old/')
            ->assertStatus(301)
            ->assertRedirect($target->publicUrl());

        $suggestion->update(['tour_id' => null]);
        app(LegacyRedirectSynchronizer::class)->sync();
        $this->assertSame($target->id, $redirect->fresh()->tour_id, 'Automatic sync must not overwrite a manual merge.');
    }

    public function test_category_fallback_references_do_not_create_incorrect_page_redirects(): void
    {
        $this->legacySuggestion('tour', 'تور شیراز', 'https://geyt.ir/category/tour/');

        $result = app(LegacyRedirectSynchronizer::class)->sync();

        $this->assertSame(0, $result['total']);
        $this->assertDatabaseCount('legacy_redirects', 0);
    }

    public function test_redirect_chains_are_collapsed_to_one_direct_permanent_redirect(): void
    {
        $middle = Tour::create([
            'title' => 'تور میانی', 'slug' => 'middle-seo-url', 'description' => '...', 'is_active' => true,
        ]);
        $final = Tour::create([
            'title' => 'تور نهایی', 'slug' => 'final-seo-url', 'description' => '...', 'is_active' => true,
        ]);
        LegacyRedirect::create([
            'old_path' => '/tour/very-old-url/',
            'source_url' => 'https://geyt.ir/tour/very-old-url/',
            'tour_id' => $middle->id,
            'match_type' => 'manual',
        ]);
        LegacyRedirect::create([
            'old_path' => '/tour/middle-seo-url/',
            'source_url' => 'https://geyt.ir/tour/middle-seo-url/',
            'tour_id' => $final->id,
            'match_type' => 'manual',
        ]);

        $this->get('/tour/very-old-url/')
            ->assertStatus(301)
            ->assertRedirect($final->publicUrl());
    }

    public function test_admin_can_register_an_additional_old_url_manually(): void
    {
        $tour = Tour::create([
            'title' => 'تور شیراز',
            'slug' => 'shiraz-tour-new',
            'description' => '...',
            'is_active' => true,
        ]);

        $this->actingAs(User::factory()->create())
            ->post(route('admin.seo-redirects.store'), [
                'source_url' => 'http://www.geyt.ir/tour/shiraz-legacy/?ref=old',
                'tour_id' => $tour->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('legacy_redirects', [
            'old_path' => '/tour/shiraz-legacy/',
            'tour_id' => $tour->id,
            'match_type' => 'manual',
        ]);
        $this->get('/tour/shiraz-legacy/')->assertStatus(301)->assertRedirect($tour->publicUrl());
    }

    public function test_sitemap_contains_only_canonical_published_pages(): void
    {
        $published = Tour::create([
            'category' => 'stay',
            'title' => 'اقامتگاه ماسال',
            'slug' => 'masal-stay-seo',
            'description' => '...',
            'cover_image' => 'tours/covers/masal-main.jpg',
            'gallery' => ['tours/gallery/masal-second.jpg'],
            'is_active' => true,
        ]);
        $draft = Tour::create([
            'title' => 'پیشنهاد پیش‌نویس',
            'slug' => 'draft-seo-page',
            'description' => '...',
            'is_active' => false,
        ]);

        $response = $this->get(route('seo.sitemap'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee('<?xml version="1.0" encoding="UTF-8"?>', false)
            ->assertSee('<loc>'.$published->publicUrl().'</loc>', false)
            ->assertSee('<loc>'.route('stays.index').'/'.'</loc>', false)
            ->assertSee('xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"', false)
            ->assertSee('<image:loc>'.url(Storage::url($published->cover_image)).'</image:loc>', false)
            ->assertDontSee('masal-second.jpg')
            ->assertDontSee($draft->publicUrl());

        $this->assertNotFalse(simplexml_load_string($response->getContent()));
    }

    public function test_public_pages_expose_canonical_metadata_and_matching_structured_data(): void
    {
        $tour = Tour::create([
            'title' => 'تور شیراز ویژه',
            'slug' => 'shiraz-structured-data',
            'excerpt' => 'مقایسه قیمت تور شیراز',
            'description' => 'جزئیات کامل سفر',
            'cover_image' => 'tours/covers/shiraz-lcp.jpg',
            'is_active' => true,
        ]);
        $tour->priceSources()->create([
            'provider_name' => 'آژانس نمونه',
            'source_url' => 'https://example.com/shiraz-tour',
            'buy_url' => 'https://example.com/shiraz-tour',
            'extraction_type' => 'manual',
            'latest_price' => 5_000_000,
            'currency' => 'تومان',
            'is_active' => true,
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('"@type":"Organization"', false)
            ->assertSee('"@type":"WebSite"', false)
            ->assertSee('rel="preload" href="'.asset('css/app.css').'" as="style"', false)
            ->assertSee('rel="stylesheet" href="'.asset('css/app.css').'" media="print"', false)
            ->assertSee('<noscript><link rel="stylesheet" href="'.asset('css/app.css').'"', false)
            ->assertSee('<link rel="canonical" href="'.url('/').'">', false);

        $detail = $this->get($tour->publicUrl())
            ->assertOk()
            ->assertSee('<link rel="canonical" href="'.$tour->publicUrl().'">', false)
            ->assertSee('<link rel="preload" as="image" href="'.Storage::url($tour->cover_image).'" fetchpriority="high">', false)
            ->assertSee('loading="eager" fetchpriority="high" decoding="async"', false)
            ->assertSee('<meta name="description" content="مقایسه قیمت تور شیراز">', false)
            ->assertSee('"@type":"TouristTrip"', false)
            ->assertSee('"@type":"AggregateOffer"', false)
            ->assertSee('"price":"50000000"', false)
            ->assertSee('"priceCurrency":"IRR"', false)
            ->assertSee('"@type":"TravelAgency"', false)
            ->assertSee('"@type":"BreadcrumbList"', false)
            ->assertSee('"url":"'.$tour->publicUrl().'"', false);

        preg_match_all('~<script type="application/ld\+json">(.*?)</script>~s', $detail->getContent(), $schemas);
        $this->assertNotEmpty($schemas[1]);
        foreach ($schemas[1] as $schema) {
            $this->assertIsArray(json_decode($schema, true, flags: JSON_THROW_ON_ERROR));
        }
    }

    public function test_non_indexable_utility_pages_have_robots_meta_and_robots_file_points_to_sitemap(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false);

        $robots = file_get_contents(public_path('robots.txt'));
        $this->assertStringContainsString('Disallow: /admin', $robots);
        $this->assertStringContainsString('Disallow: /search', $robots);
        $this->assertStringContainsString('Sitemap: https://geyt.ir/sitemap.xml', $robots);
    }

    private function legacySuggestion(
        string $category,
        string $label,
        string $oldUrl,
        bool $withTour = true,
    ): array {
        $tour = $withTour ? Tour::create([
            'category' => $category,
            'title' => $label,
            'slug' => str($label)->slug().'-new',
            'description' => '...',
            'is_active' => true,
        ]) : null;
        $suggestion = TourSuggestion::create([
            'category' => $category,
            'keyword' => $label,
            'suggested_title' => $label,
            'destination' => $label,
            'source' => 'geyt_reference_catalog',
            'status' => $tour ? 'created' : 'pending',
            'tour_id' => $tour?->id,
            'metadata' => ['geyt_references' => [[
                'label' => $label,
                'page_url' => $oldUrl,
            ]]],
        ]);

        return [$tour, $suggestion];
    }
}
