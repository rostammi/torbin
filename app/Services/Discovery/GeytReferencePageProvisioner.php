<?php

namespace App\Services\Discovery;

use App\Models\Tour;
use App\Models\TourSuggestion;
use App\Services\TourSlugGenerator;
use Illuminate\Support\Facades\DB;

class GeytReferencePageProvisioner
{
    public function __construct(
        private readonly ProviderCatalog $providers,
        private readonly TourSlugGenerator $slugGenerator,
    ) {}

    public function provision(?string $category = null): array
    {
        $summary = ['total' => 0, 'created' => 0, 'linked' => 0, 'sources' => 0];
        $suggestions = TourSuggestion::query()
            ->whereNotNull('metadata->geyt_references')
            ->when($category, fn ($query) => $query->where('category', $category))
            ->orderBy('id')
            ->get();

        foreach ($suggestions as $suggestion) {
            $result = DB::transaction(function () use ($suggestion) {
                $suggestion = TourSuggestion::query()->lockForUpdate()->findOrFail($suggestion->id);
                $tour = $suggestion->tour;
                $created = false;

                if (! $tour) {
                    $slug = $this->slugGenerator->forSuggestion($suggestion);
                    $tour = Tour::query()->where('slug', $slug)->first();
                    if (! $tour) {
                        $tour = Tour::create([
                            'category' => $suggestion->category,
                            'title' => $suggestion->suggested_title,
                            'slug' => $slug,
                            'excerpt' => "مقایسه پیشنهادهای {$suggestion->keyword} و استعلام قیمت از ارائه‌دهنده‌های معتبر.",
                            'description' => "در این صفحه می‌توانید پیشنهادهای {$suggestion->keyword} را بررسی کنید. اگر قیمت آنلاین در دسترس نباشد، امکان استعلام تلفنی نمایش داده می‌شود.",
                            'seo_keywords' => data_get($suggestion->metadata, 'keywords', [$suggestion->keyword]),
                            'is_active' => true,
                        ]);
                        $created = true;
                    }
                }

                $tour->update(['category' => $suggestion->category, 'is_active' => true]);
                $destination = (string) ($suggestion->destination ?: $suggestion->keyword);
                $sources = $this->providers->attach($tour, $destination, 10);
                $suggestion->update([
                    'tour_id' => $tour->id,
                    'status' => 'created',
                ]);

                return ['created' => $created, 'sources' => $sources];
            });

            $summary['total']++;
            $summary[$result['created'] ? 'created' : 'linked']++;
            $summary['sources'] += $result['sources'];
        }

        return $summary;
    }
}
