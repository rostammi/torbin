<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tour extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 'slug', 'excerpt', 'description', 'auto_content', 'auto_content_updated_at',
        'cover_image', 'gallery', 'image_sources', 'video_url', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'gallery' => 'array',
            'image_sources' => 'array',
            'auto_content' => 'array',
            'auto_content_updated_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function resolveRouteBinding($value, $field = null)
    {
        $tour = $this->where($field ?: $this->getRouteKeyName(), $value)->first();
        if ($tour) {
            return $tour;
        }

        $redirect = TourSlugRedirect::query()
            ->where('old_slug', $value)
            ->with('tour')
            ->first();

        return $redirect?->tour?->setAttribute('resolved_from_slug', $value);
    }

    public function priceSources(): HasMany
    {
        return $this->hasMany(PriceSource::class);
    }

    public function slugRedirects(): HasMany
    {
        return $this->hasMany(TourSlugRedirect::class);
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

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeWithPublicPricing(Builder $query): Builder
    {
        return $query
            ->withMin(['priceSources as minimum_price' => fn ($source) => $source->where('is_active', true)->funded()->where('latest_price', '>', 0)], 'latest_price')
            ->withCount(['priceSources as compared_sources_count' => fn ($source) => $source->where('is_active', true)->funded()->where('latest_price', '>', 0)]);
    }
}
