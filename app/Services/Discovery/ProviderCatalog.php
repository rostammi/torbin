<?php

namespace App\Services\Discovery;

use App\Models\Tour;

class ProviderCatalog
{
    public function attach(Tour $tour, string $destination, int $limit = 10): int
    {
        $configured = $tour->category === 'tour'
            ? config('crawler.providers', [])
            : config("comparison.providers.{$tour->category}", []);
        $providers = collect($configured)->take(min($limit, count($configured)));

        foreach ($providers as $provider) {
            $this->attachProvider($tour, $destination, $provider);
        }

        return $providers->count();
    }

    public function attachProvider(Tour $tour, string $destination, array $provider)
    {
        $url = $provider['url'];

        return $tour->priceSources()->updateOrCreate(['provider_name' => $provider['name']], [
            'source_url' => $url,
            'buy_url' => $url,
            'extraction_type' => $provider['type'],
            'selector' => $destination,
            'price_multiplier' => 1,
            'currency' => 'تومان',
            'is_active' => true,
        ]);
    }
}
