<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceHistory extends Model
{
    protected $fillable = [
        'price_source_id', 'price', 'rating', 'rating_count', 'rating_type', 'is_available',
        'buy_url', 'offer_title', 'departure_at', 'return_at', 'details', 'observed_at',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'rating' => 'float',
            'rating_count' => 'integer',
            'is_available' => 'boolean',
            'details' => 'array',
            'observed_at' => 'datetime',
        ];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(PriceSource::class, 'price_source_id');
    }
}
