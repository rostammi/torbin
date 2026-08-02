<?php

namespace Tests\Feature;

use App\Models\TourSuggestion;
use App\Services\Discovery\GeytReferenceCatalogDiscovery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeytReferenceCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_geyt_reference_catalog_is_imported_without_duplicate_destination_pages(): void
    {
        $result = app(GeytReferenceCatalogDiscovery::class)->discover();

        $this->assertSame(169, $result['total']);
        $this->assertSame(165, TourSuggestion::count());
        $this->assertSame(93, TourSuggestion::where('category', 'tour')->count());
        $this->assertSame(24, TourSuggestion::where('category', 'hotel')->count());
        $this->assertSame(19, TourSuggestion::where('category', 'stay')->count());
        $this->assertSame(29, TourSuggestion::where('category', 'visa')->count());

        $mashhad = TourSuggestion::where('category', 'tour')->where('destination', 'مشهد')->sole();
        $this->assertContains('تور مشهد با قطار', $mashhad->metadata['keywords']);
        $this->assertContains('تور مشهد هوایی', $mashhad->metadata['keywords']);
        $this->assertCount(2, $mashhad->metadata['geyt_references']);

        $tehran = TourSuggestion::where('category', 'stay')->where('destination', 'تهران')->sole();
        $this->assertContains('ویلاهای اطراف تهران', $tehran->metadata['keywords']);
        $this->assertContains('آپارتمان های مبله تهران', $tehran->metadata['keywords']);
        $this->assertContains('سوئیت‌های تهران', $tehran->metadata['keywords']);
        $this->assertCount(3, $tehran->metadata['geyt_references']);
    }
}
