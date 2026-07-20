<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tour extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 'slug', 'excerpt', 'description', 'cover_image', 'gallery', 'video_url', 'is_active',
    ];

    protected function casts(): array
    {
        return ['gallery' => 'array', 'is_active' => 'boolean'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function priceSources(): HasMany
    {
        return $this->hasMany(PriceSource::class);
    }

    public function activePrices(): HasMany
    {
        return $this->priceSources()
            ->where('is_active', true)
            ->whereNotNull('latest_price')
            ->orderByRaw('case when latest_price = 0 then 1 else 0 end')
            ->orderBy('latest_price');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
