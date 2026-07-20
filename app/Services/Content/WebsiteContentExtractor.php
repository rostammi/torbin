<?php

namespace App\Services\Content;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WebsiteContentExtractor
{
    private const TOPIC_WORDS = [
        'دیدنی', 'جاذبه', 'گردش', 'تفریح', 'راهنما', 'سفر', 'اقامت', 'هتل',
        'آب و هوا', 'آب‌وهوا', 'حمل و نقل', 'حمل‌ونقل', 'برنامه سفر', 'درباره',
        'خرید', 'رستوران', 'غذا', 'سوغات', 'فرهنگ', 'تاریخ', 'بهترین زمان',
    ];

    public function extract(string $url, string $tourTitle): array
    {
        $this->assertPublicUrl($url);

        $response = Http::accept('text/html')
            ->timeout(25)
            ->retry(1, 400)
            ->withUserAgent(config('crawler.user_agent'))
            ->withOptions(['allow_redirects' => false])
            ->get($url)
            ->throw();

        $contentType = mb_strtolower($response->header('Content-Type'));
        if ($contentType !== '' && ! str_contains($contentType, 'html')) {
            return [];
        }

        $html = mb_substr($response->body(), 0, 2_000_000);
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            return [];
        }

        $xpath = new DOMXPath($document);
        foreach ($xpath->query('//script|//style|//nav|//footer|//header|//form|//noscript') ?: [] as $node) {
            $node->parentNode?->removeChild($node);
        }

        $destination = $this->normalize(preg_replace('/^تور(?:های)?\s+/u', '', $tourTitle) ?? $tourTitle);
        $topics = [];
        foreach ($xpath->query('//main//h2|//main//h3|//article//h2|//article//h3|//h2|//h3') ?: [] as $heading) {
            $title = $this->clean($heading->textContent);
            if (! $this->isUseful($title, $destination)) {
                continue;
            }

            $key = $this->normalize($title);
            $topics[$key] ??= ['title' => $title];
            if (count($topics) >= 10) {
                break;
            }
        }

        return array_values($topics);
    }

    private function isUseful(string $title, string $destination): bool
    {
        $length = mb_strlen($title);
        if ($length < 7 || $length > 120) {
            return false;
        }

        $normalized = $this->normalize($title);
        if ($destination !== '' && str_contains($normalized, $destination)) {
            return true;
        }

        return collect(self::TOPIC_WORDS)->contains(fn (string $word) => str_contains($normalized, $word));
    }

    private function clean(string $value): string
    {
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value, " \t\n\r\0\x0B:-|•");
    }

    private function normalize(string $value): string
    {
        $value = str_replace(['ي', 'ك', "\u{200C}"], ['ی', 'ک', ' '], mb_strtolower($this->clean($value)));

        return preg_replace('/[^\pL\pN]+/u', ' ', $value) ?? $value;
    }

    private function assertPublicUrl(string $url): void
    {
        $parts = parse_url($url);
        $host = $parts['host'] ?? '';
        if (! in_array($parts['scheme'] ?? '', ['http', 'https'], true) || $host === '') {
            throw new RuntimeException('آدرس صفحه محتوایی معتبر نیست.');
        }

        $addresses = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);
        if ($addresses === []) {
            throw new RuntimeException('دامنه صفحه محتوایی قابل دسترسی نیست.');
        }

        foreach ($addresses as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                throw new RuntimeException('خواندن محتوا از آدرس‌های داخلی مجاز نیست.');
            }
        }
    }
}
