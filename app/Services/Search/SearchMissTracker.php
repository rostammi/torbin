<?php

namespace App\Services\Search;

use App\Models\SearchMiss;
use Illuminate\Http\Request;
use Throwable;

class SearchMissTracker
{
    public function track(string $query, Request $request): void
    {
        try {
            SearchMiss::create([
                'query' => trim($query),
                'normalized_query' => $this->normalize($query),
                'ip_hash' => $request->ip() ? hash_hmac('sha256', $request->ip(), config('app.key')) : null,
                'searched_at' => now(),
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function normalize(string $value): string
    {
        $value = str_replace(['ي', 'ك', "\u{200C}"], ['ی', 'ک', ' '], mb_strtolower(trim($value)));

        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }
}
