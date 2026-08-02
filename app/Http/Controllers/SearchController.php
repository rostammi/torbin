<?php

namespace App\Http\Controllers;

use App\Services\Advertising\AdvertisementManager;
use App\Services\Search\SearchMissTracker;
use App\Services\Search\TourSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SearchController extends Controller
{
    public function index(Request $request, TourSearch $search, SearchMissTracker $misses, AdvertisementManager $advertisements): View
    {
        $term = trim($request->string('q')->toString());
        $intent = $search->intent($term);
        $tours = null;
        if (mb_strlen($term) >= 3) {
            $query = $intent->isRecommendation
                ? $search->recommendations($intent)
                : $search->query($term);
            $tours = $query->paginate(12)->withQueryString();
        }

        if ($tours && $tours->total() === 0 && $tours->currentPage() === 1) {
            $misses->track($term, $request);
        }
        $searchTopAd = $advertisements->forPlacement('search_top')->first();
        $searchResultAd = $tours ? $advertisements->forPlacement('search_result')->first() : null;

        return view('search.index', compact('term', 'intent', 'tours', 'searchTopAd', 'searchResultAd'));
    }

    public function suggestions(Request $request, TourSearch $search): JsonResponse
    {
        $term = trim($request->string('q')->toString());
        if (mb_strlen($term) < 3) {
            return response()->json(['items' => [], 'total' => 0, 'minimum_characters' => 3]);
        }

        $intent = $search->intent($term);
        $query = $intent->isRecommendation
            ? $search->recommendations($intent)
            : $search->query($term);
        $total = (clone $query)->count();
        $items = $query->limit(4)->get()->map(fn ($tour) => [
            'title' => $tour->title,
            'url' => $tour->publicUrl(),
            'excerpt' => $tour->excerpt ?: str($tour->description)->stripTags()->limit(75)->toString(),
            'minimum_price' => $tour->minimum_price,
            'compared_sources_count' => $tour->compared_sources_count,
        ]);

        return response()->json([
            'items' => $items,
            'total' => $total,
            'all_url' => route('search.index', ['q' => $term]),
        ]);
    }
}
