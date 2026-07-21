<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TourSuggestion extends Model
{
    protected $fillable = [
        'keyword', 'suggested_title', 'destination', 'trend_score', 'source', 'status',
        'metadata', 'discovered_at', 'tour_id',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'discovered_at' => 'datetime',
        ];
    }

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }
}
