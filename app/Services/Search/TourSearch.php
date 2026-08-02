<?php

namespace App\Services\Search;

use App\Models\Tour;
use App\Services\Discovery\PopularTourDiscovery;
use Illuminate\Database\Eloquent\Builder;

class TourSearch
{
    public function __construct(private readonly TravelIntentParser $intentParser) {}

    public function intent(string $term): TravelSearchIntent
    {
        return $this->intentParser->parse($term);
    }

    public function query(string $term): Builder
    {
        $like = '%'.$this->escapeLike($term).'%';
        $prefix = $this->escapeLike($term).'%';

        return Tour::query()
            ->published()
            ->where(function (Builder $query) use ($like) {
                $query->where('title', 'like', $like)
                    ->orWhere('excerpt', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    ->orWhere('seo_keywords', 'like', $like)
                    ->orWhereHas('priceSources', fn (Builder $source) => $source
                        ->where('is_active', true)
                        ->funded()
                        ->where(function (Builder $agencyName) use ($like) {
                            $agencyName->where('provider_name', 'like', $like)
                                ->orWhereHas('agency', fn (Builder $agency) => $agency->where('name', 'like', $like));
                        }));
            })
            ->withPublicPricing()
            ->orderByRaw('case when title like ? then 0 when title like ? then 1 else 2 end', [$prefix, $like])
            ->orderBy('title');
    }

    public function recommendations(TravelSearchIntent $intent): Builder
    {
        $query = Tour::query()
            ->published()
            ->where('category', 'tour')
            ->whereHas('priceSources', fn (Builder $source) => $source
                ->where('is_active', true)
                ->funded()
                ->where('latest_price', '>', 0)
                ->when($intent->maximumBudget !== null, fn (Builder $price) => $price
                    ->where('latest_price', '<=', $intent->maximumBudget)));

        if ($intent->destination !== null) {
            $destination = $intent->destination;
            $query->where(fn (Builder $tour) => $tour
                ->where('title', 'like', '%'.$this->escapeLike($destination).'%')
                ->orWhereHas('suggestions', fn (Builder $suggestion) => $suggestion
                    ->where('destination', $destination)));
        } elseif ($intent->region !== null) {
            $destinations = $intent->region === 'domestic'
                ? PopularTourDiscovery::DOMESTIC_DESTINATIONS
                : PopularTourDiscovery::FOREIGN_DESTINATIONS;

            $query->where(function (Builder $tour) use ($destinations, $intent) {
                $tour->whereHas('suggestions', fn (Builder $suggestion) => $suggestion
                    ->where('metadata->region', $intent->region)
                    ->orWhereIn('destination', $destinations));

                foreach ($destinations as $destination) {
                    $tour->orWhere('title', 'like', '%'.$this->escapeLike($destination).'%');
                }
            });
        }

        return $query
            ->withPublicPricing()
            ->orderBy('minimum_price')
            ->orderBy('title');
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim($value));
    }
}
