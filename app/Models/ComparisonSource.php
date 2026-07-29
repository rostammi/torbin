<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComparisonSource extends Model
{
    protected $fillable = [
        'name', 'homepage_url', 'homepage_hash', 'categories', 'is_active', 'last_status', 'last_error',
        'last_scan_summary', 'last_scanned_at',
    ];

    protected function casts(): array
    {
        return [
            'categories' => 'array',
            'last_scan_summary' => 'array',
            'last_scanned_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }
}
