<?php

namespace App\Services\Content;

use App\Models\Tour;

class TourContentCompiler
{
    public function refresh(Tour $tour): void
    {
        $topics = [];
        $sources = $tour->priceSources()->where('is_active', true)->get();

        foreach ($sources as $source) {
            foreach ($source->content_insights ?? [] as $insight) {
                $title = trim((string) ($insight['title'] ?? ''));
                if ($title === '') {
                    continue;
                }

                $key = $this->normalize($title);
                $topics[$key] ??= ['title' => $title, 'sources' => []];
                $topics[$key]['sources'][$source->provider_name] = [
                    'name' => $source->provider_name,
                    'url' => $source->buy_url ?: $source->source_url,
                ];
            }
        }

        $compiled = collect($topics)
            ->map(function (array $topic) {
                $topic['sources'] = array_values($topic['sources']);

                return $topic;
            })
            ->sortByDesc(fn (array $topic) => count($topic['sources']))
            ->take(18)
            ->values()
            ->all();

        $tour->update([
            'auto_content' => $compiled === [] ? null : ['topics' => $compiled],
            'auto_content_updated_at' => now(),
        ]);
    }

    private function normalize(string $value): string
    {
        $value = str_replace(['ي', 'ك', "\u{200C}"], ['ی', 'ک', ' '], mb_strtolower($value));

        return trim(preg_replace('/[^\pL\pN]+/u', ' ', $value) ?? $value);
    }
}
