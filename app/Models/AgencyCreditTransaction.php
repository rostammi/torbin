<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgencyCreditTransaction extends Model
{
    protected $fillable = [
        'agency_id', 'outbound_click_id', 'user_id', 'amount', 'balance_after', 'type', 'note',
    ];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'balance_after' => 'integer'];
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }
}
