<?php

namespace App\Observers;

use App\Models\Agency;
use App\Models\PriceSource;
use App\Services\Seo\SitemapRefreshScheduler;
use Illuminate\Database\Eloquent\Model;

class SitemapContentObserver
{
    public function __construct(private readonly SitemapRefreshScheduler $sitemap) {}

    public function created(Model $model): void
    {
        $this->sitemap->schedule();
    }

    public function updated(Model $model): void
    {
        if ($model instanceof PriceSource && ! $model->wasChanged([
            'agency_id', 'provider_name', 'is_active', 'latest_price', 'currency',
            'latest_rating', 'latest_rating_count',
        ])) {
            return;
        }

        if ($model instanceof Agency && ! $model->wasChanged([
            'name', 'balance', 'cost_per_click', 'is_featured', 'is_contact_only', 'display_priority', 'is_pinned',
        ])) {
            return;
        }

        $this->sitemap->schedule();
    }

    public function deleted(Model $model): void
    {
        $this->sitemap->schedule();
    }
}
