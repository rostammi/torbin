<?php

namespace App\Providers;

use App\Models\Agency;
use App\Models\PriceSource;
use App\Models\StaticPage;
use App\Models\Tour;
use App\Observers\SitemapContentObserver;
use App\Services\Seo\SitemapRefreshScheduler;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SitemapRefreshScheduler::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $configuredUrl = (string) config('app.url');
        $scheme = (string) parse_url($configuredUrl, PHP_URL_SCHEME);
        $host = strtolower((string) parse_url($configuredUrl, PHP_URL_HOST));
        $port = parse_url($configuredUrl, PHP_URL_PORT);
        if ($scheme !== '' && $host !== '') {
            $host = str_starts_with($host, 'www.') ? substr($host, 4) : $host;
            URL::forceRootUrl($scheme.'://'.$host.($port ? ':'.$port : ''));
        }

        Paginator::defaultView('pagination.admin');
        Paginator::defaultSimpleView('pagination.simple-admin');

        $clearHomeSections = static function (): void {
            Cache::forget('public-home:category-sections:v1');
        };
        Tour::saved($clearHomeSections);
        Tour::deleted($clearHomeSections);
        PriceSource::saved($clearHomeSections);
        PriceSource::deleted($clearHomeSections);

        Tour::observe(SitemapContentObserver::class);
        StaticPage::observe(SitemapContentObserver::class);
        Agency::observe(SitemapContentObserver::class);
        PriceSource::observe(SitemapContentObserver::class);
    }
}
