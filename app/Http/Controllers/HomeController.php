<?php

namespace App\Http\Controllers;

use App\Models\PriceHistory;
use App\Models\Tour;
use App\Services\Advertising\AdvertisementManager;
use App\Services\Analytics\TourViewTracker;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(AdvertisementManager $advertisements): View
    {
        $tours = Tour::query()
            ->published()
            ->withPublicPricing()
            ->latest()
            ->paginate(12);
        $homeSliderAds = $advertisements->forPlacement('home_slider', 8);
        $homeInlineAds = $tours->isEmpty()
            ? collect()
            : $advertisements->forPlacement('home_inline', (int) ceil($tours->count() / 9));

        return view('home', compact('tours', 'homeSliderAds', 'homeInlineAds'));
    }

    public function show(Request $request, Tour $tour, TourViewTracker $views, AdvertisementManager $advertisements): View
    {
        abort_unless($tour->is_active, 404);
        $views->track($tour, $request);
        $tour->load(['priceSources' => fn ($query) => $query
            ->where('is_active', true)
            ->funded()
            ->where('latest_price', '>', 0)
            ->orderBy('latest_price')
            ->with('agency')]);

        $historyQuery = PriceHistory::query()
            ->whereIn('price_source_id', $tour->priceSources->pluck('id'))
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

        return view('tours.show', compact('tour', 'priceTrend', 'trendTopAd', 'offersBottomAd'));
    }
}
