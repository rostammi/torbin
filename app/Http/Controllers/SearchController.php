<?php

namespace App\Http\Controllers;

use App\Services\Search\TourSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SearchController extends Controller
{
    public function index(Request $request, TourSearch $search): View
    {
        $term = trim($request->string('q')->toString());
        $tours = mb_strlen($term) >= 3
            ? $search->query($term)->paginate(12)->withQueryString()
            : null;

        return view('search.index', compact('term', 'tours'));
    }

    public function suggestions(Request $request, TourSearch $search): JsonResponse
    {
        $term = trim($request->string('q')->toString());
        if (mb_strlen($term) < 3) {
            return response()->json(['items' => [], 'total' => 0, 'minimum_characters' => 3]);
        }

        $query = $search->query($term);
        $total = (clone $query)->count();
        $items = $query->limit(4)->get()->map(fn ($tour) => [
            'title' => $tour->title,
            'url' => route('tours.show', $tour),
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
