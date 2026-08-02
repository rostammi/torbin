<?php

namespace App\Services\Search;

use App\Services\Discovery\PopularTourDiscovery;

class TravelIntentParser
{
    private const DIGITS = [
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
    ];

    public function parse(string $term): TravelSearchIntent
    {
        $normalized = $this->normalize($term);
        $destination = $this->destination($normalized);
        $region = $this->region($normalized, $destination);
        $maximumBudget = $this->budget($normalized);
        $hasTravelLanguage = preg_match('/(?:سفر|تور|کجا\s+(?:برم|بریم|میتونم\s+برم)|مسافرت|بودجه)/u', $normalized) === 1;

        return new TravelSearchIntent(
            original: trim($term),
            normalized: $normalized,
            maximumBudget: $maximumBudget,
            region: $region,
            destination: $destination,
            isRecommendation: $maximumBudget !== null || ($region !== null && $hasTravelLanguage),
        );
    }

    private function budget(string $value): ?int
    {
        if (preg_match('/(?<amount>\d+(?:[.,]\d+)?)\s*(?<unit>میلیارد|میلیون|هزار)(?:\s*(?:تومان|تومن))?/u', $value, $match)) {
            $amount = $this->decimalAmount($match['amount']);
            $multiplier = match ($match['unit']) {
                'میلیارد' => 1_000_000_000,
                'میلیون' => 1_000_000,
                default => 1_000,
            };

            return $this->validBudget((int) round($amount * $multiplier));
        }

        if (preg_match('/(?<amount>\d[\d,]*)\s*(?:تومان|تومن)/u', $value, $match)) {
            return $this->validBudget((int) str_replace(',', '', $match['amount']));
        }

        if (preg_match('/(?:بودجه|تا|زیر|حداکثر)\s*(?<amount>\d[\d,]{3,})/u', $value, $match)) {
            return $this->validBudget((int) str_replace(',', '', $match['amount']));
        }

        return null;
    }

    private function decimalAmount(string $amount): float
    {
        if (substr_count($amount, ',') === 1 && strlen(substr($amount, strpos($amount, ',') + 1)) <= 2) {
            $amount = str_replace(',', '.', $amount);
        } else {
            $amount = str_replace(',', '', $amount);
        }

        return (float) $amount;
    }

    private function validBudget(int $amount): ?int
    {
        return $amount >= 10_000 && $amount <= 100_000_000_000 ? $amount : null;
    }

    private function region(string $value, ?string $destination): ?string
    {
        if (preg_match('/(?:خارجی|خارج\s+از\s+(?:کشور|ایران)|بین\s*المللی)/u', $value)) {
            return 'foreign';
        }

        if (preg_match('/(?:داخلی|داخل\s+ایران|ایرانگردی)/u', $value)) {
            return 'domestic';
        }

        if ($destination !== null) {
            return in_array($destination, PopularTourDiscovery::DOMESTIC_DESTINATIONS, true)
                ? 'domestic'
                : 'foreign';
        }

        return null;
    }

    private function destination(string $value): ?string
    {
        $destinations = [...PopularTourDiscovery::DOMESTIC_DESTINATIONS, ...PopularTourDiscovery::FOREIGN_DESTINATIONS];
        usort($destinations, fn (string $left, string $right) => mb_strlen($right) <=> mb_strlen($left));

        foreach ($destinations as $destination) {
            $normalizedDestination = preg_quote($this->normalize($destination), '/');
            if (preg_match('/(?:^|[\s،,:؛؟!])'.$normalizedDestination.'(?:$|[\s،,:؛؟!])/u', $value)) {
                return $destination;
            }
        }

        return null;
    }

    private function normalize(string $value): string
    {
        $value = strtr(mb_strtolower(trim($value)), self::DIGITS);
        $value = str_replace(['ي', 'ك', "\u{200C}", '٫', '٬'], ['ی', 'ک', ' ', '.', ','], $value);

        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }
}
