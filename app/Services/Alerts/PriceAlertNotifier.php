<?php

namespace App\Services\Alerts;

use App\Models\Tour;
use Throwable;

class PriceAlertNotifier
{
    public function __construct(private readonly SmsSender $sms) {}

    public function notifyForTour(Tour $tour): int
    {
        $offer = $tour->priceSources()
            ->where('is_active', true)
            ->funded()
            ->where('latest_price', '>', 0)
            ->orderBy('latest_price')
            ->first();

        if (! $offer) {
            return 0;
        }

        $alerts = $tour->priceAlerts()
            ->where('is_active', true)
            ->where('target_price', '>', $offer->latest_price)
            ->get();
        $sent = 0;

        foreach ($alerts as $alert) {
            try {
                $this->sms->send($alert->phone, $this->message($tour, $offer->latest_price, $offer->currency, $alert->unsubscribe_token));
                $alert->update([
                    'target_price' => $offer->latest_price,
                    'currency' => $offer->currency,
                    'last_notified_at' => now(),
                    'last_notified_price' => $offer->latest_price,
                    'last_error' => null,
                ]);
                $sent++;
            } catch (Throwable $exception) {
                $alert->update(['last_error' => mb_substr($exception->getMessage(), 0, 1000)]);
                report($exception);
            }
        }

        return $sent;
    }

    private function message(Tour $tour, int $price, string $currency, string $token): string
    {
        return "کاهش قیمت {$tour->title}: اکنون ".number_format($price)." {$currency}\n".
            route('tours.show', $tour)."\nلغو: ".route('price-alerts.unsubscribe', $token);
    }
}
