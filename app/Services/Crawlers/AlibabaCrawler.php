<?php

namespace App\Services\Crawlers;

use App\Models\PriceSource;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class AlibabaCrawler
{
    private const API = 'https://ws.alibaba.ir/api/v1/tour/available-api';

    public function crawl(PriceSource $source): CrawlResult
    {
        [$origin, $destination] = $this->routeCodes($source);
        if (! $destination) {
            $destination = $this->resolveDestination($source);
        }

        if (! $destination) {
            return new CrawlResult(0, $source->source_url);
        }

        $from = now()->toDateString();
        $to = now()->addDays(7)->toDateString();
        $rooms = (string) (parse_url($source->source_url, PHP_URL_QUERY) ?: '');
        parse_str($rooms, $query);
        $rooms = preg_match('/^\d+(?:-\d+-\d+)?$/', (string) ($query['rooms'] ?? ''))
            ? $query['rooms']
            : '2';

        $response = $this->http()->get(self::API."/available/erp/{$origin}/{$destination}/{$from}/{$to}/{$rooms}")->throw();
        $items = collect($response->json('result.items', []))
            ->filter(fn (array $item) => (int) ($item['minPersonPrice'] ?? 0) > 0);

        if ($items->isEmpty()) {
            return new CrawlResult(0, $this->tourUrl($origin, $destination, $rooms), details: [
                'origin' => $origin,
                'destination' => $destination,
                'searched_from' => $from,
                'searched_to' => $to,
            ]);
        }

        $cheapest = $items->sortBy('minPersonPrice')->first();

        return new CrawlResult(
            $this->fromRials((int) $cheapest['minPersonPrice'], $source->currency),
            isset($cheapest['url']) ? 'https://www.alibaba.ir'.$cheapest['url'] : $this->tourUrl($origin, $destination, $rooms),
            details: array_filter([
                'offer_title' => $source->tour->title,
                'origin' => $origin,
                'destination' => $destination,
                'departure_at' => $cheapest['departureDate'] ?? null,
                'return_at' => $cheapest['returnDate'] ?? null,
                'nights' => $cheapest['nights'] ?? null,
                'searched_from' => $from,
                'searched_to' => $to,
            ], fn ($value) => $value !== null),
        );
    }

    private function routeCodes(PriceSource $source): array
    {
        if (preg_match('~/tour/([^/?]+)/([^/?]+)~', $source->source_url, $matches)) {
            return [$matches[1], $matches[2]];
        }

        return ['iran-tehran', null];
    }

    private function resolveDestination(PriceSource $source): ?string
    {
        $keyword = $this->destinationKeyword($source);
        $groups = $this->http()->get(self::API.'/available/sources', ['query' => $keyword])->throw()->json('result', []);

        foreach ($groups as $group) {
            foreach ($group['places'] ?? [] as $place) {
                $names = collect($place['displayName'] ?? [])->pluck('value')->push($place['name'] ?? '');
                if ($names->contains(fn ($name) => $this->normalize((string) $name) === $keyword)) {
                    return $place['domainCode'] ?? null;
                }
            }
        }

        return null;
    }

    private function destinationKeyword(PriceSource $source): string
    {
        return $this->normalize($source->selector ?: $source->tour->title);
    }

    private function normalize(string $value): string
    {
        $value = str_replace(['ي', 'ك', "\u{200C}"], ['ی', 'ک', ' '], mb_strtolower(trim($value)));
        $value = preg_replace('/^تور(?:های)?\s+/u', '', $value) ?? $value;

        return preg_replace('/\s+/u', ' ', $value) ?? $value;
    }

    private function fromRials(int $price, string $currency): int
    {
        return $currency === 'تومان' ? (int) round($price / 10) : $price;
    }

    private function tourUrl(string $origin, string $destination, string $rooms): string
    {
        return "https://www.alibaba.ir/tour/{$origin}/{$destination}?rooms={$rooms}";
    }

    private function http(): PendingRequest
    {
        return Http::acceptJson()->timeout(30)->retry(2, 500)->withUserAgent(config('crawler.user_agent'));
    }
}
