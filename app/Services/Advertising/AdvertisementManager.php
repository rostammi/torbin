<?php

namespace App\Services\Advertising;

use App\Models\Advertisement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AdvertisementManager
{
    public function forPlacement(string $placement, int $limit = 1): Collection
    {
        $advertisements = Advertisement::query()
            ->currentlyVisible()
            ->where('placement', $placement)
            ->orderByDesc('priority')
            ->orderBy('id')
            ->limit(max(1, $limit))
            ->get();

        if ($advertisements->isNotEmpty()) {
            DB::table('advertisements')
                ->whereIn('id', $advertisements->modelKeys())
                ->update(['impressions' => DB::raw('impressions + 1')]);
            $advertisements->each(fn (Advertisement $advertisement) => $advertisement->impressions++);
        }

        return $advertisements;
    }
}
