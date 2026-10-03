<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;

// This file is intended for PHP-only cron interfaces (such as some cPanel hosts).
// Keep the project's document root pointed at /public; also reject HTTP execution.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

set_time_limit(0);

define('LARAVEL_START', microtime(true));

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$exitCode = Artisan::call('schedule:run', [
    '--no-interaction' => true,
]);

echo Artisan::output();

if ($exitCode !== 0) {
    exit($exitCode);
}

// Let every cron tick run the scheduler, but allow only one queue worker at a time.
$lockPath = storage_path('framework/cache/cpanel-queue-worker.lock');
$lock = fopen($lockPath, 'c');

if ($lock === false) {
    fwrite(STDERR, "Unable to open the queue-worker lock file: {$lockPath}\n");
    exit(1);
}

if (! flock($lock, LOCK_EX | LOCK_NB)) {
    echo "A queue worker is already running; scheduler completed.\n";
    fclose($lock);
    exit(0);
}

try {
    // sync:work also queues today's automatic price refresh when it is due.
    $exitCode = Artisan::call('sync:work', [
        '--no-interaction' => true,
    ]);

    echo Artisan::output();
} finally {
    flock($lock, LOCK_UN);
    fclose($lock);
}

exit($exitCode);
