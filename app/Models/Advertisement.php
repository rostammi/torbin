<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Advertisement extends Model
{
    public const PLACEMENTS = [
        'home_slider' => 'اسلایدر صفحه اصلی',
        'home_inline' => 'بنر بین کارت‌های صفحه اصلی',
        'search_top' => 'بنر بالای صفحه جست‌وجو',
        'search_result' => 'کادر داخل نتایج جست‌وجو',
        'tour_trend_top' => 'بنر بالای ترند قیمت',
        'tour_offers_bottom' => 'بنر بعد از پیشنهادهای قیمت',
    ];

    protected $fillable = [
        'agency_id', 'name', 'advertiser_name', 'placement', 'title', 'subtitle', 'image_path',
        'destination_url', 'cta_text', 'priority', 'contract_amount', 'contract_currency',
        'starts_at', 'ends_at', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'priority' => 'integer',
            'contract_amount' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
            'impressions' => 'integer',
            'clicks' => 'integer',
        ];
    }

    public function scopeCurrentlyVisible(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where(fn (Builder $query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }

    public function getPlacementLabelAttribute(): string
    {
        return self::PLACEMENTS[$this->placement] ?? $this->placement;
    }

    public function getClickThroughRateAttribute(): float
    {
        return $this->impressions > 0 ? ($this->clicks / $this->impressions) * 100 : 0;
    }
}
