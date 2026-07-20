<?php

namespace App\Services;

use App\Models\PriceSource;
use App\Services\Crawlers\AlibabaCrawler;
use App\Services\Crawlers\CrawlResult;
use App\Services\Crawlers\FlytodayCrawler;
use App\Services\Crawlers\SafarmarketCrawler;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class PriceCrawler
{
    public function __construct(
        private readonly AlibabaCrawler $alibaba,
        private readonly FlytodayCrawler $flytoday,
        private readonly SafarmarketCrawler $safarmarket,
    ) {}

    public function crawl(PriceSource $source): bool
    {
        try {
            if ($source->extraction_type === 'manual') {
                throw new RuntimeException('این منبع دستی است و نیازی به کراول ندارد.');
            }

            $result = $this->extract($source);

            $source->update([
                'latest_price' => $result->price,
                'latest_rating' => $result->rating,
                'latest_rating_count' => $result->ratingCount,
                'rating_type' => $result->ratingType,
                'latest_details' => $result->details ?: null,
                'buy_url' => $result->buyUrl ?: $source->buy_url,
                'last_checked_at' => now(),
                'last_status' => $result->price === 0 ? 'empty' : 'success',
                'last_error' => null,
            ]);
            $source->history()->create([
                'price' => $result->price,
                'rating' => $result->rating,
                'rating_count' => $result->ratingCount,
                'rating_type' => $result->ratingType,
                'is_available' => $result->price > 0,
                'buy_url' => $result->buyUrl,
                'offer_title' => $result->details['offer_title'] ?? null,
                'departure_at' => $result->details['departure_at'] ?? null,
                'return_at' => $result->details['return_at'] ?? null,
                'details' => $result->details ?: null,
                'observed_at' => now(),
            ]);

            return true;
        } catch (Throwable $exception) {
            $source->update([
                'last_checked_at' => now(),
                'last_status' => 'failed',
                'last_error' => mb_substr($exception->getMessage(), 0, 1000),
            ]);

            report($exception);

            return false;
        }
    }

    private function extract(PriceSource $source): CrawlResult
    {
        return match ($source->extraction_type) {
            'alibaba' => $this->alibaba->crawl($source),
            'flytoday' => $this->flytoday->crawl($source),
            'safarmarket' => $this->safarmarket->crawl($source),
            'json', 'regex' => $this->extractGeneric($source),
            default => throw new RuntimeException('نوع استخراج پشتیبانی نمی‌شود.'),
        };
    }

    private function extractGeneric(PriceSource $source): CrawlResult
    {
        $this->assertPublicUrl($source->source_url);
        $response = Http::timeout(20)
            ->retry(2, 500)
            ->withUserAgent(config('crawler.user_agent'))
            ->withOptions(['allow_redirects' => false])
            ->get($source->source_url)
            ->throw();
        $raw = $source->extraction_type === 'json'
            ? data_get($response->json(), $source->selector)
            : $this->extractByRegex($response->body(), (string) $source->selector);
        $price = $this->normalizePrice($raw, (float) $source->price_multiplier);
        if ($price < 1) {
            throw new RuntimeException('قیمت معتبر در پاسخ پیدا نشد.');
        }

        return new CrawlResult($price, $source->buy_url ?: $source->source_url);
    }

    private function extractByRegex(string $body, string $pattern): mixed
    {
        if ($pattern === '' || @preg_match($pattern, $body, $matches) !== 1) {
            throw new RuntimeException('الگوی regex با محتوای صفحه تطبیق نداشت.');
        }

        return $matches[1] ?? $matches[0];
    }

    private function normalizePrice(mixed $value, float $multiplier): int
    {
        if (! is_scalar($value)) {
            throw new RuntimeException('مقدار استخراج‌شده عددی نیست.');
        }

        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $arabic = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $latin = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        $normalized = str_replace($persian, $latin, (string) $value);
        $normalized = str_replace($arabic, $latin, $normalized);
        $digits = preg_replace('/[^0-9]/', '', $normalized) ?? '';

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
