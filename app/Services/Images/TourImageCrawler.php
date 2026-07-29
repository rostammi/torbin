<?php

namespace App\Services\Images;

use App\Models\Tour;
use App\Models\TourSuggestion;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

class TourImageCrawler
{
    public function crawl(Tour $tour, bool $replace = false, bool $append = false, ?int $limit = null): array
    {
        $tour->refresh();

        if ($tour->cover_image && ! $replace && ! $append) {
            return ['downloaded' => 0, 'skipped' => true, 'query' => null];
        }

        $query = $this->searchQuery($tour);
        $candidates = $this->search($query);
        $fallbackQuery = $this->fallbackSearchQuery($tour);
        if ($candidates === [] && $fallbackQuery && $fallbackQuery !== $query) {
            $candidates = $this->search($fallbackQuery);
        }
        $images = [];
        $limit ??= (int) config('crawler.images.count', 4);

        foreach ($candidates as $candidate) {
            if (count($images) >= $limit) {
                break;
            }

            if ($append && $this->sourceAlreadyExists($tour, $candidate)) {
                continue;
            }

            $image = $this->download($tour, $candidate);
            if ($image !== null) {
                $images[] = $image;
            }
        }

        if ($images === []) {
            throw new RuntimeException("تصویر معتبر با حداقل ابعاد برای {$tour->title} پیدا نشد.");
        }

        $newPaths = collect($images)->pluck('path');
        $oldPaths = collect([$tour->cover_image])
            ->concat($tour->gallery ?? [])
            ->filter()
            ->unique();
        $gallery = ($replace ? collect() : collect($tour->gallery ?? []))
            ->concat($append && $tour->cover_image ? $newPaths : $newPaths->skip(1))
            ->unique()
            ->values()
            ->all();

        try {
            $tour->update([
                'cover_image' => $append && $tour->cover_image ? $tour->cover_image : $images[0]['path'],
                'gallery' => $gallery,
                'image_sources' => ($replace ? collect() : collect($tour->image_sources ?? []))
                    ->concat($images)
                    ->unique('path')
                    ->values()
                    ->all(),
            ]);
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($newPaths->all());

            throw $exception;
        }

        if ($replace) {
            Storage::disk('public')->delete($oldPaths->diff($newPaths)->all());
        }

        return [
            'downloaded' => count($images),
            'replaced' => $replace,
            'appended' => $append,
            'skipped' => false,
            'query' => $query,
        ];
    }

    private function sourceAlreadyExists(Tour $tour, array $candidate): bool
    {
        $pageUrl = 'https://commons.wikimedia.org/wiki/'.rawurlencode(str_replace(' ', '_', $candidate['title']));

        return collect($tour->image_sources ?? [])->contains(
            fn (array $source) => ($source['page_url'] ?? null) === $pageUrl
        );
    }

    private function searchQuery(Tour $tour): string
    {
        $suggestion = TourSuggestion::query()
            ->where('tour_id', $tour->id)
            ->whereNotNull('destination')
            ->first();

        if ($suggestion) {
            $customQuery = trim((string) data_get($suggestion->metadata, 'image_query'));
            if ($customQuery !== '') {
                return $customQuery;
            }

            return (string) data_get(
                config('crawler.images.aliases', []),
                trim($suggestion->destination),
                trim($suggestion->destination),
            );
        }

        return str($tour->title)
            ->replaceMatches('/(?:^|\s)(?:تور|ارزان|لحظه آخری|اقساطی|هوایی|مقایسه قیمت|خرید)(?=\s|$)/u', ' ')
            ->replaceMatches('/[|\-–—].*$/u', '')
            ->squish()
            ->toString();
    }

    private function fallbackSearchQuery(Tour $tour): ?string
    {
        $destination = TourSuggestion::query()
            ->where('tour_id', $tour->id)
            ->whereNotNull('destination')
            ->value('destination');

        if (! $destination) {
            return null;
        }

        return (string) data_get(
            config('crawler.images.aliases', []),
            trim($destination),
            trim($destination),
        );
    }

