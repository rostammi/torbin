<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Agency extends Model
{
    public const DEFAULT_BALANCE = 1_000_000;

    public const DEFAULT_COST_PER_CLICK = 1_000;

    protected $fillable = ['name', 'balance', 'cost_per_click', 'currency', 'contact_priority'];

    protected $attributes = [
        'balance' => self::DEFAULT_BALANCE,
        'cost_per_click' => self::DEFAULT_COST_PER_CLICK,
        'currency' => 'تومان',
        'contact_priority' => 100,
    ];

    protected function casts(): array
    {
        return ['balance' => 'integer', 'cost_per_click' => 'integer', 'contact_priority' => 'integer'];
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

    public function providerName(): string
    {
        $name = str_replace(['ي', 'ك', "\u{200C}"], ['ی', 'ک', ' '], trim($this->name));
        $name = preg_replace('/\s+(?:تور|هتل|اقامتگاه|ویزا)\s*$/u', '', $name) ?? $name;

        return trim(preg_replace('/\s+/u', ' ', $name) ?? $name);
    }

    public function providerKey(): string
    {
        return preg_replace('/[^\pL\pN]+/u', '', mb_strtolower($this->providerName())) ?: (string) $this->getKey();
    }

    public function providerSlug(): string
    {
        $configured = (string) data_get(config('comparison.provider_slugs', []), $this->providerKey(), '');

        if ($configured !== '') {
            return $configured;
        }

        return Str::slug($this->providerName()) ?: 'provider-'.$this->getKey();
    }

    public function publicUrl(): string
    {
        return route('providers.show', $this->providerSlug());
    }

    public function canAffordClick(): bool
    {
        return $this->balance > 0
            && ($this->cost_per_click === 0 || $this->balance >= $this->cost_per_click);
    }
}
