<?php

namespace App\Services\Discovery;

use App\Models\TourSuggestion;

class GeytReferenceCatalogDiscovery
{
    public function discover(): array
    {
        $summary = ['total' => 0, 'created' => 0, 'merged' => 0, 'categories' => []];

        foreach (config('geyt_reference.catalogs', []) as $category => $labels) {
            $categorySummary = ['total' => 0, 'created' => 0, 'merged' => 0];
            foreach (array_values(array_unique($labels)) as $label) {
                $destination = $this->destination($category, $label);
                $suggestion = TourSuggestion::query()
                    ->where('category', $category)
                    ->where('destination', $destination)
                    ->first();
                $created = $suggestion === null;
                $keywords = $category === 'tour'
                    ? collect(PopularTourDiscovery::VARIANTS)
                        ->map(fn (string $template) => sprintf($template, $destination))
                        ->push($label)->unique()->values()->all()
                    : collect($this->keywords($category, $destination))
                        ->push($label)->unique()->values()->all();

                if (! $suggestion) {
                    $suggestion = TourSuggestion::create([
                        'category' => $category,
                        'keyword' => $keywords[0],
                        'suggested_title' => $this->title($category, $destination),
                        'destination' => $destination,
                        'trend_score' => 70,
                        'source' => 'geyt_reference_catalog',
                        'status' => 'pending',
                        'metadata' => [],
                        'discovered_at' => now(),
                    ]);
                }

                $metadata = $suggestion->metadata ?? [];
                $metadata['keywords'] = collect($metadata['keywords'] ?? [])->concat($keywords)->unique()->values()->all();
                $reference = [
                    'label' => $label,
                    'category_url' => $this->categoryUrl($category),
                    'synced_at' => now()->toIso8601String(),
                ];
                $metadata['geyt_references'] = collect($metadata['geyt_references'] ?? [])
                    ->reject(fn (array $item) => ($item['label'] ?? null) === $label)
                    ->push($reference)
                    ->values()
                    ->all();
                $suggestion->update(['metadata' => $metadata, 'discovered_at' => now()]);

                $categorySummary['total']++;
                $categorySummary[$created ? 'created' : 'merged']++;
            }
            $summary['categories'][$category] = $categorySummary;
            foreach (['total', 'created', 'merged'] as $key) {
                $summary[$key] += $categorySummary[$key];
            }
        }

        return $summary;
    }

    private function destination(string $category, string $label): string
    {
        if ($mapped = config("geyt_reference.canonical.{$label}")) {
            return $mapped;
        }

        $destination = match ($category) {
            'tour' => preg_replace('/^تور\s+/u', '', $label),
            'hotel' => preg_replace('/^(?:رزرو\s+)?هتل(?:‌ها|‌های|\s+های)?\s+/u', '', $label),
            'visa' => preg_replace('/^ویزای\s+/u', '', $label),
            default => $label,
        };

        return trim(preg_replace('/\s+/u', ' ', (string) $destination) ?? '');
    }

    private function title(string $category, string $destination): string
    {
        return $category === 'tour'
            ? "تور {$destination} | مقایسه قیمت و خرید از معتبرترین آژانس‌ها"
            : match ($category) {
                'hotel' => "هتل {$destination} | مقایسه قیمت و رزرو",
                'stay' => "اقامتگاه {$destination} | مقایسه قیمت و رزرو",
                'visa' => "ویزای {$destination} | مقایسه هزینه و خدمات",
            };
    }

    private function keywords(string $category, string $destination): array
    {
        return match ($category) {
            'hotel' => ["هتل {$destination}", "رزرو هتل {$destination}", "هتل ارزان {$destination}", "قیمت هتل {$destination}"],
            'stay' => ["اقامتگاه {$destination}", "رزرو اقامتگاه {$destination}", "اقامتگاه بوم‌گردی {$destination}", "اقامتگاه ارزان {$destination}"],
            'visa' => ["ویزای {$destination}", "قیمت ویزای {$destination}", "شرایط ویزای {$destination}", "اخذ ویزای {$destination}"],
        };
    }

    private function categoryUrl(string $category): string
    {
        $path = match ($category) {
            'stay' => 'accommodation',
            default => $category,
        };

        return rtrim(config('geyt_reference.source'), '/')."/category/{$path}/";
    }
}
