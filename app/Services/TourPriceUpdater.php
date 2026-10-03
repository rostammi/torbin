<?php

namespace App\Services;

use App\Models\Tour;
use App\Models\TourSuggestion;
use App\Services\Discovery\ProviderCatalog;
use DateTimeInterface;

class TourPriceUpdater
{
    public const PRIMARY_PROVIDER_COUNT = 10;

    public const MINIMUM_PRICES = 3;

    public function __construct(
        private readonly ProviderCatalog $providers,
        private readonly PriceCrawler $crawler,
    ) {}

    public function update(Tour $tour, ?DateTimeInterface $skipCheckedSince = null): array
    {
        $destination = $this->destination($tour);
        $configuredProviders = $tour->category === 'tour'
            ? config('crawler.providers', [])
            : config("comparison.providers.{$tour->category}", []);
        $this->providers->attach($tour, $destination, self::PRIMARY_PROVIDER_COUNT);

        $checked = 0;
        $crawlSuccessful = 0;
        $failedSourcesRetained = 0;
        $primaryChecked = 0;
        $fallbackChecked = 0;
        $fallbackProviders = [];
        $maxSources = max(self::MINIMUM_PRICES, (int) config('crawler.price_sync_max_sources_per_tour', 5));
        $sources = $tour->priceSources()
            ->where('is_active', true)
            ->where('extraction_type', '!=', 'manual')
            ->get();

        $alreadyChecked = $skipCheckedSince
            ? $sources->filter(fn ($source) => $source->last_checked_at?->gte($skipCheckedSince))
            : collect();
        $checkedSourceIds = $alreadyChecked->pluck('id')->all();
        $pricesFound = $alreadyChecked->filter(
            fn ($source) => $source->last_status === 'success' && (int) $source->latest_price > 0,
        )->count();
        $availableBudget = max(0, $maxSources - $alreadyChecked->count());
        $fallbackConfig = $tour->category === 'tour'
            ? config('crawler.fallback_providers', [])
            : config("comparison.fallback_providers.{$tour->category}", []);
        $primaryBudget = max(0, $availableBudget - ($fallbackConfig === [] ? 0 : 1));
        $candidates = $sources->whereNotIn('id', $checkedSourceIds);
        $reliable = $candidates
            ->filter(fn ($source) => $source->last_status === 'success' && (int) $source->latest_price > 0)
            ->sortBy(fn ($source) => [$source->last_checked_at?->timestamp ?? 0, $source->id])
            ->take(min(self::MINIMUM_PRICES, $primaryBudget));
        $audit = $candidates
            ->whereNotIn('id', $reliable->pluck('id'))
            ->sortBy(fn ($source) => [$source->last_checked_at?->timestamp ?? 0, $source->id])
            ->take(max(0, $primaryBudget - $reliable->count()));

        foreach ($reliable->concat($audit) as $source) {
            $checked++;
            $primaryChecked++;
            $checkedSourceIds[] = $source->id;
            if ($this->crawler->crawl($source, false, false)) {
                $crawlSuccessful++;
                $source->refresh();
                if ($source->last_status === 'success' && (int) $source->latest_price > 0) {
                    $pricesFound++;
                }
            } else {
                $failedSourcesRetained++;
            }
        }

        $remainingBudget = max(0, $maxSources - $alreadyChecked->count() - $checked);
        if ($pricesFound < self::MINIMUM_PRICES && $remainingBudget > 0) {
            foreach ($fallbackConfig as $provider) {
                $source = $this->providers->attachProvider($tour, $destination, $provider);
                if (in_array($source->id, $checkedSourceIds, true)) {
                    continue;
                }

                $checked++;
                $fallbackChecked++;
                $checkedSourceIds[] = $source->id;
                $fallbackProviders[] = $provider['name'];
                if ($this->crawler->crawl($source, false, false)) {
                    $crawlSuccessful++;
                    $source->refresh();
                    if ($source->last_status === 'success' && (int) $source->latest_price > 0) {
                        $pricesFound++;
                    }
                } else {
                    $failedSourcesRetained++;
                }
                if ($pricesFound >= self::MINIMUM_PRICES || --$remainingBudget <= 0) {
                    break;
                }
            }
        }

        return [
            'primary_expected' => self::PRIMARY_PROVIDER_COUNT,
            'primary_checked' => $primaryChecked,
            'fallback_checked' => $fallbackChecked,
            'checked' => $checked,
            'crawl_successful' => $crawlSuccessful,
            'failed_sources_retained' => $failedSourcesRetained,
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

        if ($destination) {
            return trim((string) $destination);
        }

        $noise = match ($tour->category) {
            'hotel' => 'هتل|رزرو|ارزان|قیمت',
            'stay' => 'اقامتگاه|بوم‌گردی|رزرو|ارزان|قیمت',
            'visa' => 'ویزا|ویزای|شرایط|اخذ|هزینه|خدمات|قیمت',
            default => 'تور|ارزان|لحظه آخری|اقساطی|هوایی|مقایسه قیمت|خرید',
        };

        return trim((string) preg_replace(
            "/(?:^|\\s)(?:{$noise})(?=\\s|$)|[|\\-–—].*$/u",
            ' ',
            $tour->title,
        ));
    }
}
