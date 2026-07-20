<?php

namespace App\Services\Crawlers;

use App\Models\PriceSource;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class SafarmarketCrawler
{
    public function crawl(PriceSource $source): CrawlResult
    {
        [$origin, $destination] = $this->routeIds($source);
        if (! $destination) {
            $destination = $this->resolveDestination($source);
        }

        if (! $destination) {
            return new CrawlResult(0, $source->source_url);
        }

        $from = now()->toDateString();
        $to = now()->addDays((int) config('crawler.search_days', 30))->toDateString();
        $params = ['origin' => $origin, 'destination' => $destination, 'dep' => $from, 'ret' => $to];
        $offers = [];
        $successfulRequests = 0;

        try {
            $response = $this->http()->get('https://ttourapi.safarmarket.com/api/v1/application/web/tours/search', $params)->throw();
            $successfulRequests++;
            foreach ($this->cards($response->json('data', [])) as $card) {
                if ((int) ($card['price'] ?? 0) > 0) {
                    $offers[] = [
                        'price' => $this->fromTomans((int) $card['price'], $source->currency),
                        'card' => $card,
                        'api' => 'api1',
                    ];
                }
            }
        } catch (Throwable $exception) {
            report($exception);
        }

        try {
            $response = $this->http()->get('https://safarmarket.com/tourApi/v3/searchResult', $params)->throw();
            $successfulRequests++;
            foreach ($this->cards($response->json('data', [])) as $card) {
                if ((int) ($card['price'] ?? 0) > 0) {
                    $offers[] = [
                        'price' => $this->fromRials((int) $card['price'], $source->currency),
                        'card' => $card,
                        'api' => 'api2',
                    ];
                }
            }
        } catch (Throwable $exception) {
            report($exception);
        }

        if ($successfulRequests === 0) {
            throw new RuntimeException('هر دو سرویس قیمت سفرمارکت در دسترس نیستند.');
        }

        $buyUrl = "https://safarmarket.com/tours2/{$origin}/{$destination}/{$from}...{$to}/allBudgets/";

        if ($offers === []) {
            return new CrawlResult(0, $buyUrl, details: [
                'origin_id' => $origin,
                'destination_id' => $destination,
                'searched_from' => $from,
                'searched_to' => $to,
            ]);
        }

        $cheapest = collect($offers)->sortBy('price')->first();
        $card = $cheapest['card'];
        [$rating, $ratingCount, $ratingType] = $this->rating($card);

        return new CrawlResult(
            $cheapest['price'],
            $buyUrl,
            $rating,
            $ratingCount,
            $ratingType,
            array_filter([
                'offer_title' => $card['residency_name'] ?? $source->tour->title,
                'tour_code' => $card['tour_code'] ?? null,
                'origin' => $card['origin_name'] ?? null,
                'destination' => $card['destination_name'] ?? null,
                'departure_at' => $card['date_dep'] ?? null,
                'return_at' => $card['date_arr'] ?? null,
                'days' => $card['days'] ?? null,
                'nights' => $card['nights'] ?? null,
                'hotel' => $card['residency_name'] ?? null,
                'hotel_stars' => $card['residency_star'] ?? null,
                'vendor' => $card['vendor_name'] ?? null,
                'transport' => $card['transportation'] ?? null,
                'categories' => $card['categories'] ?? null,
                'offers_count' => $card['offers'] ?? null,
                'api' => $cheapest['api'],
                'searched_from' => $from,
                'searched_to' => $to,
            ], fn ($value) => $value !== null),
        );
    }

    private function routeIds(PriceSource $source): array
    {
        if (preg_match('~/tours2/(\d+)/(\d+)~', $source->source_url, $matches)) {
            return [(int) $matches[1], (int) $matches[2]];
        }

        return [(int) config('crawler.safarmarket_origin_id', 19981), null];
    }

    private function resolveDestination(PriceSource $source): ?int
    {
        $keyword = $this->destinationKeyword($source);
        $response = $this->http()->get('https://panel.safarmarket.com/api/v2/search_box/destinations', [
            'term' => $keyword,
        ])->throw();

        foreach (['domestic', 'international', 'experienced'] as $group) {
            foreach ($response->json("data.{$group}.all", []) as $destination) {
                if ($this->normalize((string) ($destination['persian_name'] ?? '')) === $keyword) {
                    return (int) $destination['id'];
                }
            }
        }

        return null;
    }

    private function cards(array $data): array
    {
        return array_merge($data['cards'] ?? [], $data['suggested_cards'] ?? []);
    }

    private function rating(array $card): array
    {
        foreach (['rating', 'user_rating', 'review_score', 'residency_rating'] as $key) {
            if (isset($card[$key]) && is_numeric($card[$key])) {
                $rating = (float) $card[$key];
                $rating = $rating > 5 && $rating <= 10 ? $rating / 2 : $rating;

                return [
                    min(5, max(0, $rating)),
                    isset($card['rating_count']) ? (int) $card['rating_count'] : (isset($card['review_count']) ? (int) $card['review_count'] : null),
                    'user_rating',
                ];
            }
        }

        if (isset($card['residency_star']) && is_numeric($card['residency_star'])) {
            return [(float) $card['residency_star'], null, 'hotel_stars'];
        }

        return [null, null, null];
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

    private function fromTomans(int $price, string $currency): int
    {
        return $currency === 'ریال' ? $price * 10 : $price;
    }

    private function fromRials(int $price, string $currency): int
    {
        return $currency === 'تومان' ? (int) round($price / 10) : $price;
    }

    private function http(): PendingRequest
    {
        return Http::acceptJson()->timeout(30)->retry(2, 500)->withUserAgent(config('crawler.user_agent'));
    }
}
