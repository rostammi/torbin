<?php

use App\Models\Agency;
use App\Models\PriceSource;
use App\Models\Tour;
use App\Services\Alerts\PriceAlertNotifier;
use App\Services\PriceCrawler;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Schema;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('prices:crawl {tour?}', function (PriceCrawler $crawler, PriceAlertNotifier $alerts) {
    $query = PriceSource::query()->where('is_active', true)->where('extraction_type', '!=', 'manual');
    if ($tour = $this->argument('tour')) {
        $query->where('tour_id', $tour);
    }

    $sources = $query->get();
    $success = $sources->filter(fn ($source) => $crawler->crawl($source))->count();
    $notified = $sources->pluck('tour_id')->unique()->sum(fn ($tourId) => $alerts->notifyForTour(Tour::findOrFail($tourId)));
    $this->info("Crawled {$sources->count()} sources; {$success} succeeded; {$notified} alerts sent.");
})->purpose('Crawl active tour price sources');

Schedule::command('prices:crawl')->hourly()->withoutOverlapping();

Artisan::command('content:crawl {tour?}', function (PriceCrawler $crawler) {
    $query = PriceSource::query()->where('is_active', true);
    if ($tour = $this->argument('tour')) {
        $query->where('tour_id', $tour);
    }

    $sources = $query->get();
    $success = $sources->filter(fn ($source) => $crawler->crawlContent($source, true))->count();
    $this->info("Crawled content for {$sources->count()} sources; {$success} succeeded.");
})->purpose('Extract and compile useful tour topics from provider pages');

Schedule::command('content:crawl')->dailyAt('02:30')->withoutOverlapping();

Artisan::command('db:import-sqlite', function () {
    $targetDriver = DB::connection()->getDriverName();
    if (! in_array($targetDriver, ['mysql', 'mariadb'], true)) {
        $this->error('اتصال پیش‌فرض باید MySQL یا MariaDB باشد.');

        return 1;
    }

    $sourcePath = config('database.connections.legacy_sqlite.database');
    if (! is_string($sourcePath) || ! is_file($sourcePath)) {
        $this->error("فایل SQLite پیدا نشد: {$sourcePath}");

        return 1;
    }

    $tables = [
        'tours', 'agencies', 'users', 'price_sources', 'price_histories', 'price_alerts',
        'tour_page_views', 'outbound_clicks', 'agency_credit_transactions',
        'search_misses',
    ];
    foreach ($tables as $table) {
        if (! Schema::connection('legacy_sqlite')->hasTable($table) || ! Schema::hasTable($table)) {
            $this->warn("جدول {$table} در مبدا یا مقصد وجود ندارد؛ رد شد.");

            continue;
        }

        $targetColumns = Schema::getColumnListing($table);
        $sourceColumns = Schema::connection('legacy_sqlite')->getColumnListing($table);
        $columns = array_values(array_intersect($sourceColumns, $targetColumns));
        $updatedColumns = array_values(array_diff($columns, ['id']));
        $imported = 0;

        DB::connection('legacy_sqlite')->table($table)->orderBy('id')->chunkById(500, function ($rows) use ($table, $columns, $updatedColumns, &$imported) {
            $payload = $rows->map(fn ($row) => array_intersect_key((array) $row, array_flip($columns)))->all();
            DB::table($table)->upsert($payload, ['id'], $updatedColumns);
            $imported += count($payload);
        });

        $this->info("{$table}: {$imported} رکورد منتقل شد.");
    }

    PriceSource::query()->whereNull('agency_id')->each(function (PriceSource $source) {
        $source->agency_id = Agency::firstOrCreate(['name' => $source->provider_name])->id;
        $source->save();
    });

    $this->newLine();
    $this->info('انتقال داده‌های SQLite به MySQL کامل شد.');

    return 0;
})->purpose('Import users, tours, sources, and price history from the legacy SQLite database');
