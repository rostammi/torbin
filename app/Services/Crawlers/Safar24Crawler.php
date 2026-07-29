<?php

namespace App\Services\Crawlers;

use App\Models\PriceSource;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class Safar24Crawler
{
    public function crawl(PriceSource $source): CrawlResult
    {
        $this->assertPublicUrl($source->source_url);
        $response = $this->http()->get($source->source_url)->throw();
        $keyword = $this->normalize($source->selector ?: $source->tour->title);
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?>'.$response->body(), LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $offers = [];

        foreach ((new DOMXPath($document))->query('//a[@href]') ?: [] as $anchor) {
            if (! $anchor instanceof DOMElement) {
                continue;
            }

            $text = $this->normalize($anchor->textContent);
            if (! str_contains($text, $keyword)
                || ! preg_match_all('/([0-9۰-۹٠-٩][0-9۰-۹٠-٩,.،٬\s]{2,20})\s*(?:تومان|تومن)/u', $text, $matches)) {
                continue;
            }

            foreach ($matches[1] as $value) {
                $price = $this->digits($value);
                if ($price >= 100_000) {
                    $offers[] = [
                        'price' => $price,
                        'url' => $this->absoluteUrl($source->source_url, $anchor->getAttribute('href')),
                        'title' => trim($anchor->textContent),
                    ];
                }
            }
        }

        if ($offers === []) {
            return new CrawlResult(0, $source->source_url, details: ['destination' => $keyword]);
        }

        $cheapest = collect($offers)->sortBy('price')->first();

        return new CrawlResult(
            $cheapest['price'],
            $cheapest['url'],
            details: [
                'offer_title' => $cheapest['title'],
                'destination' => $keyword,
            ],
        );
    }

    private function normalize(string $value): string
    {
        $value = str_replace(['ي', 'ك', "\u{200C}"], ['ی', 'ک', ' '], mb_strtolower(trim($value)));
        $value = preg_replace('/^تور(?:های)?\s+/u', '', $value) ?? $value;

        return preg_replace('/\s+/u', ' ', $value) ?? $value;
    }

    private function digits(string $value): int
    {
        $value = str_replace(['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'], range(0, 9), $value);
        $value = str_replace(['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'], range(0, 9), $value);

        return (int) preg_replace('/[^0-9]/', '', $value);
    }

    private function absoluteUrl(string $base, string $href): string
    {
        if (filter_var($href, FILTER_VALIDATE_URL)) {
            return $href;
        }

        $parts = parse_url($base);

        return $parts['scheme'].'://'.$parts['host'].'/'.ltrim($href, '/');
    }

    private function http(): PendingRequest
    {
        return Http::accept('text/html')
            ->timeout(30)
            ->retry(2, 500)
            ->withUserAgent(config('crawler.user_agent'))
            ->withOptions(['allow_redirects' => false]);
    }

    private function assertPublicUrl(string $url): void
    {
        $parts = parse_url($url);
        $host = $parts['host'] ?? '';
        if (! in_array($parts['scheme'] ?? '', ['http', 'https'], true) || $host === '') {
            throw new RuntimeException('آدرس سفر۲۴ معتبر نیست.');
        }

        $addresses = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);
        if ($addresses === []) {
            throw new RuntimeException('دامنه سفر۲۴ قابل دسترسی نیست.');
        }

        foreach ($addresses as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                throw new RuntimeException('دسترسی کراولر به آدرس‌های داخلی مجاز نیست.');
            }
        }
    }
}
