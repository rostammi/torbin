<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaticPage extends Model
{
    public const MAG_SLUGS = [
        'worldwide-tours',
        'accommodation',
        'domestic-tours',
        'domestic-hotels',
        'worldwide-hotels',
    ];

    protected $fillable = ['slug', 'title', 'content', 'is_published'];

    protected function casts(): array
    {
        return ['is_published' => 'boolean'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function publicUrl(): string
    {
        if (in_array($this->slug, self::MAG_SLUGS, true)) {
            return url('/mag/'.$this->slug).'/';
        }

        return url('/'.$this->slug);
    }
}
