<?php

namespace App\Http\Controllers;

use App\Models\Agency;
use App\Models\Tour;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProviderController extends Controller
{
    public function show(Request $request, string $provider): View
    {
        $agencies = Agency::query()
            ->get()
            ->filter(fn (Agency $agency) => $agency->providerSlug() === $provider)
            ->values();
        abort_if($agencies->isEmpty(), 404);

        $providerName = $agencies->sortBy(fn (Agency $agency) => mb_strlen($agency->providerName()))
            ->first()
            ->providerName();
        $agencyIds = $agencies->pluck('id');
        $categories = config('comparison.categories');
        $category = trim($request->string('category')->toString());
        abort_if($category !== '' && ! array_key_exists($category, $categories), 404);

        $offeredByProvider = fn (Builder $query) => $query
            ->whereIn('agency_id', $agencyIds)
            ->where('is_active', true)
            ->funded()
            ->where('latest_price', '>', 0);

        $categoryCounts = collect($categories)->map(fn (array $config, string $key) => Tour::query()
            ->published()
            ->where('category', $key)
            ->whereHas('priceSources', $offeredByProvider)
            ->count());

        $items = Tour::query()
            ->published()
            ->when($category !== '', fn (Builder $query) => $query->where('category', $category))
            ->whereHas('priceSources', $offeredByProvider)
            ->withPublicPricing()
            ->orderByDesc('compared_sources_count')
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('providers.show', compact(
            'providerName', 'provider', 'categories', 'category', 'categoryCounts', 'items',
        ));
    }
}
