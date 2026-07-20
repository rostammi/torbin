<?php

namespace App\Services\Crawlers;

use App\Models\PriceSource;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class FlytodayCrawler
{
    public function crawl(PriceSource $source): CrawlResult
    {
        $url = str_contains($source->source_url, '/packagetour')
            ? $source->source_url
            : 'https://www.flytoday.ir/packagetour';
        $response = $this->http()->get($url);
        if ($response->status() === 404) {
            $url = 'https://www.flytoday.ir/packagetour';
            $response = $this->http()->get($url);
        }

        $body = $response->throw()->body();
        $keyword = $this->destinationKeyword($source);
        $offers = [];

        preg_match_all(
            '~\\\\"title\\\\":\\\\"([^\\\\]+)\\\\",\\\\"subtitle\\\\":\\\\"([^\\\\]*)\\\\",\\\\"price\\\\":\\\\"([^\\\\]+)\\\\",\\\\"lastDate\\\\":\\\\"([^\\\\]+)~u',
            $body,
            $matches,
            PREG_SET_ORDER,
        );

        foreach ($matches as $match) {
            if (! str_contains($this->normalize($match[1]), $keyword) || ! $this->isActive($match[4])) {
                continue;
            }

            $price = $this->rialPrice($match[3]);
            if ($price > 0) {
                $offers[] = [
                    'price' => $source->currency === 'تومان' ? (int) round($price / 10) : $price,
                    'title' => $match[1],
                    'subtitle' => $match[2],
                    'last_date' => $match[4],
                ];
            }
        }

        if ($offers === []) {
            return new CrawlResult(0, $url, details: ['destination' => $keyword]);
        }

        $cheapest = collect($offers)->sortBy('price')->first();

        return new CrawlResult($cheapest['price'], $url, details: [
            'offer_title' => $cheapest['title'],
            'subtitle' => $cheapest['subtitle'],
            'destination' => $keyword,
            'available_until' => $cheapest['last_date'],
        ]);
    }

    private function isActive(string $date): bool
    {
        try {
            return CarbonImmutable::parse($date)->endOfDay()->greaterThanOrEqualTo(now());
        } catch (\Throwable) {
            return false;
        }
    }

    private function rialPrice(string $value): int
    {
        if (! preg_match('/([0-9۰-۹٠-٩،,٬]+)\s*ریال/u', $value, $matches)) {
            return 0;
        }

        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $arabic = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $latin = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        $number = str_replace($persian, $latin, $matches[1]);
        $number = str_replace($arabic, $latin, $number);

        return (int) preg_replace('/[^0-9]/', '', $number);
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

    private function http(): PendingRequest
    {
        return Http::accept('text/html')->timeout(30)->retry(2, 500)->withUserAgent(config('crawler.user_agent'));
    }
}
