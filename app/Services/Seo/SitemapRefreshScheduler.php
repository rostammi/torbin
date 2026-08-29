<?php

namespace App\Services\Seo;

use Illuminate\Support\Facades\DB;
use Throwable;

class SitemapRefreshScheduler
{
    private bool $scheduled = false;

    public function __construct(private readonly SitemapGenerator $generator) {}

    public function schedule(): void
    {
        if (! config('seo.sitemap.auto_refresh', true) || $this->scheduled) {
            return;
        }

        if (DB::transactionLevel() === 0) {
            $this->generateSafely();

            return;
        }

        $this->scheduled = true;
        DB::afterCommit(function (): void {
            $this->scheduled = false;
            $this->generateSafely();
        });
        DB::afterRollBack(function (): void {
            $this->scheduled = false;
        });
    }

    private function generateSafely(): void
    {
        try {
            $this->generator->write();
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
