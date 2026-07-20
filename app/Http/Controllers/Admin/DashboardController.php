<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\OutboundClick;
use App\Models\SearchMiss;
use App\Models\Tour;
use App\Models\TourPageView;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $data = $request->validate([
            'period' => ['nullable', Rule::in(['1', '7', '30', 'all'])],
            'agency_id' => ['nullable', 'integer', Rule::exists('agencies', 'id')],
        ]);
        $period = $data['period'] ?? '30';
        $since = $period === 'all' ? null : now()->subDays((int) $period);
        $user = $request->user();
        $agencyId = $user->isAdmin() ? ($data['agency_id'] ?? null) : $user->agency_id;
        abort_if(! $user->isAdmin() && ! $agencyId, 403);

        $tourQuery = Tour::query()->orderBy('title');
        if ($agencyId) {
            $tourQuery->whereHas('priceSources', fn ($query) => $query->where('agency_id', $agencyId));
        }

        $tourQuery->withMin([
            'priceSources as public_minimum_price' => fn ($query) => $query
                ->where('is_active', true)
                ->funded()
                ->where('latest_price', '>', 0),
        ], 'latest_price');
        if ($agencyId) {
            $tourQuery->withMin([
                'priceSources as agency_price' => fn ($query) => $query
                    ->where('agency_id', $agencyId)
                    ->where('is_active', true)
                    ->where('latest_price', '>', 0),
            ], 'latest_price');
        }

        $tours = $tourQuery
            ->withCount(['pageViews as views_count' => fn ($query) => $this->withinPeriod($query, 'viewed_at', $since)])
            ->withCount(['outboundClicks as clicks_count' => function ($query) use ($agencyId, $since) {
                $query->whereIn('status', ['charged', 'free']);
                if ($agencyId) {
                    $query->where('agency_id', $agencyId);
                }
                $this->withinPeriod($query, 'clicked_at', $since);
            }])
            ->withSum(['outboundClicks as click_cost' => function ($query) use ($agencyId, $since) {
                if ($agencyId) {
                    $query->where('agency_id', $agencyId);
                }
                $this->withinPeriod($query, 'clicked_at', $since);
            }], 'charged_amount')
            ->get();

        $tourIds = $tours->pluck('id');
        $viewsTotal = TourPageView::query()->whereIn('tour_id', $tourIds)
            ->when($since, fn ($query) => $query->where('viewed_at', '>=', $since))->count();
        $clicksQuery = OutboundClick::query()->whereIn('tour_id', $tourIds)->whereIn('status', ['charged', 'free'])
            ->when($agencyId, fn ($query) => $query->where('agency_id', $agencyId))
            ->when($since, fn ($query) => $query->where('clicked_at', '>=', $since));
        $clicksTotal = (clone $clicksQuery)->count();
        $costTotal = (int) (clone $clicksQuery)->sum('charged_amount');
        $potentialKeywords = $user->isAdmin()
            ? SearchMiss::query()
                ->selectRaw('normalized_query, normalized_query as keyword, COUNT(*) as searches_count, COUNT(DISTINCT ip_hash) as visitors_count, MAX(searched_at) as last_searched_at')
                ->when($since, fn ($query) => $query->where('searched_at', '>=', $since))
                ->groupBy('normalized_query')
                ->orderByDesc('searches_count')
                ->orderByDesc('last_searched_at')
                ->limit(20)
                ->get()
            : collect();

        return view('admin.dashboard', [
            'tours' => $tours,
            'period' => $period,
            'agencies' => $user->isAdmin() ? Agency::orderBy('name')->get() : collect(),
            'selectedAgency' => $agencyId ? Agency::find($agencyId) : null,
            'viewsTotal' => $viewsTotal,
            'clicksTotal' => $clicksTotal,
            'costTotal' => $costTotal,
            'conversionTotal' => $viewsTotal > 0 ? ($clicksTotal / $viewsTotal) * 100 : 0,
            'potentialKeywords' => $potentialKeywords,
        ]);
    }

    private function withinPeriod($query, string $column, $since): mixed
    {
        return $since ? $query->where($column, '>=', $since) : $query;
    }
}
