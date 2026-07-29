<?php

namespace App\Services\Discovery;

use App\Models\TourSuggestion;
use Illuminate\Support\Collection;

class PopularTourDiscovery
{
    public const DOMESTIC_DESTINATIONS = [
        'کیش', 'مشهد', 'قشم', 'شیراز', 'اصفهان', 'یزد', 'تبریز', 'چابهار', 'رشت', 'ماسال',
        'رامسر', 'لاهیجان', 'بندر انزلی', 'اردبیل', 'سرعین', 'همدان', 'کرمان', 'کرمانشاه', 'سنندج',
        'مریوان', 'اهواز', 'شوشتر', 'بوشهر', 'بندرعباس', 'جزیره هرمز', 'لرستان', 'خرم‌آباد', 'کاشان',
        'تهران', 'دماوند', 'مازندران', 'گیلان', 'کویر مرنجاب', 'طبس', 'کلاردشت', 'نمک‌آبرود',
    ];

    public const FOREIGN_DESTINATIONS = [
        'استانبول', 'دبی', 'آنتالیا', 'تفلیس', 'باتومی', 'وان', 'باکو', 'ارمنستان', 'ایروان', 'تایلند',
        'پوکت', 'پاتایا', 'بانکوک', 'بالی', 'مالدیو', 'روسیه', 'مسکو', 'سنت پترزبورگ', 'گرجستان',
        'ترکیه', 'عمان', 'مسقط', 'قطر', 'دوحه', 'چین', 'پکن', 'شانگهای', 'ژاپن', 'توکیو', 'هند',
        'گوا', 'دهلی', 'سریلانکا', 'مالزی', 'کوالالامپور', 'سنگاپور', 'ویتنام', 'آفریقای جنوبی',
        'زنگبار', 'کنیا', 'مصر', 'فرانسه', 'پاریس', 'ایتالیا', 'رم', 'اسپانیا', 'بارسلونا', 'سوئیس',
        'اتریش', 'آلمان', 'هلند', 'یونان', 'آتن', 'قبرس', 'لارناکا', 'برزیل', 'ریودوژانیرو', 'موریس',
        'سیشل', 'اندونزی', 'ازبکستان', 'سمرقند', 'قزاقستان', 'آلماتی', 'قرقیزستان', 'لبنان', 'بیروت',
    ];

    public const VARIANTS = [
        'main' => 'تور %s',
        'cheap' => 'تور %s ارزان',
        'last_minute' => 'تور %s لحظه آخری',
        'installment' => 'تور %s اقساطی',
        'air' => 'تور هوایی %s',
        'from_tehran' => 'تور %s از تهران',
    ];

    public function discover(?int $limit = null): array
    {
        $perRegionLimit = max(1, $limit ?? (int) config('crawler.suggestions_limit', 120));
        $domestic = $this->catalogCandidates(self::DOMESTIC_DESTINATIONS, 'domestic', $perRegionLimit);
        $foreign = $this->catalogCandidates(self::FOREIGN_DESTINATIONS, 'foreign', $perRegionLimit);

        $saved = [];
        foreach ($domestic->concat($foreign) as $item) {
            $suggestion = TourSuggestion::updateOrCreate([
                'category' => 'tour',
                'destination' => $item['destination'],
            ], [
                'keyword' => $item['keyword'],
                'suggested_title' => $this->seoTitle($item['keyword']),
                'destination' => $item['destination'],
                'trend_score' => $item['score'],
                'source' => 'destination_catalog',
                'metadata' => [
                    'region' => $item['region'],
                    'variant' => 'main',
                    'keywords' => $item['keywords'],
                    'seeded' => true,
                ],
                'discovered_at' => now(),
            ]);
            $saved[] = $suggestion->id;
        }
        TourSuggestion::query()
            ->where('category', 'tour')
            ->where('source', 'destination_catalog')
            ->whereNotIn('id', $saved)
            ->delete();

        return [
            'total' => count($saved),
            'domestic_total' => $domestic->count(),
            'foreign_total' => $foreign->count(),
            'ids' => $saved,
            'source' => 'destination_catalog',
            'trends_received' => 0,
        ];
    }

    private function catalogCandidates(array $destinations, string $region, int $limit): Collection
    {
        return collect($destinations)
            ->map(function (string $destination, int $destinationIndex) use ($region) {
                return [
                    'keyword' => sprintf(self::VARIANTS['main'], $destination),
                    'keywords' => collect(self::VARIANTS)
                        ->map(fn (string $template) => sprintf($template, $destination))
                        ->values()
                        ->all(),
                    'destination' => $destination,
                    'region' => $region,
                    'score' => max(20, 95 - $destinationIndex),
                ];
            })
            ->sortByDesc('score')
            ->take($limit)
            ->values();
    }

    private function seoTitle(string $keyword): string
    {
        return $keyword.' | مقایسه قیمت و خرید از معتبرترین آژانس‌ها';
    }
}
