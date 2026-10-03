<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\ContactClick;
use App\Models\OutboundClick;
use App\Models\SearchMiss;
use App\Models\Tour;
use App\Models\TourPageView;
use App\Support\AdminTable;
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

        $term = AdminTable::term($request);
        $tourQuery = Tour::query()
            ->when($term !== '', fn ($query) => $query->where(fn ($search) => $search
                ->where('title', 'like', "%{$term}%")
                ->orWhere('slug', 'like', "%{$term}%")));
        if ($agencyId) {
            $tourQuery->whereHas('priceSources', fn ($query) => $query->where('agency_id', $agencyId));
        }

        $tourIds = (clone $tourQuery)->pluck('tours.id');

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

        $tourQuery
            ->withCount(['pageViews as views_count' => fn ($query) => $this->withinPeriod($query, 'viewed_at', $since)])
            ->withCount(['outboundClicks as clicks_count' => function ($query) use ($agencyId, $since) {
                $query->whereIn('status', ['charged', 'free']);
                if ($agencyId) {
                    $query->where('agency_id', $agencyId);
                }
                $this->withinPeriod($query, 'clicked_at', $since);
            }])
            ->withCount(['contactClicks as contact_clicks_count' => function ($query) use ($agencyId, $since) {
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
            }], 'charged_amount');
        AdminTable::sort($tourQuery, $request, [
            'title' => 'title', 'minimum_price' => 'public_minimum_price', 'agency_price' => 'agency_price',
            'views' => 'views_count', 'clicks' => 'clicks_count', 'contact_clicks' => 'contact_clicks_count',
            'gap' => fn ($query, $direction) => $query->orderByRaw("(agency_price - public_minimum_price) {$direction}"),
            'conversion' => fn ($query, $direction) => $query->orderByRaw("(clicks_count * 1.0 / NULLIF(views_count, 0)) {$direction}"),
            'cost' => 'click_cost',
        ], [['title', 'asc']]);
        $tours = $tourQuery->paginate(20)->withQueryString();

        $viewsTotal = TourPageView::query()->whereIn('tour_id', $tourIds)
            ->when($since, fn ($query) => $query->where('viewed_at', '>=', $since))->count();
        $clicksQuery = OutboundClick::query()->whereIn('tour_id', $tourIds)->whereIn('status', ['charged', 'free'])
            ->when($agencyId, fn ($query) => $query->where('agency_id', $agencyId))
            ->when($since, fn ($query) => $query->where('clicked_at', '>=', $since));
        $clicksTotal = (clone $clicksQuery)->count();
        $costTotal = (int) (clone $clicksQuery)->sum('charged_amount');
        $contactClicksQuery = ContactClick::query()->whereIn('tour_id', $tourIds)
            ->when($agencyId, fn ($query) => $query->where('agency_id', $agencyId))
            ->when($since, fn ($query) => $query->where('clicked_at', '>=', $since));
        $contactClicksTotal = (clone $contactClicksQuery)->count();
        $generalContactClicksTotal = (clone $contactClicksQuery)
            ->where('contact_type', ContactClick::TYPE_GENERAL)
            ->count();
        $contactTerm = AdminTable::term($request, 'q_contact');
        $agencyContactQuery = (clone $contactClicksQuery)
            ->where('contact_type', ContactClick::TYPE_AGENCY)
            ->selectRaw('agency_id, COUNT(*) as clicks_count')
            ->with('agency:id,name')
            ->when($contactTerm !== '', fn ($query) => $query->whereHas('agency', fn ($agency) => $agency->where('name', 'like', "%{$contactTerm}%")))
            ->groupBy('agency_id');
        AdminTable::sort($agencyContactQuery, $request, [
            'agency' => fn ($query, $direction) => $query->orderBy(Agency::query()->select('name')->whereColumn('agencies.id', 'contact_clicks.agency_id')->limit(1), $direction),
            'clicks' => 'clicks_count',
        ], [['clicks_count', 'desc']], 'sort_contact', 'direction_contact');
        $agencyContactClicks = $agencyContactQuery->get();
        $showGeneralContactClicks = $generalContactClicksTotal > 0
            && ($contactTerm === '' || str_contains('گیت شماره عمومی', $contactTerm));

        $keywordTerm = AdminTable::term($request, 'q_keyword');
        $potentialKeywords = $user->isAdmin()
            ? tap(SearchMiss::query()
                ->selectRaw('normalized_query, normalized_query as keyword, COUNT(*) as searches_count, COUNT(DISTINCT ip_hash) as visitors_count, MAX(searched_at) as last_searched_at')
                ->when($since, fn ($query) => $query->where('searched_at', '>=', $since))
                ->when($keywordTerm !== '', fn ($query) => $query->where('normalized_query', 'like', "%{$keywordTerm}%"))
                ->groupBy('normalized_query'), fn ($query) => AdminTable::sort($query, $request, [
                    'keyword' => 'normalized_query', 'searches' => 'searches_count',
                    'visitors' => 'visitors_count', 'last_search' => 'last_searched_at',
                ], [['searches_count', 'desc'], ['last_searched_at', 'desc']], 'sort_keyword', 'direction_keyword'))
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
            'contactClicksTotal' => $contactClicksTotal,
            'generalContactClicksTotal' => $generalContactClicksTotal,
            'showGeneralContactClicks' => $showGeneralContactClicks,
            'agencyContactClicks' => $agencyContactClicks,
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
