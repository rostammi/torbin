<?php

namespace App\Http\Controllers;

use App\Models\PriceSource;
use App\Services\Billing\AgencyBillingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OutboundClickController extends Controller
{
    public function __invoke(Request $request, PriceSource $source, AgencyBillingService $billing): RedirectResponse
    {
        abort_unless($source->is_active && $source->tour?->is_active, 404);

        $destination = $source->buy_url ?: $source->source_url;
        abort_unless(filter_var($destination, FILTER_VALIDATE_URL) && in_array(parse_url($destination, PHP_URL_SCHEME), ['http', 'https'], true), 404);

        if (! $source->agency_id) {
            $source->save();
        }

        $click = $billing->registerClick($source->fresh(), $request);
        if ($click->status === 'insufficient_credit') {
            return redirect()->route('tours.show', $source->tour)
                ->with('error', 'اعتبار این ارائه‌دهنده تمام شده و انتقال به سایت آن موقتاً غیرفعال است.');
        }

        return redirect()->away($click->destination_url);
    }
}
