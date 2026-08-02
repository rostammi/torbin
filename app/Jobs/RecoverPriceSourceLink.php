<?php

namespace App\Jobs;

use App\Models\PriceSource;
use App\Services\Outbound\DestinationLinkValidator;
use App\Services\Outbound\RejectedUrlRegistry;
use App\Services\PriceCrawler;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;

class RecoverPriceSourceLink implements ShouldQueue
{
    use Queueable;

    private const CANDIDATES_PER_ATTEMPT = 3;

    public int $timeout = 180;

    public int $tries = 3;

    public array $backoff = [60, 300];

    public function __construct(public int $sourceId) {}

    public function handle(
        PriceCrawler $crawler,
        DestinationLinkValidator $validator,
        RejectedUrlRegistry $rejectedUrls,
    ): void {
        $source = PriceSource::with('tour')->find($this->sourceId);
        if (! $source || ! in_array($source->last_status, ['broken_link', 'recovery_failed'], true)) {
            return;
        }

        for ($attempt = 0; $attempt < self::CANDIDATES_PER_ATTEMPT; $attempt++) {
            if (! $crawler->crawl($source, false)) {
                break;
            }

            $source->refresh();
            $destination = $source->buy_url ?: $source->source_url;
            $alreadyRejected = $rejectedUrls->contains($source->rejected_urls ?? [], $destination);
            $validation = $alreadyRejected
                ? DestinationLinkValidator::BROKEN
                : $validator->check($destination);

            if ($validation === DestinationLinkValidator::VALID && (int) $source->latest_price > 0) {
                $source->update([
                    'is_active' => true,
                    'last_status' => 'success',
                    'last_error' => null,
                    'last_checked_at' => now(),
                ]);

                return;
            }

            $definitivelyBroken = $alreadyRejected || $validation === DestinationLinkValidator::BROKEN;
            $updates = [
                'is_active' => false,
                'last_status' => 'recovery_failed',
                'last_error' => match (true) {
                    $definitivelyBroken => 'لینک جایگزین خراب بود؛ همان لحظه کنار گذاشته شد و گزینه بعدی بررسی می‌شود.',
                    $validation === DestinationLinkValidator::UNKNOWN => 'اعتبار لینک به‌علت خطای موقت قابل تأیید نبود؛ بدون مسدودسازی دائمی دوباره تلاش می‌شود.',
                    default => 'لینک جایگزین باز می‌شود اما هنوز قیمت معتبری از آن خوانده نشده است.',
                },
            ];
            if ($definitivelyBroken) {
                $updates['rejected_urls'] = $rejectedUrls->add($source->rejected_urls ?? [], $destination);
            }
            $source->update($updates);

            if ($validation === DestinationLinkValidator::UNKNOWN) {
                break;
            }
        }

        throw new RuntimeException('پس از بررسی چند کاندیدا، هنوز لینک جایگزین معتبر و دارای قیمت پیدا نشده است.');
    }
}
