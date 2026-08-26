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

        return rtrim(url('/'.$this->slug), '/').'/';
    }

    public function contentWithImageAlts(): string
    {
        $fallbackAlt = e('تصویر مرتبط با '.$this->title);

        return preg_replace_callback('/<img\b[^>]*>/i', function (array $matches) use ($fallbackAlt): string {
            $tag = $matches[0];

            if (preg_match('/\balt\s*=\s*(["\'])(.*?)\1/is', $tag, $alt)) {
                if (trim(strip_tags(html_entity_decode($alt[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'))) !== '') {
                    return $tag;
                }

                return preg_replace(
                    '/\balt\s*=\s*(["\'])(.*?)\1/is',
                    'alt="'.$fallbackAlt.'"',
                    $tag,
                    1,
                ) ?? $tag;
            }

            $closingPosition = str_ends_with($tag, '/>') ? strlen($tag) - 2 : strlen($tag) - 1;

            return substr($tag, 0, $closingPosition).' alt="'.$fallbackAlt.'"'.substr($tag, $closingPosition);
        }, $this->content) ?? $this->content;
    }
}
