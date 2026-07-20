<?php

namespace App\Services\Search;

use App\Models\Tour;
use Illuminate\Database\Eloquent\Builder;

class TourSearch
{
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

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim($value));
    }
}
