<?php

namespace App\Services\Seo;

use App\Models\StaticPage;
use App\Models\Tour;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StructuredDataBuilder
{
    public function home(): array
    {
        return [[
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'Organization',
                    '@id' => url('/').'#organization',
                    'name' => 'گیت',
                    'url' => url('/'),
                    'logo' => asset('images/geyt-logo.png'),
                    'email' => 'info@geyt.ir',
                    'telephone' => '+989199010216',
                ],
                [
                    '@type' => 'WebSite',
                    '@id' => url('/').'#website',
                    'url' => url('/'),
                    'name' => 'گیت',
                    'inLanguage' => 'fa-IR',
                    'publisher' => ['@id' => url('/').'#organization'],
                ],
            ],
        ]];
    }

    public function listing(string $category, Collection $items, string $url): array
    {
        $config = config("comparison.categories.{$category}");

        return [[
            '@context' => 'https://schema.org',
            '@type' => 'CollectionPage',
            '@id' => $url.'#collection',
            'url' => $url,
            'name' => 'مقایسه '.$config['plural'],
            'inLanguage' => 'fa-IR',
            'mainEntity' => $this->itemList($items),
        ], $this->breadcrumbs([
            ['name' => 'گیت', 'url' => url('/')],
            ['name' => $config['plural'], 'url' => $url],
        ])];
    }

    public function detail(Tour $tour, Collection $offers): array
    {
        $url = $tour->publicUrl();
        $description = trim($tour->excerpt ?: Str::limit(strip_tags($tour->description), 250));
        $entity = [
            '@type' => $tour->category === 'tour' ? 'TouristTrip' : 'Service',
            '@id' => $url.'#offering',
            'name' => $tour->title,
            'description' => $description,
            'url' => $url,
            'provider' => ['@id' => url('/').'#organization'],
        ];
        if ($tour->cover_image) {
            $entity['image'] = url(Storage::url($tour->cover_image));
        }

        $pricedOffers = $offers->where('latest_price', '>', 0)->map(function ($source) use ($url) {
            $price = $source->currency === 'ریال' ? $source->latest_price : $source->latest_price * 10;

            return [
                '@type' => 'Offer',
                'url' => $url,
                'price' => (string) $price,
                'priceCurrency' => 'IRR',
                'availability' => 'https://schema.org/InStock',
                'seller' => ['@type' => 'Organization', 'name' => $source->provider_name],
            ];
        })->values()->all();
        if ($pricedOffers !== []) {
            $entity['offers'] = $pricedOffers;
        }

        return [[
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            '@id' => $url.'#webpage',
            'url' => $url,
            'name' => $tour->title,
            'description' => $description,
            'inLanguage' => 'fa-IR',
            'mainEntity' => $entity,
        ], $this->breadcrumbs([
            ['name' => 'گیت', 'url' => url('/')],
            ['name' => $tour->categoryPlural(), 'url' => route($tour->categoryConfig('route').'.index').'/'],
            ['name' => $tour->title, 'url' => $url],
        ])];
    }

    public function staticPage(StaticPage $page): array
    {
        return [[
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            '@id' => $page->publicUrl().'#webpage',
            'url' => $page->publicUrl(),
            'name' => $page->title,
            'description' => Str::limit(trim(strip_tags($page->content)), 250),
            'inLanguage' => 'fa-IR',
        ], $this->breadcrumbs([
            ['name' => 'گیت', 'url' => url('/')],
            ['name' => $page->title, 'url' => $page->publicUrl()],
        ])];
    }

    public function magIndex(Collection $pages): array
    {
        $url = route('mag.index').'/';

        return [[
            '@context' => 'https://schema.org',
            '@type' => 'CollectionPage',
            '@id' => $url.'#collection',
            'url' => $url,
            'name' => 'مگ گیت',
            'inLanguage' => 'fa-IR',
            'mainEntity' => $this->itemList($pages, fn (StaticPage $page) => $page->publicUrl()),
        ], $this->breadcrumbs([
            ['name' => 'گیت', 'url' => url('/')],
            ['name' => 'مگ', 'url' => $url],
        ])];
    }

    public function provider(string $name, string $url, Collection $items): array
    {
        return [[
            '@context' => 'https://schema.org',
            '@type' => 'CollectionPage',
            '@id' => $url.'#collection',
            'url' => $url,
            'name' => 'پیشنهادهای '.$name,
            'inLanguage' => 'fa-IR',
            'mainEntity' => $this->itemList($items),
        ], $this->breadcrumbs([
            ['name' => 'گیت', 'url' => url('/')],
            ['name' => $name, 'url' => $url],
        ])];
    }

    private function itemList(Collection $items, ?callable $url = null): array
    {
        return [
            '@type' => 'ItemList',
            'numberOfItems' => $items->count(),
            'itemListElement' => $items->values()->map(fn ($item, int $index) => [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $item->title,
                'url' => $url ? $url($item) : $item->publicUrl(),
            ])->all(),
        ];
    }

    private function breadcrumbs(array $items): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => collect($items)->map(fn (array $item, int $index) => [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $item['name'],
                'item' => $item['url'],
            ])->all(),
        ];
    }
}
