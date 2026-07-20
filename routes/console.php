<?php

use App\Models\PriceSource;
use App\Services\PriceCrawler;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Schema;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('prices:crawl {tour?}', function (PriceCrawler $crawler) {
    $query = PriceSource::query()->where('is_active', true)->where('extraction_type', '!=', 'manual');
    if ($tour = $this->argument('tour')) {
        $query->where('tour_id', $tour);
    }

    $sources = $query->get();
    $success = $sources->filter(fn ($source) => $crawler->crawl($source))->count();
    $this->info("Crawled {$sources->count()} sources; {$success} succeeded.");
})->purpose('Crawl active tour price sources');

Schedule::command('prices:crawl')->hourly()->withoutOverlapping();

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

    $tables = ['users', 'tours', 'price_sources', 'price_histories'];
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

    $this->newLine();
    $this->info('انتقال داده‌های SQLite به MySQL کامل شد.');

    return 0;
})->purpose('Import users, tours, sources, and price history from the legacy SQLite database');
