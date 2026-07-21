<?php

namespace App\Services\Crawlers;

use App\Models\PriceSource;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class StructuredDataCrawler
{
    public function crawl(PriceSource $source): CrawlResult
    {
        $this->assertPublicUrl($source->source_url);
        $response = Http::timeout(25)->retry(2, 500)->withUserAgent(config('crawler.user_agent'))
            ->get($source->source_url)->throw();
        $body = $response->body();
        $offers = [];
        $ratings = [];

        preg_match_all('~<script[^>]+type=["\']application/ld\+json["\'][^>]*>(.*?)</script>~isu', $body, $scripts);
        foreach ($scripts[1] ?? [] as $json) {
            $decoded = json_decode(html_entity_decode($json, ENT_QUOTES | ENT_HTML5), true);
            if (is_array($decoded)) {
                $this->collectStructuredValues($decoded, $offers, $ratings);
            }
        }

        if ($offers === [] && preg_match_all('~(?:itemprop=["\']price["\'][^>]*(?:content=["\']([^"\']+)|>\s*([^<]+)))~iu', $body, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $offers[] = ['price' => $match[1] ?: $match[2], 'url' => $source->source_url];
            }
        }

        $offers = collect($offers)
            ->map(fn (array $offer) => array_merge($offer, ['normalized_price' => $this->price($offer['price'] ?? null, (float) $source->price_multiplier)]))
            ->filter(fn (array $offer) => $offer['normalized_price'] > 0);
        if ($offers->isEmpty()) {
            throw new RuntimeException('قیمت ساختاریافته‌ای در صفحه ارائه‌دهنده پیدا نشد.');
        }

        $cheapest = $offers->sortBy('normalized_price')->first();
        $rating = collect($ratings)->sortByDesc('count')->first();

        return new CrawlResult(
            $cheapest['normalized_price'],
            $cheapest['url'] ?? $source->source_url,
            isset($rating['value']) ? min(5, (float) $rating['value']) : null,
            isset($rating['count']) ? (int) $rating['count'] : null,
            $rating ? 'user_rating' : null,
            array_filter(['offer_title' => $cheapest['name'] ?? $source->tour->title]),
        );
    }

    private function collectStructuredValues(array $node, array &$offers, array &$ratings): void
    {
        $type = $node['@type'] ?? null;
        $types = is_array($type) ? $type : [$type];
        if (array_intersect($types, ['Offer', 'AggregateOffer'])) {
            $offers[] = [
                'price' => $node['lowPrice'] ?? $node['price'] ?? null,
                'url' => $node['url'] ?? null,
                'name' => $node['name'] ?? null,
            ];
        }
        if (in_array('AggregateRating', $types, true)) {
            $best = (float) ($node['bestRating'] ?? 5);
            $value = (float) ($node['ratingValue'] ?? 0);
            $ratings[] = [
                'value' => $best > 5 ? ($value / $best) * 5 : $value,
                'count' => (int) ($node['ratingCount'] ?? $node['reviewCount'] ?? 0),
            ];
        }
        foreach ($node as $value) {
            if (is_array($value)) {
                $this->collectStructuredValues($value, $offers, $ratings);
            }
        }
    }

    private function price(mixed $value, float $multiplier): int
    {
        if (! is_scalar($value)) {
            return 0;
        }
        $value = str_replace(['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'], range(0, 9), (string) $value);
        $digits = preg_replace('/[^0-9]/', '', $value) ?? '';

        return (int) round(((int) $digits) * $multiplier);
    }

    private function assertPublicUrl(string $url): void
    {
        $parts = parse_url($url);
        $host = $parts['host'] ?? '';
        if (! in_array($parts['scheme'] ?? '', ['http', 'https'], true) || $host === '') {
            throw new RuntimeException('آدرس منبع معتبر نیست.');
        }
        $addresses = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);
        if ($addresses === []) {
            throw new RuntimeException('دامنه منبع قابل دسترسی نیست.');
        }
        foreach ($addresses as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                throw new RuntimeException('دسترسی کراولر به آدرس‌های داخلی مجاز نیست.');
            }
        }
    }
}
