<?php

namespace App\Http\Controllers;

use App\Models\PriceHistory;
use App\Models\SiteSetting;
use App\Models\Tour;
use App\Services\Advertising\AdvertisementManager;
use App\Services\Analytics\TourViewTracker;
use App\Services\RelatedComparisons;
use App\Services\Seo\StructuredDataBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(AdvertisementManager $advertisements, StructuredDataBuilder $seo): View
    {
        $cachedSections = Cache::remember('public-home:category-sections:v1', now()->addSeconds(30), function () {
            return collect(config('comparison.categories'))->map(function (array $config, string $category) {
                $items = Tour::query()
                    ->published()
                    ->where('category', $category)
                    ->withPublicPricing()
                    ->orderByDesc('compared_sources_count')
                    ->latest()
                    ->limit(6)
                    ->get()
                    ->map(fn (Tour $tour) => $tour->getAttributes())
                    ->all();

                return ['key' => $category, 'config' => $config, 'items' => $items];
            })->values()->all();
        });

        $categorySections = collect($cachedSections)->map(function (array $section) {
            $section['items'] = collect($section['items'])->map(function (array $attributes) {
                $tour = new Tour();
                $tour->setRawAttributes($attributes, true);

                return $tour;
            });

            return $section;
        });
        $homeSliderAds = $advertisements->forPlacement('home_slider', 8);
        $homeInlineAds = $advertisements->forPlacement('home_inline', 4);
        $category = $categoryConfig = null;
        $structuredData = $seo->home();

        return view('home', compact('categorySections', 'homeSliderAds', 'homeInlineAds', 'category', 'categoryConfig', 'structuredData'));
    }

    public function category(Request $request, AdvertisementManager $advertisements, StructuredDataBuilder $seo): View
    {
        return $this->listing($advertisements, $seo, (string) $request->route('category_key'));
    }

    private function listing(AdvertisementManager $advertisements, StructuredDataBuilder $seo, ?string $category = null): View
    {
        $tours = Tour::query()
            ->published()
            ->when($category, fn ($query) => $query->where('category', $category))
            ->withPublicPricing()
            ->orderByDesc('compared_sources_count')
            ->latest()
            ->paginate(12);
        $homeSliderAds = $advertisements->forPlacement('home_slider', 8);
        $homeInlineAds = $tours->isEmpty()
            ? collect()
            : $advertisements->forPlacement('home_inline', (int) ceil($tours->count() / 9));

        $categoryConfig = $category ? config("comparison.categories.{$category}") : null;
        $canonicalUrl = route($categoryConfig['route'].'.index').'/';
        if ($tours->currentPage() > 1) {
            $canonicalUrl .= '?page='.$tours->currentPage();
        }
        $structuredData = $seo->listing($category, $tours->getCollection(), $canonicalUrl);

        return view('home', compact('tours', 'homeSliderAds', 'homeInlineAds', 'category', 'categoryConfig', 'canonicalUrl', 'structuredData'));
    }

    public function show(
        Request $request,
        Tour $tour,
        TourViewTracker $views,
        AdvertisementManager $advertisements,
        RelatedComparisons $related,
        StructuredDataBuilder $seo,
    ): View {
        $requestedCategory = (string) ($request->route('category_key') ?: 'tour');
        abort_unless($tour->category === $requestedCategory, 404);

        abort_unless($tour->is_active, 404);
        $views->track($tour, $request);
        $comparisonSources = $tour->publicComparisonSources();
        $tour->setRelation('priceSources', $comparisonSources);
        $comparisonContactPhone = SiteSetting::comparisonContactPhone($tour->category);
        $relatedComparisons = $related->for($tour);

        $historyQuery = PriceHistory::query()
            ->whereIn('price_source_id', $comparisonSources->where('latest_price', '>', 0)->pluck('id'))
            ->where('price', '>', 0)
            ->where('is_available', true);
        $oldestTrendDay = (clone $historyQuery)
            ->selectRaw('DATE(observed_at) as trend_day')
            ->distinct()
            ->orderByDesc('trend_day')
            ->limit(30)
            ->pluck('trend_day')
            ->min();

        $priceTrend = (clone $historyQuery)
            ->when($oldestTrendDay, fn ($query) => $query->where('observed_at', '>=', $oldestTrendDay.' 00:00:00'))
            ->when(! $oldestTrendDay, fn ($query) => $query->whereRaw('1 = 0'))
            ->with('source:id,provider_name,currency')
            ->get()
            ->groupBy(fn (PriceHistory $history) => $history->observed_at->toDateString())
            ->map(function ($histories, string $date) {
                $minimum = $histories->map(function (PriceHistory $history) {
                    $priceInTomans = $history->source?->currency === 'ریال'
                        ? (int) round($history->price / 10)
                        : $history->price;

                    return [
                        'date' => $history->observed_at,
                        'price' => $priceInTomans,
                        'provider' => $history->source?->provider_name,
                    ];
                })->sortBy('price')->first();

                return [...$minimum, 'day' => $date];
            })
            ->sortBy('day')
            ->take(-30)
            ->values();
        $trendTopAd = $advertisements->forPlacement('tour_trend_top')->first();
        $offersBottomAd = $advertisements->forPlacement('tour_offers_bottom')->first();
        $structuredData = $seo->detail($tour, $comparisonSources);

        return view('tours.show', compact(
            'tour',
            'priceTrend',
            'trendTopAd',
            'offersBottomAd',
            'comparisonContactPhone',
            'relatedComparisons',
            'structuredData',
        ));
    }
}
