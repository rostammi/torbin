<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PriceSource extends Model
{
    use HasFactory;

    protected array $pendingAgencySettings = [];

    protected $fillable = [
        'tour_id', 'agency_id', 'provider_name', 'source_url', 'buy_url', 'extraction_type', 'selector',
        'price_multiplier', 'latest_price', 'currency', 'source_currency', 'is_active', 'last_checked_at',
        'last_status', 'last_error', 'latest_rating', 'latest_rating_count', 'rating_type',
        'latest_details', 'rejected_urls', 'is_featured', 'is_contact_only', 'contact_phone',
        'display_priority', 'is_pinned', 'content_insights', 'content_checked_at', 'content_error',
    ];

    protected function casts(): array
    {
        return [
            'price_multiplier' => 'decimal:2',
            'latest_price' => 'integer',
            'latest_rating' => 'float',
            'latest_rating_count' => 'integer',
            'latest_details' => 'array',
            'rejected_urls' => 'array',
            'content_insights' => 'array',
            'is_active' => 'boolean',
            'last_checked_at' => 'datetime',
            'content_checked_at' => 'datetime',
        ];
    }

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function scopeFunded(Builder $query): Builder
    {
        return $query->whereHas('agency', fn (Builder $agency) => $agency
            ->where('balance', '>', 0)
            ->where(fn (Builder $credit) => $credit
                ->where('cost_per_click', 0)
                ->orWhereColumn('balance', '>=', 'cost_per_click')));
    }

    protected static function booted(): void
    {
        static::saving(function (PriceSource $source) {
            if ($source->provider_name && ($source->isDirty('provider_name') || ! $source->agency_id)) {
                $source->agency_id = Agency::firstOrCreate(
                    ['name' => $source->provider_name],
                    [
                        'balance' => Agency::DEFAULT_BALANCE,
                        'cost_per_click' => Agency::DEFAULT_COST_PER_CLICK,
                    ],
                )->id;
            }
        });

        static::saved(fn (PriceSource $source) => $source->syncPendingAgencySettings());
    }

    public function history(): HasMany
    {
        return $this->hasMany(PriceHistory::class)->latest('observed_at');
    }

    public function contactClicks(): HasMany
    {
        return $this->hasMany(ContactClick::class);
    }

    public function recentHistory(): HasMany
    {
        return $this->hasMany(PriceHistory::class)->latest('observed_at')->limit(30);
    }

    public function getIsFeaturedAttribute(): bool
    {
        return (bool) $this->agencySetting('is_featured', false);
    }

    public function setIsFeaturedAttribute(mixed $value): void
    {
        $this->pendingAgencySettings['is_featured'] = filter_var($value, FILTER_VALIDATE_BOOL);
    }

    public function getIsContactOnlyAttribute(): bool
    {
        return (bool) $this->agencySetting('is_contact_only', false);
    }

    public function setIsContactOnlyAttribute(mixed $value): void
    {
        $this->pendingAgencySettings['is_contact_only'] = filter_var($value, FILTER_VALIDATE_BOOL);
    }

    public function getContactPhoneAttribute(): ?string
    {
        return $this->agencySetting('contact_phone');
    }

    public function setContactPhoneAttribute(mixed $value): void
    {
        $this->pendingAgencySettings['contact_phone'] = filled($value) ? trim((string) $value) : null;
    }

    public function getDisplayPriorityAttribute(): int
    {
        return (int) $this->agencySetting('display_priority', 100);
    }

    public function setDisplayPriorityAttribute(mixed $value): void
    {
        $this->pendingAgencySettings['display_priority'] = max(0, (int) $value);
    }

    public function getIsPinnedAttribute(): bool
    {
        return (bool) $this->agencySetting('is_pinned', false);
    }

    public function setIsPinnedAttribute(mixed $value): void
    {
        $this->pendingAgencySettings['is_pinned'] = filter_var($value, FILTER_VALIDATE_BOOL);
    }

    private function agencySetting(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->pendingAgencySettings)) {
            return $this->pendingAgencySettings[$key];
        }

        if (! $this->relationLoaded('agency') && $this->agency_id) {
            $this->setRelation('agency', $this->agency()->first());
        }

        $agency = $this->relationLoaded('agency') ? $this->getRelation('agency') : null;

        return $agency?->{$key} ?? $default;
    }

    private function syncPendingAgencySettings(): void
    {
        if ($this->pendingAgencySettings === [] || ! $this->agency_id) {
            return;
        }

        $settings = $this->pendingAgencySettings;
        if ($settings['is_pinned'] ?? false) {
            $settings['is_featured'] = true;
            Agency::query()->whereKeyNot($this->agency_id)->update(['is_pinned' => false]);
        }

        $agency = Agency::findOrFail($this->agency_id);
        $agency->update($settings);
        $this->pendingAgencySettings = [];
        $this->setRelation('agency', $agency->fresh());
    }
}
