<?php

namespace App\Services\Discovery;

use App\Models\SearchMiss;
use App\Models\TourSuggestion;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class PopularTourDiscovery
{
    private const DESTINATIONS = [
        'کیش', 'مشهد', 'استانبول', 'دبی', 'قشم', 'شیراز', 'آنتالیا', 'تفلیس', 'باتومی', 'وان',
        'باکو', 'ارمنستان', 'ایروان', 'تایلند', 'پوکت', 'پاتایا', 'بانکوک', 'بالی', 'مالدیو', 'روسیه',
        'مسکو', 'سنت پترزبورگ', 'گرجستان', 'ترکیه', 'عمان', 'مسقط', 'قطر', 'دوحه', 'چین', 'پکن',
        'شانگهای', 'ژاپن', 'توکیو', 'هند', 'گوا', 'دهلی', 'سریلانکا', 'مالزی', 'کوالالامپور', 'سنگاپور',
        'ویتنام', 'آفریقای جنوبی', 'زنگبار', 'کنیا', 'مصر', 'اروپا', 'فرانسه', 'پاریس', 'ایتالیا', 'رم',
        'اسپانیا', 'بارسلونا', 'سوئیس', 'اتریش', 'آلمان', 'هلند', 'یزد', 'اصفهان', 'تبریز', 'کرمان',
        'اهواز', 'چابهار', 'رشت', 'ماسال', 'رامسر', 'لرستان', 'کردستان', 'کرمانشاه', 'بوشهر', 'بندرعباس',
    ];

    public function discover(?int $limit = null): array
    {
        $limit ??= max(100, (int) config('crawler.suggestions_limit', 120));
        $trendItems = $this->googleTrendsItems();
        $searchMisses = SearchMiss::query()
            ->selectRaw('normalized_query, count(*) as searches')
            ->where('searched_at', '>=', now()->subDays(30))
            ->groupBy('normalized_query')
            ->orderByDesc('searches')
            ->limit(50)
            ->get();

        $candidates = collect($trendItems)
            ->map(fn (array $item) => [
                'keyword' => $this->tourKeyword($item['title']),
                'destination' => $this->destination($item['title']),
                'score' => $item['score'],
                'source' => 'google_trends',
                'metadata' => ['trend_title' => $item['title'], 'traffic' => $item['traffic']],
            ])
            ->filter(fn (array $item) => $item['destination'] !== null || $this->looksTravelRelated($item['keyword']));

        foreach ($searchMisses as $miss) {
            if ($this->looksTravelRelated($miss->normalized_query)) {
                $candidates->push([
                    'keyword' => $this->tourKeyword($miss->normalized_query),
                    'destination' => $this->destination($miss->normalized_query),
                    'score' => min(95, 45 + ((int) $miss->searches * 5)),
                    'source' => 'site_search',
                    'metadata' => ['site_searches_30d' => (int) $miss->searches],
                ]);
            }
        }

        foreach (self::DESTINATIONS as $index => $destination) {
            foreach (["تور {$destination}", "تور {$destination} ارزان"] as $variant => $keyword) {
                $candidates->push([
                    'keyword' => $keyword,
                    'destination' => $destination,
                    'score' => max(10, 60 - $index - ($variant * 7)),
                    'source' => 'destination_catalog',
                    'metadata' => ['seeded' => true],
                ]);
            }
        }

        $saved = [];
        foreach ($candidates->unique(fn (array $item) => $this->normalize($item['keyword']))->sortByDesc('score')->take($limit) as $item) {
            $keyword = $this->normalize($item['keyword']);
            $suggestion = TourSuggestion::updateOrCreate(['keyword' => $keyword], [
                'suggested_title' => $this->seoTitle($keyword),
                'destination' => $item['destination'],
                'trend_score' => $item['score'],
                'source' => $item['source'],
                'metadata' => $item['metadata'],
                'discovered_at' => now(),
            ]);
            $saved[] = $suggestion->id;
        }

        return ['total' => count($saved), 'ids' => $saved, 'trends_received' => count($trendItems)];
    }

    private function googleTrendsItems(): array
    {
        try {
            $response = Http::timeout(20)->retry(2, 500)->withUserAgent(config('crawler.user_agent'))
                ->get(config('crawler.trends_feed_url'), ['geo' => config('crawler.trends_geo', 'IR')])->throw();
            $xml = @simplexml_load_string($response->body());
            if ($xml === false) {
                return [];
            }

            $items = [];
            foreach ($xml->channel->item ?? [] as $item) {
                $namespace = $item->children('ht', true);
                $traffic = (string) ($namespace->approx_traffic ?? '');
                $items[] = [
                    'title' => trim((string) $item->title),
                    'traffic' => $traffic,
                    'score' => $this->trafficScore($traffic),
                ];
            }

            return $items;
        } catch (Throwable $exception) {
            report($exception);

            return [];
        }
    }

    private function trafficScore(string $traffic): int
    {
        $number = (int) preg_replace('/[^0-9]/', '', str_replace([',', 'K', 'M'], ['', '000', '000000'], strtoupper($traffic)));

        return min(100, max(60, (int) round(log10(max(10, $number)) * 20)));
    }

    private function looksTravelRelated(string $value): bool
    {
        return Str::contains($this->normalize($value), array_merge(['تور', 'سفر', 'هتل', 'پرواز'], self::DESTINATIONS));
    }

    private function destination(string $value): ?string
    {
        $normalized = $this->normalize($value);

        return collect(self::DESTINATIONS)->first(fn (string $destination) => Str::contains($normalized, $destination));
    }

    private function tourKeyword(string $value): string
    {
        $value = $this->normalize($value);

        return Str::contains($value, 'تور') ? $value : 'تور '.$value;
    }

    private function seoTitle(string $keyword): string
    {
        return trim($keyword).' | مقایسه قیمت و خرید از معتبرترین آژانس‌ها';
    }

    private function normalize(string $value): string
    {
        $value = str_replace(['ي', 'ك', "\u{200C}"], ['ی', 'ک', ' '], mb_strtolower(trim($value)));

        return preg_replace('/\s+/u', ' ', $value) ?? $value;
    }
}
