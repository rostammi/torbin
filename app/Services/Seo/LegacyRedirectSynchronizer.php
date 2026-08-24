<?php

namespace App\Services\Seo;

use App\Models\LegacyRedirect;
use App\Models\TourSuggestion;

class LegacyRedirectSynchronizer
{
    public function __construct(private readonly LegacyUrlNormalizer $urls) {}

    public function sync(): array
    {
        $summary = ['total' => 0, 'matched' => 0, 'unmatched' => 0];

        TourSuggestion::query()
            ->whereNotNull('metadata->geyt_references')
            ->with('tour')
            ->orderBy('id')
            ->chunkById(200, function ($suggestions) use (&$summary) {
                foreach ($suggestions as $suggestion) {
                    foreach (data_get($suggestion->metadata, 'geyt_references', []) as $reference) {
                        $source = $this->urls->source((string) data_get($reference, 'page_url'));
                        if (! $source || ! $this->urls->isDetailPath($source['path'])) {
                            continue;
                        }

                        $redirect = LegacyRedirect::firstOrNew(['old_path' => $source['path']]);
                        $redirect->source_url = $source['url'];
                        $redirect->source_title = data_get($reference, 'label');
                        if ($redirect->match_type !== 'manual') {
                            $redirect->tour_id = $suggestion->tour_id;
                            $redirect->match_type = $suggestion->tour_id ? 'automatic' : 'unmatched';
                        }
                        $redirect->save();

                        $summary['total']++;
                        $summary[$redirect->tour_id ? 'matched' : 'unmatched']++;
                    }
                }
            });

        return $summary;
    }
}
