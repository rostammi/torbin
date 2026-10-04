<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteSetting extends Model
{
    public const CONTACT_PHONE = 'comparison_contact_phone';

    public const DEFAULT_CONTACT_PHONE = '09199010216';

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    public static function comparisonContactPhone(?string $category = null): string
    {
        if ($category && array_key_exists($category, config('comparison.categories', []))) {
            $categoryPhone = static::query()->whereKey(self::contactPhoneKey($category))->value('value');
            if (filled($categoryPhone)) {
                return (string) $categoryPhone;
            }
        }

        return (string) (static::query()->whereKey(self::CONTACT_PHONE)->value('value')
            ?: self::DEFAULT_CONTACT_PHONE);
    }

    public static function contactPhoneKey(string $category): string
    {
        return self::CONTACT_PHONE.'.'.$category;
    }

    public static function phoneHref(string $phone): string
    {
        return preg_replace('/[^0-9+]/', '', strtr($phone, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ])) ?? '';
    }
}
