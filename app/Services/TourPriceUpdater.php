<?php

namespace App\Services;

use App\Models\Tour;
use App\Models\TourSuggestion;
use App\Services\Discovery\ProviderCatalog;

class TourPriceUpdater
{
    public const PRIMARY_PROVIDER_COUNT = 10;

    public const MINIMUM_PRICES = 3;

    public function __construct(
        private readonly ProviderCatalog $providers,
        private readonly PriceCrawler $crawler,
    ) {}

    public function update(Tour $tour): array
    {
        $destination = $this->destination($tour);
        $primaryProviders = collect(config('crawler.providers', []))
            ->take(self::PRIMARY_PROVIDER_COUNT)
            ->values();
        $this->providers->attach($tour, $destination, self::PRIMARY_PROVIDER_COUNT);

        $checked = 0;
        $crawlSuccessful = 0;
        $failedSourcesRemoved = 0;
        $pricesFound = 0;
        foreach ($primaryProviders as $provider) {
            $source = $tour->priceSources()
                ->where('provider_name', $provider['name'])
                ->where('is_active', true)
                ->where('extraction_type', '!=', 'manual')
                ->first();
            if (! $source) {
                continue;
            }

            $checked++;
            if ($this->crawler->crawl($source)) {
                $crawlSuccessful++;
            } else {
                $failedSourcesRemoved++;

                continue;
            }
            $source->refresh();
            if ($source->last_status === 'success' && (int) $source->latest_price > 0) {
                $pricesFound++;
            }
        }

        $fallbackChecked = 0;
        $fallbackProviders = [];
        if ($pricesFound < self::MINIMUM_PRICES) {
            foreach (config('crawler.fallback_providers', []) as $provider) {
                $source = $this->providers->attachProvider($tour, $destination, $provider);
                $checked++;
                $fallbackChecked++;
                $fallbackProviders[] = $provider['name'];
                if ($this->crawler->crawl($source)) {
                    $crawlSuccessful++;
                } else {
                    $failedSourcesRemoved++;

                    continue;
                }
                $source->refresh();
                if ($source->last_status === 'success' && (int) $source->latest_price > 0) {
                    $pricesFound++;
                }
                if ($pricesFound >= self::MINIMUM_PRICES) {
                    break;
                }
            }
        }

        return [
            'primary_expected' => self::PRIMARY_PROVIDER_COUNT,
            'primary_checked' => $checked - $fallbackChecked,
            'fallback_checked' => $fallbackChecked,
            'checked' => $checked,
            'crawl_successful' => $crawlSuccessful,
            'failed_sources_removed' => $failedSourcesRemoved,
            'prices_found' => $pricesFound,
            'minimum_prices' => self::MINIMUM_PRICES,
            'target_met' => $pricesFound >= self::MINIMUM_PRICES,
            'fallback_providers' => $fallbackProviders,
            'needs_new_crawler' => $pricesFound < self::MINIMUM_PRICES,
        ];
    }

    private function destination(Tour $tour): string
    {
        $destination = TourSuggestion::query()
            ->where('tour_id', $tour->id)
            ->whereNotNull('destination')
            ->value('destination');

        return trim((string) ($destination ?: preg_replace(
            '/(?:^|\s)(?:تور|ارزان|لحظه آخری|اقساطی|هوایی|مقایسه قیمت|خرید)(?=\s|$)|[|\-–—].*$/u',
            ' ',
            $tour->title,
        )));
    }
}
