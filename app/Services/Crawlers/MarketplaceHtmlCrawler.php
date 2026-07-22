<?php

namespace App\Services\Crawlers;

use App\Models\PriceSource;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class MarketplaceHtmlCrawler
{
    private const MAX_DESTINATION_PAGES = 3;

    public function crawl(PriceSource $source): CrawlResult
    {
        $this->assertPublicUrl($source->source_url);
        $keyword = $this->destinationKeyword($source);
        $body = $this->fetch($source->source_url);
        [$offers, $links] = $this->inspect($body, $source->source_url, $keyword, $source);
        $pagesChecked = 1;

        if ($offers === []) {
            foreach (array_slice($links, 0, self::MAX_DESTINATION_PAGES) as $url) {
                $this->assertPublicUrl($url);
                [$pageOffers] = $this->inspect($this->fetch($url), $url, $keyword, $source);
                $offers = array_merge($offers, $pageOffers);
                $pagesChecked++;
            }
        }

        if ($offers === []) {
            return new CrawlResult(0, $links[0] ?? $source->source_url, details: [
                'destination' => $keyword,
                'pages_checked' => $pagesChecked,
            ]);
        }

        $cheapest = collect($offers)->sortBy('price')->first();

        return new CrawlResult(
            $cheapest['price'],
            $cheapest['url'],
            details: array_filter([
                'offer_title' => $cheapest['title'] ?: $source->tour->title,
                'destination' => $keyword,
                'pages_checked' => $pagesChecked,
            ]),
        );
    }

    private function inspect(
        string $html,
        string $pageUrl,
        string $keyword,
        PriceSource $source,
    ): array {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new DOMXPath($document);
        $offers = [];
        $links = [];

        foreach ($xpath->query('//a[@href]') ?: [] as $anchor) {
            if (! $anchor instanceof DOMElement) {
                continue;
            }

            $anchorText = $this->normalize($anchor->textContent);
            if (! str_contains($anchorText, $keyword)) {
                continue;
            }

            $url = $this->absoluteUrl($pageUrl, $anchor->getAttribute('href'));
            if ($url && $this->sameHost($pageUrl, $url) && $this->looksLikeTourUrl($url)) {
                $links[$url] = $url;
            }

            $node = $anchor;
            for ($depth = 0; $depth < 8 && $node instanceof DOMElement; $depth++, $node = $node->parentNode) {
                if (in_array(mb_strtolower($node->tagName), ['html', 'body', 'main'], true)) {
                    break;
                }
                if (mb_strlen($node->textContent) > 5000) {
                    continue;
                }
                if ($node->getElementsByTagName('a')->length > 3) {
                    continue;
                }
                $contextOffers = $this->offersFromElement(
                    $node,
                    $url ?: $pageUrl,
                    trim($anchor->textContent),
                    $source,
                );
                if ($contextOffers !== []) {
                    $offers = array_merge($offers, $contextOffers);
                    break;
                }
            }
        }

        if ($this->pageTargetsDestination($xpath, $keyword)) {
            $offers = array_merge($offers, $this->offersFromPriceElements($xpath, $pageUrl, $source));
        }

        return [$this->uniqueOffers($offers), array_values($links)];
    }

    private function offersFromPriceElements(DOMXPath $xpath, string $pageUrl, PriceSource $source): array
    {
        $offers = [];
        $query = <<<'XPATH'
            //*[
                not(ancestor::header) and not(ancestor::nav) and not(ancestor::footer) and not(ancestor::aside)
                and (
                    @itemprop='price'
                    or contains(translate(@class, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'price')
                    or contains(translate(@class, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'amount')
                    or contains(translate(@class, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'cost')
                    or contains(@class, 'geymat')
                )
            ]
            XPATH;

        foreach ($xpath->query($query) ?: [] as $element) {
            if (! $element instanceof DOMElement || mb_strlen($element->textContent) > 1000) {
                continue;
            }
            $offers = array_merge($offers, $this->offersFromElement(
                $element,
                $pageUrl,
                $this->pageTitle($xpath),
                $source,
            ));
        }

        return $offers;
    }

    private function offersFromElement(DOMElement $element, string $url, string $title, PriceSource $source): array
    {
        $text = $element->textContent;
        if (! preg_match('/(?:تومان|تومن|ریال)/u', $text)) {
            $currency = $this->currencyFromClasses($element);
            if ($currency) {
                $text .= ' '.$currency;
            }
        }

        return $this->offersFromText($text, $url, $title, $source);
    }

    private function currencyFromClasses(DOMElement $element): ?string
    {
        $nodes = [$element];
        foreach ($element->getElementsByTagName('*') as $descendant) {
            $nodes[] = $descendant;
        }

        foreach ($nodes as $node) {
            $class = mb_strtolower($node->getAttribute('class'));
            if (preg_match('/(?:^|\s)toman(?:\s|$)/', $class)) {
                return 'تومان';
            }
            if (preg_match('/(?:^|\s)rial(?:\s|$)/', $class)) {
                return 'ریال';
            }
        }

        return null;
    }

    private function offersFromText(string $text, string $url, string $title, PriceSource $source): array
    {
        $offers = [];
        preg_match_all(
            '/([0-9۰-۹٠-٩][0-9۰-۹٠-٩\s,.،٬٫]{2,20})\s*(تومان|تومن|ریال)/u',
            html_entity_decode($text, ENT_QUOTES | ENT_HTML5),
            $matches,
            PREG_SET_ORDER,
        );

        foreach ($matches as $match) {
            $price = $this->digits($match[1]);
            if ($match[2] === 'ریال' && $source->currency === 'تومان') {
                $price = (int) round($price / 10);
            } elseif ($match[2] !== 'ریال' && $source->currency === 'ریال') {
                $price *= 10;
            }
            $price = (int) round($price * (float) ($source->price_multiplier ?: 1));

            if ($price >= 100_000) {
                $offers[] = compact('price', 'url', 'title');
            }
        }

        return $offers;
    }

    private function uniqueOffers(array $offers): array
    {
        return collect($offers)
            ->unique(fn (array $offer) => $offer['price'].'|'.$offer['url'])
            ->values()
            ->all();
    }

    private function pageTargetsDestination(DOMXPath $xpath, string $keyword): bool
    {
        foreach ($xpath->query('//h1|//title') ?: [] as $node) {
            if (str_contains($this->normalize($node->textContent), $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function pageTitle(DOMXPath $xpath): string
    {
        foreach ($xpath->query('//h1|//title') ?: [] as $node) {
            $title = trim($node->textContent);
            if ($title !== '') {
                return $title;
            }
        }

        return '';
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

    private function digits(string $value): int
    {
        $value = str_replace(['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'], range(0, 9), $value);
        $value = str_replace(['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'], range(0, 9), $value);

        return (int) preg_replace('/[^0-9]/', '', $value);
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

        $parts = parse_url($base);
        if (! isset($parts['scheme'], $parts['host'])) {
            return null;
        }
        $origin = $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
        if (str_starts_with($href, '//')) {
            return $parts['scheme'].':'.$href;
        }
        if (str_starts_with($href, '/')) {
            return $origin.$href;
        }
        $directory = Str::beforeLast($parts['path'] ?? '/', '/');

        return $origin.rtrim($directory, '/').'/'.$href;
    }

    private function sameHost(string $first, string $second): bool
    {
        return mb_strtolower((string) parse_url($first, PHP_URL_HOST)) === mb_strtolower((string) parse_url($second, PHP_URL_HOST));
    }

    private function looksLikeTourUrl(string $url): bool
    {
        return preg_match('~(?:tour|tours|تور)~iu', rawurldecode($url)) === 1;
    }

    private function fetch(string $url): string
    {
        return $this->http()->get($url)->throw()->body();
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
