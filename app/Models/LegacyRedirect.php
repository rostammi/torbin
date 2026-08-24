<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegacyRedirect extends Model
{
    protected $fillable = [
        'old_path', 'source_url', 'source_title', 'tour_id', 'match_type', 'hits', 'last_hit_at', 'notes',
    ];

    protected function casts(): array
    {
        return ['last_hit_at' => 'datetime'];
    }

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }
}
