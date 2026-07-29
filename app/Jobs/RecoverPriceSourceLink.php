<?php

namespace App\Jobs;

use App\Models\PriceSource;
use App\Services\Outbound\DestinationLinkValidator;
use App\Services\PriceCrawler;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;

class RecoverPriceSourceLink implements ShouldQueue
{
    use Queueable;

    public int $timeout = 180;

    public int $tries = 3;

    public array $backoff = [60, 300];

    public function __construct(public int $sourceId) {}

    public function handle(PriceCrawler $crawler, DestinationLinkValidator $validator): void
    {
        $source = PriceSource::with('tour')->find($this->sourceId);
        if (! $source || ! in_array($source->last_status, ['broken_link', 'recovery_failed'], true)) {
            return;
        }

        if (! $crawler->crawl($source, false)) {
            throw new RuntimeException('هنوز لینک جایگزین معتبری از این منبع پیدا نشده است.');
        }

        $source->refresh();
        $destination = $source->buy_url ?: $source->source_url;
        if (in_array($destination, $source->rejected_urls ?? [], true)) {
            $source->update([
                'is_active' => false,
                'last_status' => 'recovery_failed',
                'last_error' => 'کراولر همان لینک خراب قبلی را پیدا کرد؛ این URL دوباره فعال نمی‌شود.',
            ]);

            throw new RuntimeException('URL بازیابی‌شده با یکی از لینک‌های خراب قبلی یکسان است.');
        }
        if ($validator->check($destination) !== DestinationLinkValidator::VALID) {
            $source->update([
                'is_active' => false,
                'last_status' => 'recovery_failed',
                'last_error' => 'لینک پیدا شده هنوز پاسخ معتبر نمی‌دهد؛ بازیابی دوباره تلاش می‌شود.',
            ]);

            throw new RuntimeException('لینک جایگزین پیدا شد اما هنوز پاسخ معتبر نمی‌دهد.');
        }

        $source->update([
            'is_active' => true,
            'last_status' => 'success',
            'last_error' => null,
            'last_checked_at' => now(),
        ]);
    }
}
