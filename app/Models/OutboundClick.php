<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutboundClick extends Model
{
    protected $fillable = [
        'agency_id', 'price_source_id', 'tour_id', 'charged_amount', 'currency',
        'status', 'ip_hash', 'user_agent_hash', 'destination_url', 'clicked_at',
    ];

    protected function casts(): array
    {
        return ['charged_amount' => 'integer', 'clicked_at' => 'datetime'];
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(PriceSource::class, 'price_source_id');
    }

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }
}
