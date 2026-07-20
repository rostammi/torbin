<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PriceSource extends Model
{
    use HasFactory;

    protected $fillable = [
        'tour_id', 'agency_id', 'provider_name', 'source_url', 'buy_url', 'extraction_type', 'selector',
        'price_multiplier', 'latest_price', 'currency', 'is_active', 'last_checked_at',
        'last_status', 'last_error', 'latest_rating', 'latest_rating_count', 'rating_type',
        'latest_details', 'is_featured', 'content_insights', 'content_checked_at', 'content_error',
    ];

    protected function casts(): array
    {
        return [
            'price_multiplier' => 'decimal:2',
            'latest_price' => 'integer',
            'latest_rating' => 'float',
            'latest_rating_count' => 'integer',
            'latest_details' => 'array',
            'content_insights' => 'array',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'last_checked_at' => 'datetime',
            'content_checked_at' => 'datetime',
        ];
    }

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function scopeFunded(Builder $query): Builder
    {
        return $query->whereHas('agency', fn (Builder $agency) => $agency->where('balance', '>', 0));
    }

    protected static function booted(): void
    {
        static::saving(function (PriceSource $source) {
            if ($source->provider_name && ($source->isDirty('provider_name') || ! $source->agency_id)) {
                $source->agency_id = Agency::firstOrCreate(['name' => $source->provider_name])->id;
            }
        });
    }

    public function history(): HasMany
    {
        return $this->hasMany(PriceHistory::class)->latest('observed_at');
    }

    public function recentHistory(): HasMany
    {
        return $this->hasMany(PriceHistory::class)->latest('observed_at')->limit(30);
    }
}
