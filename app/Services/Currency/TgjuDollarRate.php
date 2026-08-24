<?php

namespace App\Services\Currency;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class TgjuDollarRate
{
    private const FRESH_CACHE_KEY = 'exchange-rate:tgju:usd-toman';

    private const STALE_CACHE_KEY = 'exchange-rate:tgju:usd-toman:last-known';

    public function toman(): int
    {
        if ($rate = Cache::get(self::FRESH_CACHE_KEY)) {
            return (int) $rate;
        }

        try {
            $response = Http::timeout((int) config('crawler.dollar.timeout', 12))
                ->retry(2, 400)
                ->withUserAgent(config('crawler.user_agent'))
                ->get(config('crawler.dollar.url'))
                ->throw();
            $rate = $this->extractTomanRate($response->body());

            Cache::put(self::FRESH_CACHE_KEY, $rate, now()->addMinutes((int) config('crawler.dollar.cache_minutes', 10)));
            Cache::put(self::STALE_CACHE_KEY, $rate, now()->addDays((int) config('crawler.dollar.stale_days', 7)));

            return $rate;
        } catch (Throwable $exception) {
            if ($rate = Cache::get(self::STALE_CACHE_KEY)) {
                report($exception);

                return (int) $rate;
            }

            throw new RuntimeException('دریافت نرخ دلار از TGJU ناموفق بود و نرخ ذخیره‌شده‌ای وجود ندارد.', previous: $exception);
        }
    }

    public function extractTomanRate(string $html): int
    {
        $patterns = [
            '~data-col=["\']info\.last_trade\.PDrCotVal["\'][^>]*>\s*([^<]+)~iu',
            '~نرخ\s*فعلی\s*:?[\s:]*([0-9۰-۹٠-٩][0-9۰-۹٠-٩,،٬\s]{3,})~u',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $match) === 1) {
                $rial = $this->integer($match[1]);
                if ($rial >= 10_000) {
                    return (int) round($rial / 10);
                }
            }
        }

        throw new RuntimeException('نرخ فعلی دلار در پاسخ TGJU پیدا نشد.');
    }

    private function integer(string $value): int
    {
        $value = str_replace(
            ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'],
            ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
            $value,
        );

        return (int) (preg_replace('/\D/', '', $value) ?: 0);
    }
}
