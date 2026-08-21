<?php

namespace App\Services;

use App\Models\Tour;
use App\Models\TourSuggestion;
use App\Services\Discovery\PopularTourDiscovery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class RelatedComparisons
{
    private const FOREIGN_GROUPS = [
        ['ترکیه', 'استانبول', 'آنتالیا', 'وان'],
        ['امارات', 'دبی'],
        ['گرجستان', 'تفلیس', 'باتومی'],
        ['ارمنستان', 'ایروان'],
        ['تایلند', 'بانکوک', 'پوکت', 'پاتایا'],
        ['روسیه', 'مسکو', 'سنت پترزبورگ'],
        ['عمان', 'مسقط'],
        ['قطر', 'دوحه'],
        ['چین', 'پکن', 'شانگهای'],
        ['ژاپن', 'توکیو'],
        ['هند', 'دهلی', 'گوا'],
        ['مالزی', 'کوالالامپور'],
        ['اندونزی', 'بالی'],
        ['فرانسه', 'پاریس'],
        ['ایتالیا', 'رم'],
        ['اسپانیا', 'بارسلونا'],
        ['یونان', 'آتن'],
        ['قبرس', 'لارناکا'],
        ['ازبکستان', 'سمرقند'],
        ['قزاقستان', 'آلماتی'],
        ['لبنان', 'بیروت'],
        ['برزیل', 'ریودوژانیرو'],
    ];

    private const DOMESTIC_GROUPS = [
        ['رشت', 'ماسال', 'لاهیجان', 'بندر انزلی', 'گیلان'],
        ['رامسر', 'کلاردشت', 'نمک‌آبرود', 'مازندران'],
        ['کیش', 'قشم', 'جزیره هرمز', 'بندرعباس'],
        ['تهران', 'دماوند'],
        ['اصفهان', 'کاشان', 'یزد', 'کویر مرنجاب'],
        ['تبریز', 'اردبیل', 'سرعین'],
        ['همدان', 'کرمانشاه', 'سنندج', 'مریوان'],
        ['اهواز', 'شوشتر'],
        ['لرستان', 'خرم‌آباد'],
        ['شیراز', 'بوشهر'],
    ];

    public function for(Tour $tour, int $limit = 4): Collection
    {
        $current = $tour->suggestions()
            ->whereNotNull('destination')
            ->oldest('id')
            ->first();

        if (! $current) {
            return collect();
        }

        $destination = trim((string) $current->destination);
        $region = $this->region($current, $destination);
        $relatedDestinations = $this->relatedDestinations($destination, $region);
        $allowedDestinations = collect([$destination])->concat($relatedDestinations)->unique()->values();

        return Tour::query()
            ->published()
            ->whereKeyNot($tour->getKey())
            ->whereHas('suggestions', fn (Builder $query) => $query->whereIn('destination', $allowedDestinations))
            ->with(['suggestions' => fn ($query) => $query->whereIn('destination', $allowedDestinations)->oldest('id')])
            ->withPublicPricing()
            ->get()
            ->filter(fn (Tour $candidate) => $candidate->compared_sources_count > 0)
            ->map(function (Tour $candidate) use ($tour, $destination, $region, $relatedDestinations) {
                $score = $candidate->suggestions->max(fn (TourSuggestion $suggestion) => $this->score(
                    $tour,
                    $candidate,
                    $destination,
                    (string) $suggestion->destination,
                    $region,
                    $relatedDestinations,
                ));

                return ['tour' => $candidate, 'score' => (int) $score];
            })
            ->sortByDesc(fn (array $item) => [
                $item['score'],
                $item['tour']->compared_sources_count,
                -$item['tour']->getKey(),
            ])
            ->pluck('tour')
            ->take(max(1, $limit))
            ->values();
    }

    private function score(
        Tour $current,
        Tour $candidate,
        string $destination,
        string $candidateDestination,
        string $region,
        Collection $relatedDestinations,
    ): int {
        $destinationScore = $candidateDestination === $destination
            ? 100
            : max(40, 60 - (int) $relatedDestinations->search($candidateDestination));

        $categoryScore = match ($current->category) {
            'tour' => match ($candidate->category) {
                'visa' => $region === 'foreign' ? 45 : 0,
                'hotel' => 35,
                'stay' => 30,
                'tour' => 15,
                default => 0,
            },
            'hotel' => match ($candidate->category) {
                'tour' => 45,
                'visa' => $region === 'foreign' ? 20 : 0,
                'stay' => 15,
                'hotel' => 5,
                default => 0,
            },
            'stay' => match ($candidate->category) {
                'tour' => 45,
                'hotel' => 30,
                'stay' => 5,
                default => 0,
            },
            'visa' => match ($candidate->category) {
                'tour' => 45,
                'hotel' => 35,
                'stay' => 25,
                'visa' => 5,
                default => 0,
            },
            default => 0,
        };

        return $destinationScore + $categoryScore;
    }

    private function relatedDestinations(string $destination, string $region): Collection
    {
        $groups = $region === 'domestic' ? self::DOMESTIC_GROUPS : self::FOREIGN_GROUPS;
        $group = collect($groups)->first(fn (array $destinations) => in_array($destination, $destinations, true));

        return collect($group ?? [])->reject(fn (string $item) => $item === $destination)->values();
    }

    private function region(TourSuggestion $suggestion, string $destination): string
    {
        $region = (string) data_get($suggestion->metadata, 'region', '');
        if (in_array($region, ['domestic', 'foreign'], true)) {
            return $region;
        }

        return in_array($destination, PopularTourDiscovery::DOMESTIC_DESTINATIONS, true)
            ? 'domestic'
            : 'foreign';
    }
}
