<?php

namespace App\Services\Discovery;

use App\Models\TourSuggestion;
use DOMDocument;
use Illuminate\Support\Facades\Http;
use Throwable;

class GeytReferenceCatalogDiscovery
{
    public function discover(?string $onlyCategory = null): array
    {
        abort_unless($onlyCategory === null || array_key_exists($onlyCategory, config('comparison.categories')), 404);
        $summary = ['total' => 0, 'created' => 0, 'merged' => 0, 'categories' => []];

        foreach (array_keys(config('comparison.categories')) as $category) {
            if ($onlyCategory !== null && $category !== $onlyCategory) {
                continue;
            }

            $categorySummary = ['total' => 0, 'created' => 0, 'merged' => 0];
            foreach ($this->catalogEntries($category) as $entry) {
                $label = $entry['label'];
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
                    'page_url' => $entry['url'],
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

    private function catalogEntries(string $category): array
    {
        if (config('geyt_reference.live_discovery')) {
            try {
                $entries = $this->fetchCategory($category);
                if ($entries !== []) {
                    return $entries;
                }
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return collect(config("geyt_reference.catalogs.{$category}", []))
            ->unique()
            ->map(fn (string $label) => [
                'label' => $label,
                'url' => $this->categoryUrl($category),
            ])
            ->values()
            ->all();
    }

    private function fetchCategory(string $category): array
    {
        $categoryUrl = $this->categoryUrl($category);
        $itemPath = $category === 'stay' ? 'accommodation' : $category;
        $html = Http::timeout(30)
            ->retry(2, 500)
            ->withUserAgent(config('crawler.user_agent'))
            ->get($categoryUrl)
            ->throw()
            ->body();

        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $entries = [];
        foreach ($dom->getElementsByTagName('a') as $anchor) {
            $href = trim($anchor->getAttribute('href'));
            $path = rawurldecode((string) parse_url($href, PHP_URL_PATH));
            if (! preg_match("~^/{$itemPath}/[^/]+/?$~u", $path)) {
                continue;
            }

            $label = trim(preg_replace('/\s+/u', ' ', $anchor->getAttribute('title')) ?? '');
            if ($label === '' || $label === 'مشاهده قیمت ها') {
                continue;
            }

            $url = str_starts_with($href, 'http')
                ? $href
                : rtrim(config('geyt_reference.source'), '/').'/'.ltrim($href, '/');
            $entries[$url] = ['label' => $label, 'url' => $url];
        }

        return array_values($entries);
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
