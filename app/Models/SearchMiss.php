<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SearchMiss extends Model
{
    protected $fillable = ['query', 'normalized_query', 'ip_hash', 'searched_at'];

    protected function casts(): array
    {
        return ['searched_at' => 'datetime'];
    }
}
