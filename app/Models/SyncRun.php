<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncRun extends Model
{
    protected $fillable = [
        'user_id', 'type', 'status', 'total', 'successful', 'failed', 'details',
        'error', 'started_at', 'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'details' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function canCancel(): bool
    {
        return $this->status === 'running' && $this->finished_at === null;
    }

    public function canRetry(): bool
    {
        if (! in_array($this->status, ['failed', 'partial'], true)) {
            return false;
        }

        return match ($this->type) {
            'discover_tours', 'discover_hotels', 'discover_stays', 'discover_visas',
            'prices', 'content', 'images', 'images_tours', 'images_hotels', 'images_stays', 'images_visas', 'all',
            'provision_all_tours' => true,
            'provision_tour' => (int) data_get($this->details, 'suggestion_id') > 0,
            'scan_comparison_source' => (int) data_get($this->details, 'source_id') > 0,
            'add_tour_images', 'refresh_tour_images' => (int) data_get($this->details, 'tour_id') > 0,
            default => false,
        };
    }
}
