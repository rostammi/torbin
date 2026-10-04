<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComparisonReport extends Model
{
    public const TYPES = [
        'inappropriate_content' => 'محتوای نامناسب',
        'inappropriate_image' => 'تصویر نامناسب',
        'incorrect_price' => 'قیمت اشتباه',
    ];

    public const STATUSES = [
        'pending' => 'در انتظار بررسی',
        'resolved' => 'رسیدگی‌شده',
    ];

    protected $fillable = [
        'tour_id', 'type', 'details', 'status', 'ip_hash', 'user_agent_hash', 'resolved_at',
    ];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
