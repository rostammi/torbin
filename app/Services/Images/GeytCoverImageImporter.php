<?php

namespace App\Services\Images;

use App\Models\LegacyRedirect;
use App\Models\Tour;
use DOMDocument;
use DOMXPath;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class GeytCoverImageImporter
{
    public function __construct(private readonly WebpImageConverter $webp) {}

    public function import(Tour $tour): array
    {
        $urls = $this->sourceUrls($tour);
        if ($urls === []) {
            return ['status' => 'unmapped'];
        }

        $lastError = null;
        foreach ($urls as $pageUrl) {
            try {
                $page = $this->request($pageUrl, 'text/html,application/xhtml+xml');
                $imageUrl = $this->extractCoverUrl($page->body(), $pageUrl);
                if (! $imageUrl) {
                    throw new RuntimeException('تصویر کاور در صفحه منبع پیدا نشد.');
                }

                $existing = collect($tour->image_sources ?? [])->first(
                    fn (array $source) => ($source['source'] ?? null) === 'geyt_cover'
                        && ($source['page_url'] ?? null) === $pageUrl
                        && filled($source['path'] ?? null)
                        && Storage::disk('public')->exists($source['path']),
                );
                $path = $existing['path'] ?? $this->download($tour, $imageUrl);
                try {
                    $this->promote($tour, $path, $pageUrl, $imageUrl);
                } catch (Throwable $exception) {
                    if (! $existing) {
                        Storage::disk('public')->delete($path);
                    }

                    throw $exception;
                }

                return [
                    'status' => $existing ? 'reused' : 'imported',
                    'path' => $path,
                    'page_url' => $pageUrl,
                    'image_url' => $imageUrl,
                ];
            } catch (Throwable $exception) {
                $lastError = $exception;
            }
        }

        throw new RuntimeException(
            'دریافت کاور از URLهای متناظر geyt.ir ناموفق بود: '.($lastError?->getMessage() ?? 'خطای نامشخص'),
            previous: $lastError,
        );
    }

    public function sourceUrls(Tour $tour): array
    {
        return LegacyRedirect::query()
            ->where('tour_id', $tour->id)
            ->orderByRaw("CASE WHEN match_type = 'manual' THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->pluck('source_url')
            ->filter(fn (string $url) => $this->isGeytDetailUrl($url))
            ->unique()
            ->values()
            ->all();
    }

    private function request(string $url, string $accept): Response
    {
        return Http::timeout(30)
            ->connectTimeout(10)
            ->retry(2, 500)
            ->withUserAgent('Mozilla/5.0 (compatible; GeytCoverImporter/1.0; +https://geyt.ir)')
            ->accept($accept)
            ->get($url)
            ->throw();
    }

    private function extractCoverUrl(string $html, string $pageUrl): ?string
    {
        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $xpath = new DOMXPath($dom);
        foreach ([
            '//meta[translate(@property,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="og:image"]/@content',
            '//meta[translate(@name,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="twitter:image"]/@content',
        ] as $query) {
            $value = trim((string) $xpath->evaluate("string({$query})"));
            if ($value !== '') {
                return $this->absoluteImageUrl(html_entity_decode($value, ENT_QUOTES | ENT_HTML5), $pageUrl);
            }
        }

        foreach ($xpath->query('//script[@type="application/ld+json"]') ?: [] as $script) {
            $schema = json_decode(trim($script->textContent), true);
            $image = data_get($schema, 'image.0') ?: data_get($schema, 'image.url') ?: data_get($schema, 'image');
            if (is_string($image) && trim($image) !== '') {
                return $this->absoluteImageUrl($image, $pageUrl);
            }
        }

        return null;
    }

    private function absoluteImageUrl(string $imageUrl, string $pageUrl): string
    {
        if (str_starts_with($imageUrl, '//')) {
            $imageUrl = 'https:'.$imageUrl;
        } elseif (str_starts_with($imageUrl, '/')) {
            $imageUrl = 'https://geyt.ir'.$imageUrl;
        } elseif (! str_starts_with($imageUrl, 'http://') && ! str_starts_with($imageUrl, 'https://')) {
            $imageUrl = rtrim(dirname($pageUrl), '/').'/'.ltrim($imageUrl, '/');
        }

        $host = strtolower((string) parse_url($imageUrl, PHP_URL_HOST));
        if (! in_array($host, ['geyt.ir', 'www.geyt.ir'], true)) {
            throw new RuntimeException('دامنه تصویر کاور خارج از geyt.ir است.');
        }

        return $imageUrl;
    }

    private function download(Tour $tour, string $imageUrl): string
    {
        $response = $this->request($imageUrl, 'image/avif,image/webp,image/png,image/jpeg');
        $body = $response->body();
        $maxBytes = (int) config('crawler.images.max_bytes', 8_388_608);
        if ($body === '' || strlen($body) > $maxBytes || ! is_array(@getimagesizefromstring($body))) {
            throw new RuntimeException('فایل کاور دریافتی معتبر نیست یا بیش از حد مجاز حجم دارد.');
        }

        $path = 'tours/geyt-covers/'.$tour->id.'/'.hash('sha256', $imageUrl).'.webp';
        $encoded = $this->webp->encode($body);
        if (! Storage::disk('public')->put($path, $encoded)) {
            throw new RuntimeException('ذخیره کاور WebP دریافت‌شده از geyt.ir ناموفق بود.');
        }

        return $path;
    }

    private function promote(Tour $tour, string $path, string $pageUrl, string $imageUrl): void
    {
        DB::transaction(function () use ($tour, $path, $pageUrl, $imageUrl): void {
            $tour = Tour::query()->lockForUpdate()->findOrFail($tour->id);
            $gallery = collect([$tour->cover_image])
                ->concat($tour->gallery ?? [])
                ->filter()
                ->reject(fn (string $current) => $current === $path)
                ->unique()
                ->values()
                ->all();
            $sources = collect($tour->image_sources ?? [])
                ->reject(fn (array $source) => ($source['source'] ?? null) === 'geyt_cover'
                    && ($source['page_url'] ?? null) === $pageUrl)
                ->prepend([
                    'path' => $path,
                    'page_url' => $pageUrl,
                    'image_url' => $imageUrl,
                    'artist' => 'geyt.ir',
                    'source' => 'geyt_cover',
                    'imported_at' => now()->toIso8601String(),
                ])
                ->values()
                ->all();

            $tour->update([
                'cover_image' => $path,
                'gallery' => $gallery,
                'image_sources' => $sources,
            ]);
        });
    }

    private function isGeytDetailUrl(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $path = rawurldecode((string) parse_url($url, PHP_URL_PATH));

        return in_array($host, ['geyt.ir', 'www.geyt.ir'], true)
            && (bool) preg_match('~^/(tour|hotel|accommodation|visa)/[^/]+/?$~u', $path);
    }
}
