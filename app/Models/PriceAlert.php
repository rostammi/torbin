<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceAlert extends Model
{
    public const ORIGINS = [
        'price_drop' => 'اگر ارزان‌تر شد خبرم کن',
        'no_price_contact' => 'درخواست تماس؛ بدون قیمت آنلاین',
    ];

    public const CONTACT_STATUSES = [
        'pending' => 'در انتظار تماس',
        'contacted' => 'تماس گرفته شده',
    ];

    protected $fillable = [
        'tour_id', 'phone', 'phone_hash', 'unsubscribe_token', 'unsubscribe_token_hash',
        'target_price', 'currency', 'is_active', 'last_notified_at',
        'last_notified_price', 'last_error', 'origin', 'contact_status', 'contacted_at',
    ];

    protected function casts(): array
    {
        return [
            'phone' => 'encrypted',
            'unsubscribe_token' => 'encrypted',
            'target_price' => 'integer',
            'last_notified_price' => 'integer',
            'is_active' => 'boolean',
            'last_notified_at' => 'datetime',
            'contacted_at' => 'datetime',
        ];
    }

    public function originLabel(): string
    {
        return self::ORIGINS[$this->origin] ?? $this->origin;
    }

    public function contactStatusLabel(): string
    {
        return self::CONTACT_STATUSES[$this->contact_status] ?? $this->contact_status;
    }

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }
}
