<?php

namespace App\Services;

use App\Models\Tour;
use App\Models\TourSlugRedirect;
use App\Models\TourSuggestion;
use Illuminate\Support\Str;

class TourSlugGenerator
{
    private const VARIANT_PREFIXES = [
        'cheap' => 'cheap-',
        'last_minute' => 'last-minute-',
        'installment' => 'installment-',
        'air' => 'air-',
    ];

    public function forSuggestion(TourSuggestion $suggestion, ?Tour $tour = null): string
    {
        $destination = $suggestion->destination ?: preg_replace('/^تور\s+/u', '', $suggestion->keyword);
        $destinationSlug = $this->destinationSlug((string) $destination);
        $category = $suggestion->category ?: 'tour';
        if ($category !== 'tour') {
            $suffix = match ($category) {
                'hotel' => 'hotels',
                'stay' => 'stays',
                'visa' => 'visa',
                default => 'compare',
            };

            return $this->unique("{$destinationSlug}-{$suffix}", $tour);
        }
        $variant = (string) data_get($suggestion->metadata, 'variant', $this->variantFromTitle($suggestion->keyword));
        $base = $variant === 'from_tehran'
            ? "{$destinationSlug}-tour-from-tehran"
            : (self::VARIANT_PREFIXES[$variant] ?? '')."{$destinationSlug}-tour";

        return $this->unique($base, $tour);
    }

    public function forTour(Tour $tour): string
    {
        $suggestion = TourSuggestion::query()->where('tour_id', $tour->id)->oldest('id')->first();

        return $suggestion
            ? $this->forSuggestion($suggestion, $tour)
            : $this->unique($this->fromTitle($tour->title, $tour->category), $tour);
    }

    public function fromTitle(string $title, string $category = 'tour'): string
    {
        if ($category !== 'tour') {
            $subject = preg_replace('/(?:^|\s)(?:هتل|رزرو|اقامتگاه|بوم‌گردی|ارزان|ویزا|ویزای|قیمت|شرایط|اخذ|مقایسه|هزینه|خدمات)(?=\s|$)|[|\-–—].*$/u', ' ', $title);
            $suffix = match ($category) {
                'hotel' => 'hotels',
                'stay' => 'stays',
                'visa' => 'visa',
                default => 'compare',
            };

            return $this->destinationSlug(trim((string) $subject))."-{$suffix}";
        }
        $destination = preg_replace(
            '/(?:^|\s)(?:تور|ارزان|لحظه آخری|اقساطی|هوایی|مقایسه قیمت|خرید|از تهران)(?=\s|$)|[|\-–—].*$/u',
            ' ',
            $title,
        );
        $destinationSlug = $this->destinationSlug(trim((string) $destination));
        $variant = $this->variantFromTitle($title);

        return $variant === 'from_tehran'
            ? "{$destinationSlug}-tour-from-tehran"
            : (self::VARIANT_PREFIXES[$variant] ?? '')."{$destinationSlug}-tour";
    }

    public function refreshAll(): int
    {
        $updated = 0;
        Tour::query()->orderBy('id')->each(function (Tour $tour) use (&$updated) {
            $slug = $this->forTour($tour);
            if ($slug === $tour->slug) {
                return;
            }

            TourSlugRedirect::query()->updateOrCreate(
                ['old_slug' => $tour->slug],
                ['tour_id' => $tour->id],
            );
            $tour->update(['slug' => $slug]);
            $updated++;
        });

        return $updated;
    }

    public function unique(string $base, ?Tour $tour = null): string
    {
        $base = Str::slug($base) ?: 'tour';
        $slug = $base;
        $suffix = 2;

        while (Tour::query()
            ->where('slug', $slug)
            ->when($tour, fn ($query) => $query->whereKeyNot($tour->id))
            ->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    private function destinationSlug(string $destination): string
    {
        $alias = (string) data_get(config('crawler.images.aliases', []), trim($destination), '');
        if ($alias !== '') {
            $alias = preg_replace('/\s+(?:united arab emirates|south africa)$/i', '', $alias) ?? $alias;
            $alias = preg_replace(
                '/\s+(?:iran|turkey|georgia|azerbaijan|thailand|indonesia|russia|oman|qatar|china|japan|india|malaysia|tanzania|france|italy|spain|switzerland|austria|germany|netherlands|greece|cyprus|brazil|uzbekistan|kazakhstan|kyrgyzstan|lebanon)$/i',
                '',
                $alias,
            ) ?? $alias;
            $alias = preg_replace('/\b(?:travel|country|city|island|mount|desert)\b/i', ' ', $alias) ?? $alias;
            $slug = Str::slug($alias);
            if ($slug !== '') {
                return $slug;
            }
        }

        return Str::slug($destination) ?: 'destination';
    }

    private function variantFromTitle(string $title): string
    {
        return match (true) {
            str_contains($title, 'لحظه آخری') => 'last_minute',
            str_contains($title, 'اقساطی') => 'installment',
            str_contains($title, 'ارزان') => 'cheap',
            str_contains($title, 'هوایی') => 'air',
            str_contains($title, 'از تهران') => 'from_tehran',
            default => 'main',
        };
    }
}
