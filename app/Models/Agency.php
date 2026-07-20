<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Agency extends Model
{
    protected $fillable = ['name', 'balance', 'cost_per_click', 'currency'];

    protected function casts(): array
    {
        return ['balance' => 'integer', 'cost_per_click' => 'integer'];
    }

    public function priceSources(): HasMany
    {
        return $this->hasMany(PriceSource::class);
    }

    public function clicks(): HasMany
    {
        return $this->hasMany(OutboundClick::class);
    }

    public function creditTransactions(): HasMany
    {
        return $this->hasMany(AgencyCreditTransaction::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function canAffordClick(): bool
    {
        return $this->cost_per_click === 0 || $this->balance >= $this->cost_per_click;
    }
}
