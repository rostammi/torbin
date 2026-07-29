<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TourSlugRedirect extends Model
{
    protected $fillable = ['tour_id', 'old_slug'];

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }
}
