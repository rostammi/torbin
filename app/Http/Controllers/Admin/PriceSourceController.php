<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PriceSource;
use App\Models\Tour;
use App\Services\PriceCrawler;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PriceSourceController extends Controller
{
    public function official(Tour $tour): RedirectResponse
    {
        $sources = [
            'علی‌بابا' => ['alibaba', 'https://www.alibaba.ir/tour'],
            'فلای‌تودی' => ['flytoday', 'https://www.flytoday.ir/packagetour'],
            'سفرمارکت' => ['safarmarket', 'https://safarmarket.com/tours'],
        ];

        foreach ($sources as $provider => [$type, $url]) {
            $tour->priceSources()->updateOrCreate(['provider_name' => $provider], [
                'source_url' => $url,
                'buy_url' => $url,
                'extraction_type' => $type,
                'selector' => null,
                'price_multiplier' => 1,
                'currency' => 'تومان',
                'is_active' => true,
                'latest_price' => null,
                'last_status' => null,
                'last_error' => null,
            ]);
        }

        return back()->with('success', 'سه منبع رسمی اضافه شدند. برای دریافت قیمت، «بررسی همه قیمت‌ها» را بزنید.');
    }

    public function store(Request $request, Tour $tour): RedirectResponse
    {
        $tour->priceSources()->create($this->validated($request));

        return back()->with('success', 'منبع قیمت اضافه شد.');
    }

    public function update(Request $request, PriceSource $source): RedirectResponse
    {
        $source->update($this->validated($request));

        return back()->with('success', 'منبع قیمت به‌روزرسانی شد.');
    }

    public function destroy(PriceSource $source): RedirectResponse
    {
        $source->delete();

        return back()->with('success', 'منبع قیمت حذف شد.');
    }

    public function crawl(PriceSource $source, PriceCrawler $crawler): RedirectResponse
    {
        $ok = $crawler->crawl($source);

        return back()->with($ok ? 'success' : 'error', $ok ? 'قیمت با موفقیت خوانده شد.' : 'خواندن قیمت ناموفق بود؛ جزئیات را در وضعیت منبع ببینید.');
    }

    private function validated(Request $request): array
    {
        $type = $request->input('extraction_type');
        $data = $request->validate([
            'provider_name' => ['required', 'string', 'max:120'],
            'source_url' => ['required', 'url:http,https', 'max:2000'],
            'buy_url' => ['nullable', 'url:http,https', 'max:2000'],
            'extraction_type' => ['required', Rule::in(['alibaba', 'flytoday', 'safarmarket', 'regex', 'json', 'manual'])],
            'selector' => [Rule::requiredIf(in_array($type, ['regex', 'json'], true)), 'nullable', 'string', 'max:2000'],
            'price_multiplier' => ['required', 'numeric', 'min:0.01', 'max:100000'],
            'latest_price' => [Rule::requiredIf($type === 'manual'), 'nullable', 'integer', 'min:0'],
            'currency' => ['required', Rule::in(['تومان', 'ریال'])],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $data['buy_url'] = $data['buy_url'] ?: $data['source_url'];
        if ($type === 'manual') {
            $data['last_status'] = 'manual';
            $data['last_checked_at'] = now();
        }

        return $data;
    }
}
