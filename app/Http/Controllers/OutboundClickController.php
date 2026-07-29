<?php

namespace App\Http\Controllers;

use App\Jobs\RecoverPriceSourceLink;
use App\Models\PriceSource;
use App\Services\Billing\AgencyBillingService;
use App\Services\Outbound\DestinationLinkValidator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OutboundClickController extends Controller
{
    public function __invoke(
        Request $request,
        PriceSource $source,
        AgencyBillingService $billing,
        DestinationLinkValidator $validator,
    ): RedirectResponse
    {
        abort_unless($source->tour?->is_active, 404);
        if (! $source->is_active && in_array($source->last_status, ['broken_link', 'recovery_failed'], true)) {
            return $this->brokenLinkResponse($source);
        }
        abort_unless($source->is_active, 404);

        $destination = $source->buy_url ?: $source->source_url;
        if (! filter_var($destination, FILTER_VALIDATE_URL)
            || ! in_array(parse_url($destination, PHP_URL_SCHEME), ['http', 'https'], true)) {
            $this->quarantine($source, 'آدرس لینک خرید نامعتبر بود و برای بازیابی در صف قرار گرفت.');

            return $this->brokenLinkResponse($source);
        }

        if ($validator->check($destination) === DestinationLinkValidator::BROKEN) {
            $this->quarantine($source, 'لینک خرید پاسخ ۴۰۴ یا ۴۱۰ داد و برای بازیابی در صف قرار گرفت.');

            return $this->brokenLinkResponse($source);
        }

        if (! $source->agency_id) {
            $source->save();
        }

        $click = $billing->registerClick($source->fresh(), $request);
        if ($click->status === 'insufficient_credit') {
            return redirect()->to($source->tour->publicUrl())
                ->with('error', 'اعتبار این ارائه‌دهنده تمام شده و انتقال به سایت آن موقتاً غیرفعال است.');
        }

        return redirect()->away($click->destination_url);
    }

    private function brokenLinkResponse(PriceSource $source): RedirectResponse
    {
        return redirect()->to($source->tour->publicUrl())
            ->with('error', 'این پیشنهاد منقضی شده و از فهرست حذف شد. لطفاً یکی از لینک‌های دیگر را انتخاب کنید؛ گیت در حال پیدا کردن لینک جایگزین است.');
    }

    private function quarantine(PriceSource $source, string $error): void
    {
        $disabled = PriceSource::query()
            ->whereKey($source->id)
            ->where('is_active', true)
            ->update([
                'is_active' => false,
                'last_status' => 'broken_link',
                'last_error' => $error,
                'last_checked_at' => now(),
            ]);
        if ($disabled) {
            $source->refresh();
            $source->update([
                'rejected_urls' => collect($source->rejected_urls ?? [])
                    ->push($source->buy_url ?: $source->source_url)
                    ->filter()
                    ->unique()
                    ->values()
                    ->all(),
            ]);
            RecoverPriceSourceLink::dispatch($source->id);
        }
    }
}