    private function search(string $query): array
    {
        $response = $this->request()->get(config('crawler.images.api_url'), [
            'action' => 'query',
            'format' => 'json',
            'formatversion' => 2,
            'generator' => 'search',
            'gsrsearch' => $query,
            'gsrnamespace' => 6,
            'gsrlimit' => (int) config('crawler.images.search_limit', 20),
            'prop' => 'imageinfo',
            'iiprop' => 'url|size|mime|extmetadata',
            'iiurlwidth' => (int) config('crawler.images.download_width', 1920),
            'iiextmetadatalanguage' => 'fa',
            'iiextmetadatafilter' => 'Artist|Credit|LicenseShortName|LicenseUrl|UsageTerms|AttributionRequired',
        ])->throw();

        return collect($response->json('query.pages', []))
            ->map(function (array $page) {
                $info = data_get($page, 'imageinfo.0', []);

                return [
                    'title' => (string) ($page['title'] ?? ''),
                    'url' => (string) ($info['thumburl'] ?? $info['url'] ?? ''),
                    'width' => (int) ($info['width'] ?? 0),
                    'height' => (int) ($info['height'] ?? 0),
                    'mime' => (string) ($info['thumbmime'] ?? $info['mime'] ?? ''),
                    'artist' => $this->metadata($info, 'Artist'),
                    'credit' => $this->metadata($info, 'Credit'),
                    'license' => $this->metadata($info, 'LicenseShortName'),
                    'license_url' => $this->metadata($info, 'LicenseUrl'),
                ];
            })
            ->filter(fn (array $candidate) => $this->acceptableMetadata($candidate))
            ->unique('url')
            ->values()
            ->all();
    }

    private function download(Tour $tour, array $candidate): ?array
    {
        if (! $this->isAllowedImageUrl($candidate['url'])) {
            return null;
        }

        try {
            $response = $this->request()->withOptions(['stream' => true])->get($candidate['url'])->throw();
            $body = $this->limitedBody($response->toPsrResponse()->getBody());
            if ($body === null) {
                return null;
            }

            $size = @getimagesizefromstring($body);
            if (! is_array($size) || ! $this->acceptableDimensions((int) $size[0], (int) $size[1])) {
                return null;
            }

            $extension = match ((int) ($size[2] ?? 0)) {
                IMAGETYPE_JPEG => 'jpg',
                IMAGETYPE_PNG => 'png',
                IMAGETYPE_WEBP => 'webp',
                default => null,
            };
            if ($extension === null) {
                return null;
            }

            $path = 'tours/crawled/'.$tour->id.'/'.Str::uuid().'.'.$extension;
            if (! Storage::disk('public')->put($path, $body)) {
                throw new RuntimeException('ذخیره تصویر در فضای عمومی ناموفق بود.');
            }

            return [
                'path' => $path,
                'page_url' => 'https://commons.wikimedia.org/wiki/'.rawurlencode(str_replace(' ', '_', $candidate['title'])),
                'artist' => $candidate['artist'] ?: $candidate['credit'] ?: 'Wikimedia Commons',
                'license' => $candidate['license'] ?: 'مجوز آزاد Wikimedia Commons',
                'license_url' => $candidate['license_url'],
                'width' => (int) $size[0],
                'height' => (int) $size[1],
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    private function acceptableMetadata(array $candidate): bool
    {
        return in_array($candidate['mime'], ['image/jpeg', 'image/png', 'image/webp'], true)
            && $this->acceptableDimensions($candidate['width'], $candidate['height']);
    }

    private function acceptableDimensions(int $width, int $height): bool
    {
        return $width >= (int) config('crawler.images.min_width', 1280)
            && $height >= (int) config('crawler.images.min_height', 720)
            && ($height > 0 && $width / $height >= (float) config('crawler.images.min_aspect_ratio', 1.2));
    }

    private function isAllowedImageUrl(string $url): bool
    {
        return parse_url($url, PHP_URL_SCHEME) === 'https'
            && strtolower((string) parse_url($url, PHP_URL_HOST)) === 'upload.wikimedia.org';
    }

    private function metadata(array $info, string $key): string
    {
        $value = (string) data_get($info, "extmetadata.{$key}.value", '');

        return str(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8'))
            ->squish()
            ->limit(500, '')
            ->toString();
    }

    private function limitedBody(StreamInterface $stream): ?string
    {
        $maximum = (int) config('crawler.images.max_bytes', 8_388_608);
        $body = '';

        while (! $stream->eof()) {
            $body .= $stream->read(8192);
            if (strlen($body) > $maximum) {
                return null;
            }
        }

        return $body;
    }

    private function request(): PendingRequest
    {
        return Http::withUserAgent(config('crawler.user_agent'))
            ->timeout(25)
            ->retry(2, 500);
    }
}
