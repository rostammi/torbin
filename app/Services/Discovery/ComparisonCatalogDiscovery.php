<?php

namespace App\Services\Discovery;

use App\Models\TourSuggestion;

class ComparisonCatalogDiscovery
{
    public function __construct(private readonly PopularTourDiscovery $tours) {}

    public function discover(): array
    {
        $tourResult = $this->tours->discover();
        $results = ['tour' => $tourResult];
        $total = $tourResult['total'];

        foreach (['hotel', 'stay', 'visa'] as $category) {
            $results[$category] = $this->discoverCategory($category);
            $total += $results[$category]['total'];
        }

        return ['total' => $total, 'categories' => $results];
    }

    public function discoverCategory(string $category): array
    {
        abort_unless(in_array($category, ['hotel', 'stay', 'visa'], true), 404);
        $saved = [];

        foreach (config("comparison.catalogs.{$category}", []) as $index => $destination) {
            $keywords = $this->keywords($category, $destination);
            $suggestion = TourSuggestion::updateOrCreate(
                ['category' => $category, 'destination' => $destination],
                [
                    'keyword' => $keywords[0],
                    'suggested_title' => $this->title($category, $destination),
                    'trend_score' => max(40, 90 - $index),
                    'source' => "{$category}_catalog",
                    'status' => 'pending',
                    'metadata' => [
                        'keywords' => $keywords,
                        'variant' => 'main',
                        'image_query' => $this->imageQuery($category, $destination),
                        'seeded' => true,
                    ],
                    'discovered_at' => now(),
                ],
            );
            $saved[] = $suggestion->id;
        }

        TourSuggestion::query()
            ->where('category', $category)
            ->where('source', "{$category}_catalog")
            ->whereNotIn('id', $saved)
            ->delete();

        return ['category' => $category, 'total' => count($saved), 'ids' => $saved];
    }

    public function keywords(string $category, string $destination): array
    {
        return match ($category) {
            'hotel' => [
                "هتل {$destination}",
                "رزرو هتل {$destination}",
                "هتل ارزان {$destination}",
                "قیمت هتل {$destination}",
            ],
            'stay' => [
                "اقامتگاه {$destination}",
                "رزرو اقامتگاه {$destination}",
                "اقامتگاه بوم‌گردی {$destination}",
                "اقامتگاه ارزان {$destination}",
            ],
            'visa' => [
                "ویزای {$destination}",
                "قیمت ویزای {$destination}",
                "شرایط ویزای {$destination}",
                "اخذ ویزای {$destination}",
            ],
        };
    }

    public function title(string $category, string $destination): string
    {
        return match ($category) {
            'hotel' => "هتل {$destination} | مقایسه قیمت و رزرو",
            'stay' => "اقامتگاه {$destination} | مقایسه قیمت و رزرو",
            'visa' => "ویزای {$destination} | مقایسه هزینه و خدمات",
        };
    }

    private function imageQuery(string $category, string $destination): string
    {
        $alias = (string) data_get(config('crawler.images.aliases', []), $destination, $destination);

        return match ($category) {
            'hotel' => "{$alias} hotel",
            'stay' => "{$alias} traditional house accommodation",
            'visa' => "{$alias} passport visa travel",
        };
    }
}
