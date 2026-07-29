<?php

namespace App\Services\Crawlers;

use App\Models\PriceSource;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SourceUrlResolver
{
    private const MAX_CANDIDATES = 3;

    public function candidates(PriceSource $source): array
    {
        $origin = $this->origin($source->source_url);
        if (! $origin) {
            return [];
        }

        $keyword = $this->normalize($source->selector ?: $source->tour->title);
        $pages = collect([
            $source->source_url,
            $origin,
            $origin.'/?s='.rawurlencode('تور '.$keyword),
        ])->unique();
        $candidates = collect();

        foreach ($pages as $pageUrl) {
            try {
                $response = $this->http()->get($pageUrl)->throw();
            } catch (\Throwable) {
                continue;
            }

            foreach ($this->matchingLinks($response->body(), $pageUrl, $keyword) as $candidate) {
                if ($candidate !== $source->source_url) {
                    $candidates->push($candidate);
                }
                if ($candidates->unique()->count() >= self::MAX_CANDIDATES) {
                    break 2;
                }
            }
        }

        return $candidates->unique()->take(self::MAX_CANDIDATES)->values()->all();
    }

    private function matchingLinks(string $html, string $pageUrl, string $keyword): array
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $links = [];

        foreach ((new DOMXPath($document))->query('//a[@href]') ?: [] as $anchor) {
            if (! $anchor instanceof DOMElement) {
                continue;
            }

            $url = $this->absoluteUrl($pageUrl, $anchor->getAttribute('href'));
            $searchable = $this->normalize($anchor->textContent.' '.rawurldecode($url ?? ''));
            if ($url
                && $this->sameHost($pageUrl, $url)
                && str_contains($searchable, $keyword)
                && preg_match('~(?:tour|tours|تور)~iu', $searchable)) {
                $links[] = $url;
            }
        }

        return array_values(array_unique($links));
    }

    private function normalize(string $value): string
    {
        $value = str_replace(['ي', 'ك', "\u{200C}"], ['ی', 'ک', ' '], mb_strtolower(trim($value)));
        $value = preg_replace('/^تور(?:های)?\s+/u', '', $value) ?? $value;
        $value = preg_replace('/[|\-–—].*$/u', '', $value) ?? $value;

        return preg_replace('/\s+/u', ' ', $value) ?? $value;
    }

    private function origin(string $url): ?string
    {
        $parts = parse_url($url);
        if (! isset($parts['scheme'], $parts['host']) || ! in_array($parts['scheme'], ['http', 'https'], true)) {
            return null;
        }

        return $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
    }

    private function absoluteUrl(string $base, string $href): ?string
    {
        $href = trim(html_entity_decode($href, ENT_QUOTES | ENT_HTML5));
        if ($href === '' || str_starts_with($href, '#') || str_starts_with($href, 'javascript:')) {
            return null;
        }
        if (filter_var($href, FILTER_VALIDATE_URL)) {
            return $href;
        }

        $origin = $this->origin($base);
        if (! $origin) {
            return null;
        }
        if (str_starts_with($href, '//')) {
            return parse_url($base, PHP_URL_SCHEME).':'.$href;
        }
        if (str_starts_with($href, '/')) {
            return $origin.$href;
        }

        $path = (string) parse_url($base, PHP_URL_PATH);

        return $origin.'/'.ltrim(Str::beforeLast($path, '/').'/'.$href, '/');
    }

    private function sameHost(string $first, string $second): bool
    {
        return mb_strtolower((string) parse_url($first, PHP_URL_HOST))
            === mb_strtolower((string) parse_url($second, PHP_URL_HOST));
    }

    private function http(): PendingRequest
    {
        return Http::accept('text/html')
            ->timeout(20)
            ->retry(1, 300)
            ->withUserAgent(config('crawler.user_agent'))
            ->withOptions(['allow_redirects' => false]);
    }
}
