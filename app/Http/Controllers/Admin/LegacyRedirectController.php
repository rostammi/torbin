<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LegacyRedirect;
use App\Models\Tour;
use App\Services\Seo\LegacyRedirectSynchronizer;
use App\Services\Seo\LegacyUrlNormalizer;
use App\Support\AdminTable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LegacyRedirectController extends Controller
{
    public function index(Request $request): View
    {
        $status = in_array($request->string('status')->toString(), ['matched', 'unmatched', 'manual'], true)
            ? $request->string('status')->toString()
            : 'unmatched';
        $term = AdminTable::term($request);
        $query = LegacyRedirect::query()
            ->with('tour')
            ->when($status === 'matched', fn ($query) => $query->whereNotNull('tour_id'))
            ->when($status === 'unmatched', fn ($query) => $query->whereNull('tour_id'))
            ->when($status === 'manual', fn ($query) => $query->where('match_type', 'manual'))
            ->when($term !== '', fn ($query) => $query->where(fn ($search) => $search
                ->where('source_title', 'like', "%{$term}%")
                ->orWhere('old_path', 'like', "%{$term}%")
                ->orWhere('source_url', 'like', "%{$term}%")
                ->orWhere('notes', 'like', "%{$term}%")
                ->orWhereHas('tour', fn ($tour) => $tour->where('title', 'like', "%{$term}%"))));
        AdminTable::sort($query, $request, [
            'source' => 'old_path', 'status' => 'match_type',
            'destination' => fn ($query, $direction) => $query->orderBy(Tour::query()->select('title')->whereColumn('tours.id', 'legacy_redirects.tour_id')->limit(1), $direction),
            'hits' => 'hits', 'updated' => 'updated_at',
        ], [['updated_at', 'desc']]);
        $redirects = $query->paginate(25)->withQueryString();
        $counts = [
            'all' => LegacyRedirect::count(),
            'matched' => LegacyRedirect::whereNotNull('tour_id')->count(),
            'unmatched' => LegacyRedirect::whereNull('tour_id')->count(),
            'manual' => LegacyRedirect::where('match_type', 'manual')->count(),
        ];
        $tours = Tour::query()->orderBy('category')->orderBy('title')->get(['id', 'category', 'title', 'slug']);

        return view('admin.seo-redirects.index', compact('redirects', 'counts', 'status', 'tours'));
    }

    public function store(Request $request, LegacyUrlNormalizer $urls): RedirectResponse
    {
        $data = $request->validate([
            'source_url' => ['required', 'url:http,https', 'max:2000'],
            'tour_id' => ['required', Rule::exists('tours', 'id')],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $source = $urls->source($data['source_url']);
        if (! $source) {
            return back()->withErrors(['source_url' => 'فقط URLهای قدیمی دامنه geyt.ir قابل ثبت هستند.'])->withInput();
        }

        LegacyRedirect::updateOrCreate(['old_path' => $source['path']], [
            'source_url' => $source['url'],
            'tour_id' => $data['tour_id'],
            'match_type' => 'manual',
            'notes' => $data['notes'] ?? null,
        ]);

        return back()->with('success', 'ریدایرکت ۳۰۱ دستی ذخیره شد.');
    }

    public function update(Request $request, LegacyRedirect $legacyRedirect): RedirectResponse
    {
        $data = $request->validate([
            'tour_id' => ['required', Rule::exists('tours', 'id')],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $legacyRedirect->update([
            'tour_id' => $data['tour_id'],
            'match_type' => 'manual',
            'notes' => $data['notes'] ?? $legacyRedirect->notes,
        ]);

        return back()->with('success', 'صفحه قدیمی با پیشنهاد جدید مرج شد.');
    }

    public function sync(LegacyRedirectSynchronizer $synchronizer): RedirectResponse
    {
        $result = $synchronizer->sync();

        return back()->with('success', "{$result['total']} URL قدیمی بررسی شد؛ {$result['matched']} مورد متناظر و {$result['unmatched']} مورد نیازمند مرج دستی است.");
    }
}
