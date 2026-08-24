<?php

namespace App\Services\Currency;

use App\Models\PriceSource;
use RuntimeException;

class PriceCurrencyConverter
{
    public function __construct(private readonly TgjuDollarRate $dollarRate) {}

    /** @return array{price:int,currency:string,details:array<string,mixed>} */
    public function convert(mixed $value, PriceSource $source, ?string $currencyHint = null): array
    {
        if (! is_scalar($value)) {
            throw new RuntimeException('مقدار استخراج‌شده عددی نیست.');
        }

        $text = $this->normalizeDigits(html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5));
        $mode = $this->mode($source, $currencyHint, $text);
        $multiplier = (float) ($source->price_multiplier ?: 1);

        if (in_array($mode, ['usd', 'mixed'], true)) {
            $usd = $this->firstUsdAmount($text);
            $local = $this->firstLocalAmount($text);

            if ($usd === null && $mode === 'usd') {
                $usd = $this->number($text);
            }
            if ($usd === null) {
                throw new RuntimeException('مبلغ دلاری معتبری در قیمت پیدا نشد.');
            }

            $rate = $this->dollarRate->toman();
            $localToman = $local ? $this->toToman($local['amount'], $local['currency']) : 0;
            $price = (int) round((($usd * $rate) + $localToman) * $multiplier);

            return [
                'price' => $price,
                'currency' => 'تومان',
                'details' => array_filter([
                    'source_usd_price' => $usd,
                    'source_local_price_toman' => $localToman ?: null,
                    'usd_rate_toman' => $rate,
                    'exchange_rate_source' => (string) config('crawler.dollar.url'),
                ], fn ($item) => $item !== null),
            ];
        }

        $local = $this->firstLocalAmount($text);
        $amount = $local['amount'] ?? $this->number($text);
        $inputCurrency = $local['currency'] ?? ($mode === 'rial' ? 'ریال' : ($mode === 'toman' ? 'تومان' : $source->currency));
        $outputCurrency = $source->currency ?: 'تومان';

        if ($inputCurrency === 'ریال' && $outputCurrency === 'تومان') {
            $amount /= 10;
        } elseif ($inputCurrency !== 'ریال' && $outputCurrency === 'ریال') {
            $amount *= 10;
        }

        return ['price' => (int) round($amount * $multiplier), 'currency' => $outputCurrency, 'details' => []];
    }

    private function mode(PriceSource $source, ?string $hint, string $text): string
    {
        $configured = $source->source_currency ?: 'auto';
        if ($configured !== 'auto') {
            return $configured;
        }

        $hint = strtoupper((string) $hint);
        if (in_array($hint, ['USD', 'US$', '$'], true)) {
            return 'usd';
        }
        if (in_array($hint, ['IRR', 'RIAL'], true)) {
            return 'rial';
        }
        if (preg_match('/(?:US\$|USD|\$|دلار)/iu', $text)) {
            return preg_match('/(?:تومان|تومن|ریال)/u', $text) ? 'mixed' : 'usd';
        }
        if (preg_match('/ریال/u', $text)) {
            return 'rial';
        }

        return preg_match('/(?:تومان|تومن)/u', $text) ? 'toman' : 'auto';
    }

    private function firstUsdAmount(string $text): ?float
    {
        if (preg_match('/(?:US\$|\$|دلار)\s*([0-9][0-9\s,.]*)|([0-9][0-9\s,.]*)\s*(?:USD|US\$|\$|دلار)/iu', $text, $match) !== 1) {
            return null;
        }

        return $this->number($match[1] !== '' ? $match[1] : $match[2]);
    }

    /** @return array{amount:float,currency:string}|null */
    private function firstLocalAmount(string $text): ?array
    {
        if (preg_match('/([0-9][0-9\s,.]*)\s*(تومان|تومن|ریال)/u', $text, $match) !== 1) {
            return null;
        }

        return ['amount' => $this->number($match[1]), 'currency' => $match[2] === 'ریال' ? 'ریال' : 'تومان'];
    }

    private function toToman(float $amount, string $currency): float
    {
        return $currency === 'ریال' ? $amount / 10 : $amount;
    }

    private function number(string $value): float
    {
        $value = trim(str_replace(['،', '٬', '٫', ' '], [',', ',', '.', ''], $value));
        $value = preg_replace('/[^0-9,.]/', '', $value) ?: '0';

        if (str_contains($value, ',') && ! str_contains($value, '.')) {
            $value = preg_match('/,\d{1,2}$/', $value) ? str_replace(',', '.', $value) : str_replace(',', '', $value);
        } else {
            $value = str_replace(',', '', $value);
        }

        return (float) $value;
    }

    private function normalizeDigits(string $value): string
    {
        return str_replace(
            ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'],
            ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
            $value,
        );
    }
}
