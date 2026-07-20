<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceAlert extends Model
{
    protected $fillable = [
        'tour_id', 'phone', 'phone_hash', 'unsubscribe_token', 'unsubscribe_token_hash',
        'target_price', 'currency', 'is_active', 'last_notified_at',
        'last_notified_price', 'last_error',
    ];

    protected function casts(): array
    {
        return [
            'phone' => 'encrypted',
            'unsubscribe_token' => 'encrypted',
            'target_price' => 'integer',
            'last_notified_price' => 'integer',
            'is_active' => 'boolean',
            'last_notified_at' => 'datetime',
        ];
    }

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }
}
