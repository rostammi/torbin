<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PriceSource extends Model
{
    use HasFactory;

    protected $fillable = [
        'tour_id', 'provider_name', 'source_url', 'buy_url', 'extraction_type', 'selector',
        'price_multiplier', 'latest_price', 'currency', 'is_active', 'last_checked_at',
        'last_status', 'last_error', 'latest_rating', 'latest_rating_count', 'rating_type',
        'latest_details',
    ];

    protected function casts(): array
    {
        return [
            'price_multiplier' => 'decimal:2',
            'latest_price' => 'integer',
            'latest_rating' => 'float',
            'latest_rating_count' => 'integer',
            'latest_details' => 'array',
            'is_active' => 'boolean',
            'last_checked_at' => 'datetime',
        ];
    }

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
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
