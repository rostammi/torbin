<?php

namespace App\Services\Seo;

use App\Models\Agency;
use App\Models\StaticPage;
use App\Models\Tour;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class SitemapGenerator
{
    public function xml(): string
    {
        return view('seo.sitemap', ['urls' => $this->urls()])->render();
    }

    public function write(): string
    {
        $path = (string) config('seo.sitemap.path', public_path('sitemap.xml'));
        $directory = dirname($path);
        if (! is_dir($directory) || ! is_writable($directory)) {
            throw new RuntimeException("مسیر سایت‌مپ قابل نوشتن نیست: {$directory}");
        }

        $lockPath = storage_path('framework/sitemap.lock');
        $lock = fopen($lockPath, 'c');
        if ($lock === false) {
            throw new RuntimeException('ایجاد قفل بازسازی سایت‌مپ ناموفق بود.');
        }

        try {
            if (! flock($lock, LOCK_EX)) {
                throw new RuntimeException('دریافت قفل بازسازی سایت‌مپ ناموفق بود.');
            }

            $temporary = tempnam($directory, '.sitemap-');
            if ($temporary === false) {
                throw new RuntimeException('ایجاد فایل موقت سایت‌مپ ناموفق بود.');
            }

            try {
                $xml = $this->xml();
                if (file_put_contents($temporary, $xml, LOCK_EX) === false) {
                    throw new RuntimeException('نوشتن فایل سایت‌مپ ناموفق بود.');
                }
                chmod($temporary, 0644);
                if (! rename($temporary, $path)) {
                    throw new RuntimeException('جایگزینی اتمیک فایل سایت‌مپ ناموفق بود.');
                }
            } finally {
                if (isset($temporary) && is_file($temporary)) {
                    @unlink($temporary);
                }
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }

        return $path;
    }

    public function urls(): Collection
    {
        $fallbackModifiedAt = $this->fallbackModifiedAt();
        $tours = Tour::query()->published()->withMax('priceSources', 'updated_at')->get();
        $staticPages = StaticPage::query()->where('is_published', true)->get();
        $agencies = Agency::query()->withMax('priceSources', 'updated_at')->orderBy('id')->get();
        $siteModifiedAt = $this->latestModifiedAt(
            $tours->pluck('updated_at')
                ->concat($tours->pluck('price_sources_max_updated_at'))
                ->concat($staticPages->pluck('updated_at'))
                ->concat($agencies->pluck('updated_at'))
                ->concat($agencies->pluck('price_sources_max_updated_at')),
            $fallbackModifiedAt,
        );

        $urls = collect([
            $this->entry(url('/'), $siteModifiedAt, 'daily', '0.9'),
            $this->categoryEntry($tours, 'tour', 'tours.index', $fallbackModifiedAt),
            $this->categoryEntry($tours, 'hotel', 'hotels.index', $fallbackModifiedAt),
            $this->categoryEntry($tours, 'stay', 'stays.index', $fallbackModifiedAt),
            $this->categoryEntry($tours, 'visa', 'visas.index', $fallbackModifiedAt),
            $this->entry(
                route('mag.index').'/',
                $this->latestModifiedAt(
                    $staticPages->whereIn('slug', StaticPage::MAG_SLUGS)->pluck('updated_at'),
                    $fallbackModifiedAt,
                ),
                'weekly',
                '0.8',
            ),
        ]);

        $urls = $urls->concat($tours->map(fn (Tour $tour) => $this->entry(
            $tour->publicUrl(),
            $this->latestModifiedAt([
                $tour->updated_at,
                $tour->price_sources_max_updated_at,
            ], $fallbackModifiedAt),
            'daily',
            '1.0',
            $this->comparisonImageUrl($tour),
        )));
        $urls = $urls->concat($staticPages->map(fn (StaticPage $page) => $this->entry(
            $page->publicUrl(),
            $page->updated_at ?? $fallbackModifiedAt,
            in_array($page->slug, StaticPage::MAG_SLUGS, true) ? 'weekly' : 'monthly',
            in_array($page->slug, StaticPage::MAG_SLUGS, true) ? '0.7' : '0.6',
        )));
        $urls = $urls->concat($this->providerUrls($agencies, $fallbackModifiedAt));

        return $urls->unique('loc')->values();
    }

    private function comparisonImageUrl(Tour $tour): string
    {
        $image = $tour->cover_image ?: data_get($tour->gallery, 0);

        return $image
            ? url(Storage::url($image))
            : asset('images/geyt-social-share.png');
    }

    private function providerUrls(Collection $agencies, CarbonInterface $fallbackModifiedAt): Collection
    {
        return $agencies
            ->groupBy(fn (Agency $agency) => $agency->providerSlug())
            ->map(function (Collection $agencies) use ($fallbackModifiedAt): array {
                $agency = $agencies->first();
                $lastModified = $this->latestModifiedAt(
                    $agencies->pluck('updated_at')->concat($agencies->pluck('price_sources_max_updated_at')),
                    $fallbackModifiedAt,
                );

                return $this->entry($agency->publicUrl(), $lastModified, 'daily', '0.8');
            })
            ->values();
    }

    private function categoryEntry(
        Collection $tours,
        string $category,
        string $route,
        CarbonInterface $fallbackModifiedAt,
    ): array {
        return $this->entry(
            route($route).'/',
            $this->latestModifiedAt(
                $tours->where('category', $category)->pluck('updated_at')
                    ->concat($tours->where('category', $category)->pluck('price_sources_max_updated_at')),
                $fallbackModifiedAt,
            ),
            'daily',
            '0.9',
        );
    }

    private function entry(
        string $location,
        CarbonInterface $lastModified,
        string $changeFrequency,
        string $priority,
        ?string $image = null,
    ): array {
        return array_filter([
            'loc' => $location,
            'lastmod' => $lastModified->toAtomString(),
            'changefreq' => $changeFrequency,
            'priority' => $priority,
            'image' => $image,
        ], fn (mixed $value) => $value !== null);
    }

    private function latestModifiedAt(iterable $values, CarbonInterface $fallback): CarbonInterface
    {
        return collect($values)
            ->filter()
            ->map(fn (mixed $value) => $value instanceof CarbonInterface ? $value : CarbonImmutable::parse($value))
            ->sortByDesc(fn (CarbonInterface $value) => $value->getTimestamp())
            ->first() ?? $fallback;
    }

    private function fallbackModifiedAt(): CarbonInterface
    {
        $files = [
            base_path('routes/web.php'),
            resource_path('views/home.blade.php'),
            resource_path('views/layouts/app.blade.php'),
        ];
        $timestamp = collect($files)
            ->filter(fn (string $file) => is_file($file))
            ->map(fn (string $file) => filemtime($file))
            ->max() ?: time();

        return CarbonImmutable::createFromTimestamp($timestamp, config('app.timezone'));
    }
}
