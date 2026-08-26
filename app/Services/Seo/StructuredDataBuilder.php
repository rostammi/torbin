<?php

namespace App\Services\Seo;

use App\Models\PriceSource;
use App\Models\StaticPage;
use App\Models\Tour;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StructuredDataBuilder
{
    public function home(): array
    {
        $url = url('/');
        $catalogs = collect(config('comparison.categories'))->map(fn (array $config) => [
            '@type' => 'OfferCatalog',
            'name' => $config['plural'],
            'url' => route($config['route'].'.index').'/',
        ])->values()->all();

        return $this->graph([
            [
                '@type' => 'WebPage', '@id' => $url.'#webpage', 'url' => $url,
                'name' => 'گیت | مقایسه خدمات سفر',
                'description' => 'مقایسه قیمت تور، هتل، اقامتگاه و خدمات ویزا از ارائه‌دهندگان معتبر.',
                'inLanguage' => 'fa-IR', 'isPartOf' => ['@id' => $this->websiteId()],
                'about' => ['@id' => $this->organizationId()],
                'mainEntity' => ['@id' => $url.'#service-catalog'],
            ],
            [
                '@type' => 'OfferCatalog', '@id' => $url.'#service-catalog',
                'name' => 'خدمات قابل مقایسه در گیت', 'itemListElement' => $catalogs,
            ],
        ]);
    }

    public function listing(string $category, Collection $items, string $url): array
    {
        $config = config("comparison.categories.{$category}");
        $listId = $url.'#item-list';

        return $this->graph([
            [
                '@type' => 'CollectionPage', '@id' => $url.'#webpage', 'url' => $url,
                'name' => 'مقایسه '.$config['plural'],
                'description' => 'مقایسه قیمت و پیشنهادهای '.$config['plural'].' از ارائه‌دهندگان معتبر در گیت.',
                'inLanguage' => 'fa-IR', 'isPartOf' => ['@id' => $this->websiteId()],
                'about' => $this->categorySubject($category), 'mainEntity' => ['@id' => $listId],
                'breadcrumb' => ['@id' => $url.'#breadcrumb'],
            ],
            $this->itemList($items, $listId),
            $this->breadcrumbs([
                ['name' => 'گیت', 'url' => url('/')],
                ['name' => $config['plural'], 'url' => $url],
            ], $url.'#breadcrumb'),
        ]);
    }

    public function detail(Tour $tour, Collection $offers): array
    {
        $url = $tour->publicUrl();
        $entityId = $url.'#primary-entity';
        $description = trim($tour->excerpt ?: Str::limit(strip_tags($tour->description), 250));
        $entity = [
            '@type' => $this->entityType($tour->category), '@id' => $entityId,
            'name' => $tour->title, 'description' => $description, 'url' => $url,
            'inLanguage' => 'fa-IR', 'broker' => ['@id' => $this->organizationId()],
        ];
        if ($tour->category === 'tour') {
            $entity['touristType'] = 'گردشگری و سفر تفریحی';
        } else {
            $entity['serviceType'] = $this->serviceType($tour->category);
        }

        $images = collect([$tour->cover_image, ...($tour->gallery ?? [])])->filter()->unique()
            ->map(fn (string $image) => url(Storage::url($image)))->values()->all();
        if ($images !== []) {
            $entity['image'] = $images;
        }

        $pricedOffers = $offers->where('latest_price', '>', 0)
            ->map(fn (PriceSource $source) => $this->offer($source, $entityId))->values();
        if ($pricedOffers->isNotEmpty()) {
            $prices = $pricedOffers->pluck('price')->map(fn (string $price) => (int) $price);
            $entity['offers'] = [
                '@type' => 'AggregateOffer', '@id' => $url.'#offers', 'priceCurrency' => 'IRR',
                'lowPrice' => (string) $prices->min(), 'highPrice' => (string) $prices->max(),
                'offerCount' => $pricedOffers->count(), 'offers' => $pricedOffers->all(),
            ];
        }

        return $this->graph([
            [
                '@type' => 'WebPage', '@id' => $url.'#webpage', 'url' => $url,
                'name' => $tour->title, 'description' => $description, 'inLanguage' => 'fa-IR',
                'isPartOf' => ['@id' => $this->websiteId()], 'mainEntity' => ['@id' => $entityId],
                'breadcrumb' => ['@id' => $url.'#breadcrumb'],
                'datePublished' => $tour->created_at?->toAtomString(),
                'dateModified' => $tour->updated_at?->toAtomString(),
            ],
            $entity,
            $this->breadcrumbs([
                ['name' => 'گیت', 'url' => url('/')],
                ['name' => $tour->categoryPlural(), 'url' => route($tour->categoryConfig('route').'.index').'/' ],
                ['name' => $tour->title, 'url' => $url],
            ], $url.'#breadcrumb'),
        ]);
    }

    public function staticPage(StaticPage $page): array
    {
        $url = $page->publicUrl();
        $description = Str::limit(trim(strip_tags($page->content)), 250);
        $pageType = match ($page->slug) {
            'about-us' => 'AboutPage', 'contact-us' => 'ContactPage',
            'faq' => 'FAQPage', default => 'WebPage',
        };
        $webPage = [
            '@type' => $pageType, '@id' => $url.'#webpage', 'url' => $url,
            'name' => $page->title, 'description' => $description, 'inLanguage' => 'fa-IR',
            'isPartOf' => ['@id' => $this->websiteId()], 'breadcrumb' => ['@id' => $url.'#breadcrumb'],
            'datePublished' => $page->created_at?->toAtomString(),
            'dateModified' => $page->updated_at?->toAtomString(),
        ];
        $nodes = [];
        if ($page->slug === 'faq') {
            $webPage['mainEntity'] = $this->faqEntities($page->content);
        } elseif (in_array($page->slug, ['about-us', 'contact-us'], true)) {
            $webPage['mainEntity'] = ['@id' => $this->organizationId()];
        } elseif (in_array($page->slug, StaticPage::MAG_SLUGS, true)) {
            $articleId = $url.'#article';
            $webPage['mainEntity'] = ['@id' => $articleId];
            $article = [
                '@type' => 'BlogPosting', '@id' => $articleId, 'url' => $url,
                'headline' => $page->title, 'description' => $description, 'inLanguage' => 'fa-IR',
                'author' => ['@id' => $this->organizationId()],
                'publisher' => ['@id' => $this->organizationId()],
                'datePublished' => $page->created_at?->toAtomString(),
                'dateModified' => $page->updated_at?->toAtomString(),
                'isPartOf' => ['@id' => route('mag.index').'/#blog'],
            ];
            if ($image = $this->firstImage($page->content)) {
                $article['image'] = $image;
            }
            $nodes[] = $article;
        }

        return $this->graph([
            $webPage, ...$nodes,
            $this->breadcrumbs([
                ['name' => 'گیت', 'url' => url('/')],
                ...in_array($page->slug, StaticPage::MAG_SLUGS, true)
                    ? [['name' => 'مگ', 'url' => route('mag.index').'/']]
                    : [],
                ['name' => $page->title, 'url' => $url],
            ], $url.'#breadcrumb'),
        ]);
    }

    public function magIndex(Collection $pages): array
    {
        $url = route('mag.index').'/';
        $blogId = $url.'#blog';
        $posts = $pages->map(fn (StaticPage $page) => array_filter([
            '@type' => 'BlogPosting', '@id' => $page->publicUrl().'#article',
            'url' => $page->publicUrl(), 'headline' => $page->title,
            'image' => $this->firstImage($page->content),
            'datePublished' => $page->created_at?->toAtomString(),
            'dateModified' => $page->updated_at?->toAtomString(),
        ], fn ($value) => $value !== null && $value !== ''))->values()->all();

        return $this->graph([
            [
                '@type' => 'CollectionPage', '@id' => $url.'#webpage', 'url' => $url,
                'name' => 'مگ گیت', 'description' => 'راهنمای سفر، انتخاب تور، رزرو هتل و اقامتگاه در مگ گیت.',
                'inLanguage' => 'fa-IR', 'isPartOf' => ['@id' => $this->websiteId()],
                'mainEntity' => ['@id' => $blogId], 'breadcrumb' => ['@id' => $url.'#breadcrumb'],
            ],
            [
                '@type' => 'Blog', '@id' => $blogId, 'url' => $url, 'name' => 'مگ گیت',
                'inLanguage' => 'fa-IR', 'publisher' => ['@id' => $this->organizationId()],
                'blogPost' => $posts,
            ],
            $this->breadcrumbs([
                ['name' => 'گیت', 'url' => url('/')], ['name' => 'مگ', 'url' => $url],
            ], $url.'#breadcrumb'),
        ]);
    }

    public function provider(string $name, string $url, Collection $items): array
    {
        $providerId = $url.'#provider';
        $catalogId = $url.'#catalog';

        return $this->graph([
            [
                '@type' => 'ProfilePage', '@id' => $url.'#webpage', 'url' => $url,
                'name' => 'پیشنهادهای '.$name,
                'description' => 'مشاهده و مقایسه خدمات سفر ارائه‌شده توسط '.$name.' در گیت.',
                'inLanguage' => 'fa-IR', 'isPartOf' => ['@id' => $this->websiteId()],
                'mainEntity' => ['@id' => $providerId], 'breadcrumb' => ['@id' => $url.'#breadcrumb'],
            ],
            [
                '@type' => 'TravelAgency', '@id' => $providerId, 'name' => $name, 'url' => $url,
                'areaServed' => ['@type' => 'Country', 'name' => 'ایران'],
                'hasOfferCatalog' => ['@id' => $catalogId],
            ],
            [
                '@type' => 'OfferCatalog', '@id' => $catalogId, 'name' => 'خدمات '.$name,
                'itemListElement' => $this->itemListElements($items),
            ],
            $this->breadcrumbs([
                ['name' => 'گیت', 'url' => url('/')], ['name' => $name, 'url' => $url],
            ], $url.'#breadcrumb'),
        ]);
    }

    private function graph(array $nodes): array
    {
        return [['@context' => 'https://schema.org', '@graph' => [...$this->siteNodes(), ...$nodes]]];
    }

    private function siteNodes(): array
    {
        return [
            [
                '@type' => 'Organization', '@id' => $this->organizationId(),
                'name' => 'گیت', 'url' => url('/'),
                'description' => 'موتور جست‌وجو و مرجع مستقل مقایسه خدمات سفر',
                'logo' => [
                    '@type' => 'ImageObject', '@id' => url('/').'#logo',
                    'url' => asset('images/geyt-logo.png'), 'contentUrl' => asset('images/geyt-logo.png'),
                    'caption' => 'لوگوی گیت', 'width' => 299, 'height' => 80,
                ],
                'email' => 'info@geyt.ir', 'telephone' => '+989199010216',
                'sameAs' => [
                    'https://www.linkedin.com/company/geyt/',
                    'https://www.instagram.com/geyt.ir',
                ],
                'contactPoint' => [
                    '@type' => 'ContactPoint', 'contactType' => 'customer support',
                    'telephone' => '+989199010216', 'email' => 'info@geyt.ir',
                    'availableLanguage' => ['fa'],
                ],
                'knowsAbout' => ['تور', 'هتل', 'اقامتگاه', 'خدمات ویزا', 'مقایسه قیمت خدمات سفر'],
            ],
            [
                '@type' => 'WebSite', '@id' => $this->websiteId(), 'url' => url('/'),
                'name' => 'گیت', 'inLanguage' => 'fa-IR',
                'publisher' => ['@id' => $this->organizationId()],
                'potentialAction' => [
                    '@type' => 'SearchAction',
                    'target' => ['@type' => 'EntryPoint', 'urlTemplate' => url('/search/{search_term_string}/')],
                    'query-input' => 'required name=search_term_string',
                ],
            ],
        ];
    }

    private function offer(PriceSource $source, string $entityId): array
    {
        $price = $source->currency === 'ریال' ? $source->latest_price : $source->latest_price * 10;
        $seller = ['@type' => 'TravelAgency', 'name' => $source->provider_name];
        if ($source->agency) {
            $seller['@id'] = $source->agency->publicUrl().'#provider';
            $seller['url'] = $source->agency->publicUrl();
        }

        return [
            '@type' => 'Offer', '@id' => route('outbound.click', $source).'#offer',
            'url' => route('outbound.click', $source), 'price' => (string) $price,
            'priceCurrency' => 'IRR', 'availability' => 'https://schema.org/InStock',
            'itemOffered' => ['@id' => $entityId], 'seller' => $seller,
        ];
    }

    private function itemList(Collection $items, string $id): array
    {
        return [
            '@type' => 'ItemList', '@id' => $id, 'numberOfItems' => $items->count(),
            'itemListOrder' => 'https://schema.org/ItemListOrderAscending',
            'itemListElement' => $this->itemListElements($items),
        ];
    }

    private function itemListElements(Collection $items): array
    {
        return $items->values()->map(function ($item, int $index) {
            $entity = [
                '@type' => $this->entityType($item->category ?? 'tour'),
                '@id' => $item->publicUrl().'#primary-entity',
                'name' => $item->title, 'url' => $item->publicUrl(),
            ];
            if ($item instanceof Tour && $item->category !== 'tour') {
                $entity['serviceType'] = $this->serviceType($item->category);
            }
            if ($item instanceof Tour && $item->cover_image) {
                $entity['image'] = url(Storage::url($item->cover_image));
            }

            return ['@type' => 'ListItem', 'position' => $index + 1, 'item' => $entity];
        })->all();
    }

    private function breadcrumbs(array $items, string $id): array
    {
        return [
            '@type' => 'BreadcrumbList', '@id' => $id,
            'itemListElement' => collect($items)->map(fn (array $item, int $index) => [
                '@type' => 'ListItem', 'position' => $index + 1,
                'name' => $item['name'], 'item' => $item['url'],
            ])->all(),
        ];
    }

    private function categorySubject(string $category): array
    {
        return [
            '@type' => $this->entityType($category),
            'name' => config("comparison.categories.{$category}.plural"),
            ...$category === 'tour' ? [] : ['serviceType' => $this->serviceType($category)],
        ];
    }

    private function entityType(string $category): string
    {
        return $category === 'tour' ? 'TouristTrip' : 'Service';
    }

    private function serviceType(string $category): string
    {
        return match ($category) {
            'hotel' => 'جست‌وجو، مقایسه قیمت و رزرو هتل',
            'stay' => 'جست‌وجو، مقایسه قیمت و رزرو اقامتگاه',
            'visa' => 'مقایسه و دریافت خدمات ویزا',
            default => 'خدمات سفر',
        };
    }

    private function faqEntities(string $html): array
    {
        [$document, $xpath] = $this->htmlDocument($html);
        if (! $document || ! $xpath) {
            return [];
        }

        $questions = [];
        foreach ($xpath->query('//h2|//h3') ?: [] as $heading) {
            $answer = $heading->nextSibling;
            while ($answer && ! ($answer->nodeType === XML_ELEMENT_NODE && strtolower($answer->nodeName) === 'p')) {
                $answer = $answer->nextSibling;
            }
            $name = $this->cleanText($heading->textContent);
            $text = $answer ? $this->cleanText($answer->textContent) : '';
            if ($name !== '' && $text !== '') {
                $questions[] = [
                    '@type' => 'Question', 'name' => $name,
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => $text],
                ];
            }
        }

        return $questions;
    }

    private function firstImage(string $html): ?string
    {
        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $html, $image) !== 1) {
            return null;
        }

        return Str::startsWith($image[1], ['http://', 'https://']) ? $image[1] : url($image[1]);
    }

    private function htmlDocument(string $html): array
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $loaded ? [$document, new DOMXPath($document)] : [null, null];
    }

    private function cleanText(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
    }

    private function organizationId(): string
    {
        return url('/').'#organization';
    }

    private function websiteId(): string
    {
        return url('/').'#website';
    }
}
