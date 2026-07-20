<?php

namespace App\Http\Controllers;

use App\Models\Tour;
use App\Services\Analytics\TourViewTracker;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        $tours = Tour::query()
            ->published()
            ->withPublicPricing()
            ->latest()
            ->paginate(12);

        return view('home', compact('tours'));
    }

    public function show(Request $request, Tour $tour, TourViewTracker $views): View
    {
        abort_unless($tour->is_active, 404);
        $views->track($tour, $request);
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
