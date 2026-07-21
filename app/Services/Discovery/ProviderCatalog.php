<?php

namespace App\Services\Discovery;

use App\Models\Tour;

class ProviderCatalog
{
    public function attach(Tour $tour, string $destination, int $limit = 10): int
    {
        $providers = collect(config('crawler.providers', []))->take(max(4, min(10, $limit)));

        foreach ($providers as $provider) {
            $url = $provider['url'];
            if ($provider['type'] === 'structured') {
                $url .= (str_contains($url, '?') ? '&' : '?').'q='.rawurlencode($destination);
            }

            $tour->priceSources()->updateOrCreate(['provider_name' => $provider['name']], [
                'source_url' => $url,
                'buy_url' => $url,
                'extraction_type' => $provider['type'],
                'selector' => $destination,
                'price_multiplier' => 1,
                'currency' => 'تومان',
                'is_active' => true,
            ]);
        }

        return $providers->count();
    }
}
