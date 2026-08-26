<?php

namespace App\Http\Controllers;

use App\Models\Agency;
use App\Models\StaticPage;
use App\Models\Tour;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class SeoController extends Controller
{
    public function sitemap(): Response
    {
        $urls = collect([
            ['loc' => url('/'), 'lastmod' => null],
            ['loc' => route('tours.index').'/', 'lastmod' => null],
            ['loc' => route('hotels.index').'/', 'lastmod' => null],
            ['loc' => route('stays.index').'/', 'lastmod' => null],
            ['loc' => route('visas.index').'/', 'lastmod' => null],
            ['loc' => route('mag.index').'/', 'lastmod' => null],
        ]);

        $urls = $urls->concat(Tour::query()->published()->get()->map(fn (Tour $tour) => [
            'loc' => $tour->publicUrl(),
            'lastmod' => $tour->updated_at?->toAtomString(),
            'image' => $this->comparisonImageUrl($tour),
        ]));
        $urls = $urls->concat(StaticPage::query()->where('is_published', true)->get()->map(fn (StaticPage $page) => [
            'loc' => $page->publicUrl(),
            'lastmod' => $page->updated_at?->toAtomString(),
        ]));
        $urls = $urls->concat($this->providerUrls());

        return response()
            ->view('seo.sitemap', ['urls' => $urls->unique('loc')->values()])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    private function comparisonImageUrl(Tour $tour): ?string
    {
        $image = $tour->cover_image ?: data_get($tour->gallery, 0);

        return $image ? url(Storage::url($image)) : null;
    }

    private function providerUrls(): Collection
    {
        return Agency::query()
            ->whereHas('priceSources', fn ($query) => $query->where('is_active', true)->funded()->where('latest_price', '>', 0))
            ->get()
            ->unique(fn (Agency $agency) => $agency->providerSlug())
            ->map(fn (Agency $agency) => ['loc' => $agency->publicUrl(), 'lastmod' => $agency->updated_at?->toAtomString()]);
    }
}
