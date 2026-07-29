<?php

namespace App\Services\Discovery;

use App\Models\ComparisonSource;
use App\Models\TourSuggestion;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ComparisonSourceScanner
{
    private const MAX_HTML_BYTES = 5_242_880;

    private const MAX_ITEMS_PER_CATEGORY = 100;

    private const MAX_DISCOVERY_PAGES = 12;

    public function __construct(
        private readonly ComparisonCatalogDiscovery $catalog,
        private readonly ProviderCatalog $providers,
    ) {}

    public function scan(ComparisonSource $source): array
    {
        $this->assertPublicUrl($source->homepage_url);
        $homepageLinks = $this->fetchLinks($source->homepage_url, $source->homepage_url);
        $links = $homepageLinks;
        $discoveryPages = collect($homepageLinks)
            ->filter(function (array $link) use ($source) {
                foreach ($source->categories ?? [] as $category) {
                    if ($this->mentionsCategory($category, $link) && $this->candidate($category, $link) === null) {
                        return true;
                    }
                }

                return false;
            })
            ->pluck('url')
            ->unique()
            ->take(self::MAX_DISCOVERY_PAGES);
        foreach ($discoveryPages as $pageUrl) {
            try {
                $links = array_merge($links, $this->fetchLinks($pageUrl, $source->homepage_url));
            } catch (\Throwable) {
                // One broken category landing page must not invalidate all
                // otherwise usable discoveries from this source.
            }
        }
        $links = collect($links)->unique(fn (array $link) => $link['url'].'|'.$link['text'])->values()->all();
        $summary = [
            'pages_checked' => 1 + $discoveryPages->count(),
            'links_checked' => count($links),
            'found' => 0,
            'created' => 0,
            'updated' => 0,
            'categories' => [],
        ];

        foreach ($source->categories ?? [] as $category) {
            $candidates = collect($links)
                ->map(fn (array $link) => $this->candidate($category, $link))
                ->filter()
                ->unique('destination')
                ->take(self::MAX_ITEMS_PER_CATEGORY)
                ->values();
            $categoryCreated = $categoryUpdated = 0;

            foreach ($candidates as $candidate) {
                $created = $this->storeCandidate($source, $category, $candidate);
                $created ? $categoryCreated++ : $categoryUpdated++;
            }

            $summary['categories'][$category] = [
                'found' => $candidates->count(),
                'created' => $categoryCreated,
                'updated' => $categoryUpdated,
            ];
            $summary['found'] += $candidates->count();
            $summary['created'] += $categoryCreated;
            $summary['updated'] += $categoryUpdated;
        }

        $source->update([
            'last_status' => 'success',
            'last_error' => null,
            'last_scan_summary' => $summary,
            'last_scanned_at' => now(),
        ]);

        return $summary;
    }

    private function storeCandidate(ComparisonSource $source, string $category, array $candidate): bool
    {
        return DB::transaction(function () use ($source, $category, $candidate) {
            $suggestion = TourSuggestion::query()
                ->where('category', $category)
                ->where('destination', $candidate['destination'])
                ->lockForUpdate()
                ->first();
            $created = $suggestion === null;
            $keywords = $this->keywords($category, $candidate['destination']);
            $sourceReference = [
                'source_id' => $source->id,
                'name' => $source->name,
                'homepage_url' => $source->homepage_url,
                'item_url' => $candidate['url'],
            ];

            if (! $suggestion) {
                $suggestion = TourSuggestion::create([
                    'category' => $category,
                    'keyword' => $keywords[0],
                    'suggested_title' => $this->title($category, $candidate['destination']),
                    'destination' => $candidate['destination'],
                    'trend_score' => 50,
                    'source' => 'managed_source',
                    'status' => 'pending',
                    'metadata' => [
                        'keywords' => $keywords,
                        'variant' => 'main',
                        'discovery_sources' => [$sourceReference],
                    ],
                    'discovered_at' => now(),
                ]);
            } else {
                $metadata = $suggestion->metadata ?? [];
                $metadata['keywords'] = collect($metadata['keywords'] ?? [])
                    ->concat($keywords)->unique()->values()->all();
                $metadata['discovery_sources'] = collect($metadata['discovery_sources'] ?? [])
                    ->reject(fn (array $item) => ($item['source_id'] ?? null) === $source->id)
                    ->push($sourceReference)
                    ->values()
                    ->all();
                $suggestion->update(['metadata' => $metadata, 'discovered_at' => now()]);
            }

            if ($suggestion->tour) {
                $this->providers->attachProvider($suggestion->tour, $candidate['destination'], [
                    'name' => $source->name,
                    'type' => 'marketplace_html',
                    'url' => $candidate['url'],
                ]);
            }

            return $created;
        });
    }

    private function candidate(string $category, array $link): ?array
    {
        if (! $this->mentionsCategory($category, $link)) {
            return null;
        }

        $text = $this->cleanLabel($link['text']);
        $destination = match ($category) {
            'tour' => preg_replace('/(?:^|\s)(?:تور(?:های)?|ارزان|لحظه آخری|اقساطی|هوایی|زمینی|از تهران|خرید|رزرو|قیمت)(?=\s|$)/u', ' ', $text),
            'hotel' => preg_replace('/(?:^|\s)(?:هتل(?:های)?|رزرو|ارزان|خرید|قیمت)(?=\s|$)/u', ' ', $text),
            'stay' => preg_replace('/(?:^|\s)(?:اقامتگاه(?:های)?|بوم ?گردی|ویلا|سوئیت|اجاره|روزانه|رزرو|ارزان|قیمت)(?=\s|$)/u', ' ', $text),
            'visa' => preg_replace('/(?:^|\s)(?:ویزا(?:ی|های)?|روادید|اخذ|فوری|توریستی|قیمت|شرایط)(?=\s|$)/u', ' ', $text),
        };
        $destination = trim($this->normalize((string) $destination));
        if ($destination === '' || mb_strlen($destination) < 2 || mb_strlen($destination) > 80) {
            return null;
        }
        if (preg_match('/^(?:همه|مشاهده|بیشتر|داخلی|خارجی|ایران)$/u', $destination)) {
            return null;
        }

        return ['destination' => $destination, 'url' => $link['url'], 'label' => $text];
    }

    private function links(string $html, string $baseUrl): array
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
            $url = $this->absoluteUrl($baseUrl, $anchor->getAttribute('href'));
            $text = trim(preg_replace('/\s+/u', ' ', $anchor->textContent) ?? '');
            if ($url && $text !== '' && $this->sameHost($baseUrl, $url)) {
                $links[] = ['url' => $url, 'text' => $text];
            }
        }

        return collect($links)->unique('url')->values()->all();
    }

    private function fetchLinks(string $url, string $homepage): array
    {
        if (! $this->sameHost($homepage, $url)) {
            throw new RuntimeException('اسکن فقط روی دامنه ثبت‌شده انجام می‌شود.');
        }
        $response = $this->http()->get($url)->throw();
        $html = $response->body();
        if (strlen($html) > self::MAX_HTML_BYTES) {
            throw new RuntimeException('حجم صفحه منبع بیشتر از حد مجاز است.');
        }

        return $this->links($html, $url);
    }

    private function mentionsCategory(string $category, array $link): bool
    {
        $searchable = $this->normalize($link['text'].' '.rawurldecode($link['url']));
        $patterns = [
            'tour' => '/(?:تور|tour)/iu',
            'hotel' => '/(?:هتل|hotel)/iu',
            'stay' => '/(?:اقامتگاه|بوم ?گردی|ویلا|سوئیت|اجاره روزانه|villa|stay|accommodation)/iu',
            'visa' => '/(?:ویزا|روادید|visa)/iu',
        ];

        return isset($patterns[$category]) && preg_match($patterns[$category], $searchable) === 1;
    }

    private function cleanLabel(string $value): string
    {
        $value = preg_replace('/(?:از\s*)?[۰-۹٠-٩0-9][۰-۹٠-٩0-9,.٬،\s]*(?:تومان|ریال)?/u', ' ', $value) ?? $value;
        $value = preg_replace('/(?:مشاهده|جزئیات|خرید|بیشتر|بهترین پیشنهاد|شروع قیمت)/u', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    }

    private function keywords(string $category, string $destination): array
    {
        if ($category === 'tour') {
            return collect(PopularTourDiscovery::VARIANTS)
                ->map(fn (string $template) => sprintf($template, $destination))
                ->values()
                ->all();
        }

        return $this->catalog->keywords($category, $destination);
    }

    private function title(string $category, string $destination): string
    {
        if ($category === 'tour') {
            return "تور {$destination} | مقایسه قیمت و خرید از معتبرترین آژانس‌ها";
        }

        return $this->catalog->title($category, $destination);
    }

    private function assertPublicUrl(string $url): void
    {
        $parts = parse_url($url);
        $host = $parts['host'] ?? '';
        if (! in_array($parts['scheme'] ?? '', ['http', 'https'], true) || $host === '') {
            throw new RuntimeException('آدرس هوم‌پیج معتبر نیست.');
        }
        $addresses = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);
        if ($addresses === []) {
            throw new RuntimeException('دامنه منبع قابل دسترسی نیست.');
        }
        foreach ($addresses as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                throw new RuntimeException('اسکن آدرس‌های داخلی یا رزروشده مجاز نیست.');
            }
        }
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
        $path = (string) ($parts['path'] ?? '/');
        $directory = rtrim(str_replace('\\', '/', dirname($path)), '/.');

        return $origin.($directory ? '/'.ltrim($directory, '/') : '').'/'.ltrim($href, '/');
    }

    private function sameHost(string $first, string $second): bool
    {
        return mb_strtolower((string) parse_url($first, PHP_URL_HOST))
            === mb_strtolower((string) parse_url($second, PHP_URL_HOST));
    }

    private function normalize(string $value): string
    {
        $value = str_replace(['ي', 'ك', "\u{200C}"], ['ی', 'ک', ' '], mb_strtolower($value));

        return preg_replace('/\s+/u', ' ', $value) ?? $value;
    }

    private function http(): PendingRequest
    {
        return Http::accept('text/html')
            ->timeout(30)
            ->retry(1, 400)
            ->withUserAgent(config('crawler.user_agent'))
            ->withOptions(['allow_redirects' => false]);
    }
}
