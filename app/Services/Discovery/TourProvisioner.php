<?php

namespace App\Services\Discovery;

use App\Models\Tour;
use App\Models\TourSuggestion;
use App\Services\Images\TourImageCrawler;
use App\Services\PriceCrawler;
use App\Services\TourPriceUpdater;
use App\Services\TourSlugGenerator;
use Illuminate\Support\Facades\DB;
use Throwable;

class TourProvisioner
{
    public function __construct(
        private readonly ProviderCatalog $providers,
        private readonly PriceCrawler $crawler,
        private readonly TourPriceUpdater $priceUpdater,
        private readonly TourSlugGenerator $slugGenerator,
        private readonly TourImageCrawler $imageCrawler,
    ) {}

    public function provision(TourSuggestion $suggestion): array
    {
        $destination = $suggestion->destination ?: preg_replace('/^تور\s+/u', '', $suggestion->keyword);
        [$tour, $created] = DB::transaction(function () use ($suggestion, $destination) {
            $suggestion = TourSuggestion::query()->lockForUpdate()->findOrFail($suggestion->id);
            $slug = $this->slugGenerator->forSuggestion($suggestion);

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

        $priceResult = $this->priceUpdater->update($tour);
        $successful = $priceResult['crawl_successful'];
        $contentSuccessful = 0;
        foreach ($tour->priceSources()->where('is_active', true)->get() as $source) {
            if ($this->crawler->crawlContent($source->fresh(), true)) {
                $contentSuccessful++;
            }
        }

        try {
            $imageResult = $this->imageCrawler->crawl($tour);
        } catch (Throwable $exception) {
            // A comparison page without a cover image is incomplete and must
            // not remain publicly available when image provisioning fails.
            $tour->update(['is_active' => false]);

            throw $exception;
        }

        $tour->refresh();
        $sources = $tour->priceSources()->count();
        $tour->update(['is_active' => $priceResult['target_met'] && filled($tour->cover_image)]);
        $suggestion->refresh();
        $suggestion->update([
            'status' => 'created',
            'metadata' => array_merge($suggestion->metadata ?? [], [
                'provision_action' => $created ? 'created' : 'updated',
                'crawl_successful' => $successful,
                'content_successful' => $contentSuccessful,
                'prices_found' => $priceResult['prices_found'],
                'fallback_checked' => $priceResult['fallback_checked'],
                'failed_sources_removed' => $priceResult['failed_sources_removed'],
                'images_downloaded' => $imageResult['downloaded'],
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
            'prices_found' => $priceResult['prices_found'],
            'fallback_checked' => $priceResult['fallback_checked'],
            'failed_sources_removed' => $priceResult['failed_sources_removed'],
            'images_downloaded' => $imageResult['downloaded'],
        ];
    }
}
