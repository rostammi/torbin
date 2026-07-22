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
        $destination = $suggestion->destination ?: preg_replace('/^تور\s+/u', '', $suggestion->keyword);
        [$tour, $created] = DB::transaction(function () use ($suggestion, $destination) {
            $suggestion = TourSuggestion::query()->lockForUpdate()->findOrFail($suggestion->id);
            $slug = Str::slug($suggestion->keyword) ?: 'tour-'.substr(sha1($suggestion->keyword), 0, 12);

            $tour = $suggestion->tour ?: Tour::where('slug', $slug)->first();
            $created = $tour === null;

            if ($created) {
                $tour = Tour::create([
                    'title' => $suggestion->suggested_title,
                    'slug' => $slug,
                    'excerpt' => "مقایسه قیمت {$suggestion->keyword} بین معتبرترین سایت‌های فروش تور، همراه با آخرین قیمت و امتیاز.",
                    'description' => "برای انتخاب {$suggestion->keyword}، قیمت و جزئیات پیشنهادهای آژانس‌های مختلف را در این صفحه مقایسه کنید. اطلاعات قیمت و محتوای سفر به‌صورت دوره‌ای از سایت‌های ارائه‌دهنده به‌روزرسانی می‌شود.",
                    'is_active' => false,
                ]);
            }

            $this->providers->attach($tour, (string) $destination);
            $suggestion->update(['status' => 'processing', 'tour_id' => $tour->id]);

            return [$tour, $created];
        });

        $successful = 0;
        $contentSuccessful = 0;
        foreach ($tour->priceSources()->where('is_active', true)->get() as $source) {
            if ($this->crawler->crawl($source)) {
                $successful++;
            }

            if ($this->crawler->crawlContent($source->fresh(), true)) {
                $contentSuccessful++;
            }
        }

        $sources = $tour->priceSources()->count();
        $tour->update(['is_active' => $sources >= 4]);
        $suggestion->refresh();
        $suggestion->update([
            'status' => 'created',
            'metadata' => array_merge($suggestion->metadata ?? [], [
                'provision_action' => $created ? 'created' : 'updated',
                'crawl_successful' => $successful,
                'content_successful' => $contentSuccessful,
                'sources_added' => $sources,
                'last_provisioned_at' => now()->toIso8601String(),
            ]),
        ]);

        return [
            'tour' => $tour->fresh(),
            'created' => $created,
            'sources' => $sources,
            'crawled' => $successful,
            'content_crawled' => $contentSuccessful,
        ];
    }
}
