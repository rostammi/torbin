<?php

namespace App\Services;

use App\Models\Tour;
use App\Models\TourSlugRedirect;
use App\Models\TourSuggestion;
use App\Services\Discovery\PopularTourDiscovery;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TourDestinationConsolidator
{
    public function __construct(private readonly TourSlugGenerator $slugs) {}

    public function consolidateAll(): array
    {
        $summary = ['destinations' => 0, 'merged_tours' => 0, 'removed_suggestions' => 0];
        $destinations = TourSuggestion::query()
            ->when(Schema::hasColumn('tour_suggestions', 'category'), fn ($query) => $query->where('category', 'tour'))
            ->whereNotNull('destination')
            ->select('destination')
            ->distinct()
            ->orderBy('destination')
            ->pluck('destination');

        foreach ($destinations as $destination) {
            $result = $this->consolidate((string) $destination);
            $summary['destinations']++;
            $summary['merged_tours'] += $result['merged_tours'];
            $summary['removed_suggestions'] += $result['removed_suggestions'];
        }

        return $summary;
    }

    public function consolidate(string $destination): array
    {
        return DB::transaction(function () use ($destination) {
            $suggestions = TourSuggestion::query()
                ->where('destination', $destination)
                ->when(Schema::hasColumn('tour_suggestions', 'category'), fn ($query) => $query->where('category', 'tour'))
                ->with('tour')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            if ($suggestions->isEmpty()) {
                return ['merged_tours' => 0, 'removed_suggestions' => 0];
            }

            $keywords = collect(PopularTourDiscovery::VARIANTS)
                ->map(fn (string $template) => sprintf($template, $destination))
                ->concat($suggestions->pluck('keyword'))
                ->filter()
                ->unique()
                ->values();
            $orphanTours = Tour::query()
                ->whereDoesntHave('suggestions')
                ->when(Schema::hasColumn('tours', 'category'), fn ($query) => $query->where('category', 'tour'))
                ->where(function ($query) use ($keywords) {
                    $query->whereIn('title', $keywords)
                        ->orWhere(function ($titles) use ($keywords) {
                            foreach ($keywords as $keyword) {
                                $titles->orWhere('title', 'like', $keyword.' |%');
                            }
                        });
                })
                ->lockForUpdate()
                ->get();
            $canonicalSuggestion = $suggestions->sortBy(fn (TourSuggestion $suggestion) => [
                data_get($suggestion->metadata, 'variant') === 'main' || $suggestion->keyword === "تور {$destination}" ? 0 : 1,
                $suggestion->id,
            ])->first();
            $candidateTours = $suggestions->pluck('tour')
                ->concat($orphanTours)
                ->filter()
                ->unique()
                ->values();
            $canonicalTour = $canonicalSuggestion->tour ?: $candidateTours->first();
            $keywords = $keywords->all();
            $metadata = array_merge($canonicalSuggestion->metadata ?? [], [
                'variant' => 'main',
                'keywords' => $keywords,
                'consolidated' => true,
            ]);

            $canonicalSuggestion->update([
                'keyword' => "تور {$destination}",
                'suggested_title' => "تور {$destination} | مقایسه قیمت و خرید از معتبرترین آژانس‌ها",
                'metadata' => $metadata,
                'tour_id' => $canonicalTour?->id,
            ]);

            $mergedTours = 0;
            if ($canonicalTour) {
                foreach ($candidateTours->unique('id') as $duplicate) {
                    if ($duplicate->is($canonicalTour)) {
                        continue;
                    }

                    $this->mergeTour($canonicalTour, $duplicate);
                    $mergedTours++;
                }

                $canonicalTour->refresh();
                $newSlug = $this->slugs->unique($this->slugs->fromTitle("تور {$destination}"), $canonicalTour);
                if ($newSlug !== $canonicalTour->slug) {
                    TourSlugRedirect::query()->updateOrCreate(
                        ['old_slug' => $canonicalTour->slug],
                        ['tour_id' => $canonicalTour->id],
                    );
                }
                $canonicalTour->update([
                    'title' => "تور {$destination} | مقایسه قیمت و خرید از معتبرترین آژانس‌ها",
                    'excerpt' => "مقایسه قیمت تور {$destination}، تور ارزان، لحظه آخری، اقساطی، هوایی و از تهران در یک صفحه.",
                    'seo_keywords' => $keywords,
                    'slug' => $newSlug,
                ]);
                $canonicalSuggestion->update(['tour_id' => $canonicalTour->id]);
            }

            $duplicateSuggestionIds = $suggestions->pluck('id')->reject(
                fn (int $id) => $id === $canonicalSuggestion->id
            );
            TourSuggestion::query()->whereIn('id', $duplicateSuggestionIds)->delete();

            return [
                'merged_tours' => $mergedTours,
                'removed_suggestions' => $duplicateSuggestionIds->count(),
            ];
        });
    }

    private function mergeTour(Tour $canonical, Tour $duplicate): void
    {
        $paths = collect([$canonical->cover_image])
            ->concat($canonical->gallery ?? [])
            ->concat([$duplicate->cover_image])
            ->concat($duplicate->gallery ?? [])
            ->filter()
            ->unique()
            ->values();
        $canonical->update([
            'cover_image' => $paths->first(),
            'gallery' => $paths->skip(1)->values()->all(),
            'image_sources' => collect($canonical->image_sources ?? [])
                ->concat($duplicate->image_sources ?? [])
                ->unique('path')
                ->values()
                ->all(),
            'auto_content' => $canonical->auto_content ?: $duplicate->auto_content,
            'auto_content_updated_at' => $canonical->auto_content_updated_at ?: $duplicate->auto_content_updated_at,
            'video_url' => $canonical->video_url ?: $duplicate->video_url,
            'is_active' => $canonical->is_active || $duplicate->is_active,
        ]);

        foreach ($duplicate->priceSources()->get() as $source) {
            $existing = $canonical->priceSources()->where('provider_name', $source->provider_name)->first();
            if (! $existing) {
                $source->update(['tour_id' => $canonical->id]);

                continue;
            }

            DB::table('price_histories')->where('price_source_id', $source->id)
                ->update(['price_source_id' => $existing->id]);
            DB::table('outbound_clicks')->where('price_source_id', $source->id)
                ->update(['price_source_id' => $existing->id, 'tour_id' => $canonical->id]);
            if ($this->sourceScore($source) > $this->sourceScore($existing)) {
                $attributes = Arr::only($source->getAttributes(), $source->getFillable());
                unset($attributes['tour_id']);
                $existing->update($attributes);
            }
            $source->delete();
        }

        foreach ($duplicate->priceAlerts()->get() as $alert) {
            $exists = $canonical->priceAlerts()
                ->where('phone_hash', $alert->phone_hash)
                ->where('origin', $alert->origin)
                ->exists();
            $exists ? $alert->delete() : $alert->update(['tour_id' => $canonical->id]);
        }

        DB::table('tour_page_views')->where('tour_id', $duplicate->id)->update(['tour_id' => $canonical->id]);
        DB::table('outbound_clicks')->where('tour_id', $duplicate->id)->update(['tour_id' => $canonical->id]);
        TourSuggestion::query()->where('tour_id', $duplicate->id)->update(['tour_id' => $canonical->id]);
        TourSlugRedirect::query()->where('tour_id', $duplicate->id)->update(['tour_id' => $canonical->id]);
        TourSlugRedirect::query()->updateOrCreate(
            ['old_slug' => $duplicate->slug],
            ['tour_id' => $canonical->id],
        );
        $duplicate->delete();
    }

    private function sourceScore($source): int
    {
        return ((int) ($source->last_status === 'success') * 1_000_000_000_000)
            + (int) ($source->latest_price > 0) * 100_000_000_000
            + (int) optional($source->last_checked_at)->timestamp;
    }
}
