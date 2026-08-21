<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tour extends Model
{
    use HasFactory;

    protected $hidden = ['has_contact_source'];

    protected $fillable = [
        'category', 'title', 'slug', 'excerpt', 'description', 'auto_content', 'auto_content_updated_at',
        'seo_keywords', 'cover_image', 'gallery', 'image_sources', 'video_url', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'gallery' => 'array',
            'image_sources' => 'array',
            'auto_content' => 'array',
            'seo_keywords' => 'array',
            'auto_content_updated_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function categoryConfig(?string $key = null): mixed
    {
        $config = config("comparison.categories.{$this->category}", config('comparison.categories.tour'));

        return $key ? data_get($config, $key) : $config;
    }

    public function categoryLabel(): string
    {
        return (string) $this->categoryConfig('label');
    }

    public function categoryPlural(): string
    {
        return (string) $this->categoryConfig('plural');
    }

    public function publicRouteName(): string
    {
        return (string) $this->categoryConfig('route').'.show';
    }

    public function publicUrl(): string
    {
        return rtrim(route($this->publicRouteName(), $this), '/').'/';
    }

    public function priceSources(): HasMany
    {
        return $this->hasMany(PriceSource::class);
    }

    public function suggestions(): HasMany
    {
        return $this->hasMany(TourSuggestion::class);
    }

    public function priceAlerts(): HasMany
    {
        return $this->hasMany(PriceAlert::class);
    }

    public function pageViews(): HasMany
    {
        return $this->hasMany(TourPageView::class);
    }

    public function outboundClicks(): HasMany
    {
        return $this->hasMany(OutboundClick::class);
    }

    public function activePrices(): HasMany
    {
        return $this->priceSources()
            ->where('is_active', true)
            ->funded()
            ->where('latest_price', '>', 0)
            ->orderBy('latest_price');
    }

    public function publicComparisonSources(): Collection
    {
        $priced = $this->priceSources()
            ->where('is_active', true)
            ->funded()
            ->where('latest_price', '>', 0)
            ->orderBy('latest_price')
            ->with('agency')
            ->get();

        $contactSource = $this->priceSources()
            ->where('is_active', true)
            ->where(fn (Builder $query) => $query
                ->whereNull('latest_price')
                ->orWhere('latest_price', '<=', 0))
            ->orderBy(
                Agency::query()
                    ->select('contact_priority')
                    ->whereColumn('agencies.id', 'price_sources.agency_id')
                    ->limit(1),
            )
            ->orderBy('id')
            ->with('agency')
            ->first();

        return $contactSource ? $priced->push($contactSource) : $priced;
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeWithPublicPricing(Builder $query): Builder
    {
        return $query
            ->withMin(['priceSources as minimum_price' => fn ($source) => $source->where('is_active', true)->funded()->where('latest_price', '>', 0)], 'latest_price')
            ->withCount(['priceSources as compared_sources_count' => fn ($source) => $source->where('is_active', true)->funded()->where('latest_price', '>', 0)])
            ->withExists(['priceSources as has_contact_source' => fn ($source) => $source
                ->where('is_active', true)
                ->where(fn (Builder $missingPrice) => $missingPrice
                    ->whereNull('latest_price')
                    ->orWhere('latest_price', '<=', 0))]);
    }

    public function getComparedSourcesCountAttribute(mixed $value): int
    {
        return (int) $value + (int) ($this->attributes['has_contact_source'] ?? false);
    }
}
