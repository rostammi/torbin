<?php

namespace App\Services\Discovery;

use App\Models\Tour;
use App\Models\TourSuggestion;
use App\Services\PriceCrawler;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TourProvisioner
{
    public function __construct(
        private readonly ProviderCatalog $providers,
        private readonly PriceCrawler $crawler,
    ) {}

    public function provision(TourSuggestion $suggestion): array
    {
        if ($suggestion->tour) {
            return ['tour' => $suggestion->tour, 'sources' => $suggestion->tour->priceSources()->count(), 'crawled' => 0];
        }

        $destination = $suggestion->destination ?: preg_replace('/^تور\s+/u', '', $suggestion->keyword);
        $tour = DB::transaction(function () use ($suggestion, $destination) {
            $slug = Str::slug($suggestion->keyword) ?: 'tour-'.substr(sha1($suggestion->keyword), 0, 12);
            if (Tour::where('slug', $slug)->exists()) {
                $slug .= '-'.Str::lower(Str::random(5));
            }
            $tour = Tour::create([
                'title' => $suggestion->suggested_title,
                'slug' => $slug,
                'excerpt' => "مقایسه قیمت {$suggestion->keyword} بین معتبرترین سایت‌های فروش تور، همراه با آخرین قیمت و امتیاز.",
                'description' => "برای انتخاب {$suggestion->keyword}، قیمت و جزئیات پیشنهادهای آژانس‌های مختلف را در این صفحه مقایسه کنید. اطلاعات قیمت و محتوای سفر به‌صورت دوره‌ای از سایت‌های ارائه‌دهنده به‌روزرسانی می‌شود.",
                'is_active' => false,
            ]);
            $this->providers->attach($tour, (string) $destination);
            $suggestion->update(['status' => 'processing', 'tour_id' => $tour->id]);

            return $tour;
        });

        $successful = 0;
        foreach ($tour->priceSources as $source) {
            if ($this->crawler->crawl($source)) {
                $successful++;
            }
        }

        $sources = $tour->priceSources()->count();
        $tour->update(['is_active' => $sources >= 4]);
        $suggestion->update([
            'status' => 'created',
            'metadata' => array_merge($suggestion->metadata ?? [], ['crawl_successful' => $successful, 'sources_added' => $sources]),
        ]);

        return ['tour' => $tour->fresh(), 'sources' => $sources, 'crawled' => $successful];
    }
}
