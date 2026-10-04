<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\PriceSource;
use App\Models\Tour;
use App\Services\Alerts\PriceAlertNotifier;
use App\Services\Discovery\ProviderCatalog;
use App\Services\PriceCrawler;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PriceSourceController extends Controller
{
    public function official(Tour $tour, ProviderCatalog $providers): RedirectResponse
    {
        $destination = preg_replace('/^تور(?:های)?\s+/u', '', $tour->title) ?: $tour->title;
        $count = $providers->attach($tour, $destination, 10);

        return back()->with('success', "{$count} منبع پیشنهاد اضافه شدند. برای دریافت قیمت، «بررسی همه قیمت‌ها» را بزنید.");
    }

    public function store(Request $request, Tour $tour, PriceAlertNotifier $alerts): RedirectResponse
    {
        $tour->priceSources()->create($this->validated($request));
        $alerts->notifyForTour($tour);

        return back()->with('success', 'منبع قیمت اضافه شد؛ تنظیمات عمومی آن از صفحه منابع مرکزی مدیریت می‌شود.');
    }

    public function bulkCreate(): View
    {
        $tours = Tour::query()
            ->orderBy('category')
            ->orderBy('title')
            ->get(['id', 'category', 'title', 'slug', 'is_active']);
        $agencyNames = Agency::query()->orderBy('name')->pluck('name');

        return view('admin.sources.bulk-create', compact('tours', 'agencyNames'));
    }

    public function bulkStore(Request $request): RedirectResponse
    {
        $target = $request->validate([
            'target_mode' => ['required', Rule::in(['category', 'selected'])],
            'target_category' => [
                Rule::requiredIf($request->input('target_mode') === 'category'),
                'nullable',
                Rule::in(array_keys(config('comparison.categories'))),
            ],
            'tour_ids' => [
                Rule::requiredIf($request->input('target_mode') === 'selected'),
                'nullable',
                'array',
                'min:1',
                'max:1000',
            ],
            'tour_ids.*' => ['required', 'integer', 'distinct', Rule::exists('tours', 'id')],
        ]);
        $sourceData = $this->validated($request);

        $tours = Tour::query()
            ->when(
                $target['target_mode'] === 'category',
                fn ($query) => $query->where('category', $target['target_category']),
                fn ($query) => $query->whereKey($target['tour_ids'] ?? []),
            )
            ->orderBy('id')
            ->get();

        if ($tours->isEmpty()) {
            return back()->withInput()->with('error', 'هیچ صفحهٔ مقایسه‌ای برای افزودن منبع انتخاب نشده است.');
        }

        $created = 0;
        $skipped = 0;
        DB::transaction(function () use ($tours, $sourceData, &$created, &$skipped) {
            foreach ($tours as $tour) {
                if ($tour->priceSources()->where('provider_name', $sourceData['provider_name'])->exists()) {
                    $skipped++;

                    continue;
                }

                $tour->priceSources()->create($sourceData);
                $created++;
            }
        });

        if ($created === 0) {
            return back()->withInput()->with('error', "این منبع از قبل روی هر {$skipped} صفحهٔ انتخاب‌شده وجود دارد.");
        }

        $message = "منبع {$sourceData['provider_name']} به {$created} صفحهٔ مقایسه اضافه شد.";
        if ($skipped > 0) {
            $message .= " {$skipped} صفحه به‌دلیل وجود قبلی منبع بدون تغییر ماند.";
        }

        return redirect()->route('admin.tours.index')->with('success', $message);
    }

    public function update(Request $request, PriceSource $source, PriceAlertNotifier $alerts): RedirectResponse
    {
        $source->update($this->validated($request));
        $alerts->notifyForTour($source->tour);

        return back()->with('success', 'اطلاعات قیمت و لینک این پیشنهاد به‌روزرسانی شد.');
    }

    public function updateAgencyFeatured(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'provider_name' => ['required', 'string', 'max:120', Rule::exists('agencies', 'name')],
            'is_featured' => ['required', 'boolean'],
        ]);

        $agency = Agency::query()->where('name', $data['provider_name'])->firstOrFail();
        $isFeatured = (bool) $data['is_featured'] || $agency->is_pinned;
        $agency->update(['is_featured' => $isFeatured]);
        $action = $isFeatured ? 'ویژه شدند' : 'از حالت ویژه خارج شدند';

        return back()->with('success', "همه پیشنهادهای آژانس {$agency->name} {$action}.");
    }

    public function destroy(PriceSource $source): RedirectResponse
    {
        $source->delete();

        return back()->with('success', 'منبع قیمت از این پیشنهاد حذف شد.');
    }

    public function crawl(PriceSource $source, PriceCrawler $crawler, PriceAlertNotifier $alerts): RedirectResponse
    {
        $tour = $source->tour;
        $ok = $crawler->crawl($source);
        $alerts->notifyForTour($tour);

        return back()->with(
            $ok ? 'success' : 'error',
            $ok ? 'قیمت با موفقیت خوانده شد.' : 'خواندن قیمت ناموفق بود؛ منبع حفظ شد و با گزینه تماس نمایش داده می‌شود.'
        );
    }

    private function validated(Request $request): array
    {
        $type = $request->input('extraction_type');
        $data = $request->validate([
            'provider_name' => ['required', 'string', 'max:120'],
            'source_url' => ['required', 'url:http,https', 'max:2000'],
            'buy_url' => ['nullable', 'url:http,https', 'max:2000'],
            'extraction_type' => ['required', Rule::in(['alibaba', 'flytoday', 'safarmarket', 'marketplace_html', 'structured', 'regex', 'json', 'manual'])],
            'selector' => [Rule::requiredIf(in_array($type, ['regex', 'json'], true)), 'nullable', 'string', 'max:2000'],
            'price_multiplier' => ['required', 'numeric', 'min:0.01', 'max:100000'],
            'latest_price' => [Rule::requiredIf($type === 'manual'), 'nullable', 'integer', 'min:0'],
            'currency' => ['required', Rule::in(['تومان', 'ریال'])],
            'source_currency' => ['nullable', Rule::in(['auto', 'toman', 'rial', 'usd', 'mixed'])],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $data['source_currency'] = $data['source_currency'] ?? 'auto';
        $data['buy_url'] = $data['buy_url'] ?: $data['source_url'];
        if ($type === 'manual') {
            $data['last_status'] = 'manual';
            $data['last_checked_at'] = now();
        }

        return $data;
    }
}
