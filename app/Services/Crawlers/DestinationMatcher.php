<?php

namespace App\Services\Crawlers;

class DestinationMatcher
{
    public function matches(string $value, string $destination): bool
    {
        $value = $this->normalize($value);
        $destination = $this->normalizeDestination($destination);

        return $destination !== '' && str_contains(" {$value} ", " {$destination} ");
    }

    public function normalize(string $value): string
    {
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = str_replace(
            ['ي', 'ك', "\u{200C}"],
            ['ی', 'ک', ' '],
            mb_strtolower(trim($value)),
        );

        return trim(preg_replace('/[^\pL\pN]+/u', ' ', $value) ?? $value);
    }

    public function normalizeDestination(string $value): string
    {
        $value = $this->normalize($value);

        return preg_replace('/^(?:تور(?:های)?|هتل|اقامتگاه|بوم گردی|ویزا(?:ی)?)\s+/u', '', $value) ?? $value;
    }

    public function looksLikeCategoryUrl(string $url, string $category): bool
    {
        $searchable = $this->normalize(rawurldecode(
            (string) parse_url($url, PHP_URL_PATH).' '.(string) parse_url($url, PHP_URL_QUERY),
        ));
        $markers = match ($category) {
            'hotel' => ['hotel', 'hotels', 'هتل'],
            'stay' => ['accommodation', 'stay', 'stays', 'villa', 'room', 'اقامتگاه', 'ویلا'],
            'visa' => ['visa', 'visas', 'ویزا'],
            default => ['tour', 'tours', 'تور'],
        };

        return collect($markers)->contains(fn (string $marker) => $this->matches($searchable, $marker));
    }
}
