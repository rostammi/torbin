<?php

namespace App\Services\Crawlers;

use App\Models\PriceSource;
use App\Services\Outbound\RejectedUrlRegistry;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SourceUrlResolver
{
    private const MAX_CANDIDATES = 3;

    public function __construct(
        private readonly RejectedUrlRegistry $rejectedUrls,
        private readonly DestinationMatcher $destinationMatcher,
    ) {}

    public function candidates(PriceSource $source): array
    {
        $origin = $this->origin($source->source_url);
        if (! $origin) {
            return [];
        }

        $keyword = $this->destinationMatcher->normalizeDestination($source->selector ?: $source->tour->title);
        $pages = collect([
            $origin,
            $origin.'/?s='.rawurlencode($this->categoryLabel($source).' '.$keyword),
        ])->unique()->take(max(1, (int) config('crawler.price_url_resolution_pages', 1)));
        $candidates = collect();

        foreach ($pages as $pageUrl) {
            try {
                $response = $this->http()->get($pageUrl)->throw();
            } catch (\Throwable) {
                continue;
            }

            foreach ($this->matchingLinks(
                $response->body(),
                $pageUrl,
                $keyword,
                $source->tour->category ?: 'tour',
            ) as $candidate) {
                if ($candidate !== $source->source_url
                    && ! $this->rejectedUrls->contains($source->rejected_urls ?? [], $candidate)) {
                    $candidates->push($candidate);
                }
                if ($candidates->unique()->count() >= min(
                    self::MAX_CANDIDATES,
                    max(1, (int) config('crawler.price_url_resolution_candidates', 1)),
                )) {
                    break 2;
                }
            }
        }

        return $candidates->unique()->take(min(
            self::MAX_CANDIDATES,
            max(1, (int) config('crawler.price_url_resolution_candidates', 1)),
        ))->values()->all();
    }

    private function matchingLinks(string $html, string $pageUrl, string $keyword, string $category): array
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
                && $this->destinationMatcher->matches($searchable, $keyword)
                && $this->destinationMatcher->looksLikeCategoryUrl($url, $category)) {
                $links[] = $url;
            }
        }

        return array_values(array_unique($links));
    }

    private function normalize(string $value): string
    {
        $value = $this->destinationMatcher->normalize($value);
        $value = preg_replace('/[|\-–—].*$/u', '', $value) ?? $value;

        return preg_replace('/\s+/u', ' ', $value) ?? $value;
    }

    private function categoryLabel(PriceSource $source): string
    {
        return match ($source->tour->category) {
            'hotel' => 'هتل',
            'stay' => 'اقامتگاه',
            'visa' => 'ویزا',
            default => 'تور',
        };
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
            ->timeout((int) config('crawler.price_http_timeout', 12))
            ->retry((int) config('crawler.price_http_attempts', 1), 300)
            ->withUserAgent(config('crawler.user_agent'))
            ->withOptions(['allow_redirects' => false]);
    }
}
