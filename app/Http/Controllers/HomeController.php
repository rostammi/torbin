<?php

namespace App\Http\Controllers;

use App\Models\Tour;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        $tours = Tour::query()
            ->published()
            ->withMin(['priceSources as minimum_price' => fn ($query) => $query->where('is_active', true)->funded()->where('latest_price', '>', 0)], 'latest_price')
            ->withCount(['priceSources as compared_sources_count' => fn ($query) => $query->where('is_active', true)->funded()->whereNotNull('latest_price')])
            ->latest()
            ->paginate(12);

        return view('home', compact('tours'));
    }

    public function show(Tour $tour): View
    {
        abort_unless($tour->is_active, 404);
        $tour->load(['priceSources' => fn ($query) => $query
            ->where('is_active', true)
            ->funded()
            ->whereNotNull('latest_price')
            ->orderByRaw('case when latest_price = 0 then 1 else 0 end')
            ->orderBy('latest_price')
            ->with(['recentHistory', 'agency'])]);

        return view('tours.show', compact('tour'));
    }
}
