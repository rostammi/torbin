<?php

namespace App\Console\Commands;

use App\Models\SyncRun;
use App\Models\Tour;
use App\Services\Images\GeytCoverImageImporter;
use App\Services\Seo\SitemapGenerator;
use Illuminate\Console\Command;
use Throwable;

class ImportGeytCoverImages extends Command
{
    protected $signature = 'images:import-geyt-covers {--limit= : Maximum number of mapped comparisons to process}';

    protected $description = 'Import mapped comparison cover images from geyt.ir without removing current images';

    public function handle(GeytCoverImageImporter $importer, SitemapGenerator $sitemap): int
    {
        $query = Tour::query()->published()->whereHas('legacyRedirects')->orderBy('id');
        if ($limit = (int) $this->option('limit')) {
            $query->limit(max(1, $limit));
        }
        $tours = $query->get();
        $run = SyncRun::create([
            'type' => 'geyt_cover_images',
            'total' => $tours->count(),
            'started_at' => now(),
        ]);
        $details = ['imported' => 0, 'reused' => 0, 'unmapped' => 0, 'failures' => []];
        $progress = $this->output->createProgressBar($tours->count());
        $autoRefresh = config('seo.sitemap.auto_refresh', true);
        config(['seo.sitemap.auto_refresh' => false]);

        try {
            foreach ($tours as $tour) {
                try {
                    $result = $importer->import($tour);
                    $details[$result['status']]++;
                    $run->increment('successful');
                } catch (Throwable $exception) {
                    $run->increment('failed');
                    if (count($details['failures']) < 50) {
                        $details['failures'][] = [
                            'tour_id' => $tour->id,
                            'title' => $tour->title,
                            'error' => mb_substr($exception->getMessage(), 0, 500),
                        ];
                    }
                    report($exception);
                }
                $progress->advance();
            }

            $progress->finish();
            $this->newLine();
            $run->refresh()->update([
                'status' => $run->failed > 0 ? 'partial' : 'success',
                'details' => ['geyt_covers' => $details],
                'error' => $run->failed > 0 ? "دریافت کاور {$run->failed} مقایسه ناموفق بود." : null,
                'finished_at' => now(),
            ]);
        } finally {
            config(['seo.sitemap.auto_refresh' => $autoRefresh]);
            if ($autoRefresh) {
                $sitemap->write();
            }
        }

        $this->info("Imported: {$details['imported']}; reused: {$details['reused']}; failed: {$run->fresh()->failed}");

        return $run->fresh()->failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
