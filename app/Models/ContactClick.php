<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactClick extends Model
{
    public const TYPE_AGENCY = 'agency';

    public const TYPE_GENERAL = 'general';

    protected $fillable = [
        'agency_id', 'price_source_id', 'tour_id', 'contact_type', 'phone',
        'ip_hash', 'user_agent_hash', 'clicked_at',
    ];

    protected function casts(): array
    {
        return ['clicked_at' => 'datetime'];
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
