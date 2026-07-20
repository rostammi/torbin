<?php

namespace App\Services\Analytics;

use App\Models\Tour;
use Illuminate\Http\Request;
use Throwable;

class TourViewTracker
{
    public function track(Tour $tour, Request $request): void
    {
        try {
            $tour->pageViews()->create([
                'ip_hash' => $request->ip() ? hash_hmac('sha256', $request->ip(), config('app.key')) : null,
                'user_agent_hash' => $request->userAgent() ? hash('sha256', $request->userAgent()) : null,
                'viewed_at' => now(),
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
