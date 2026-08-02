<?php

namespace App\Services\Search;

final readonly class TravelSearchIntent
{
    public function __construct(
        public string $original,
        public string $normalized,
        public ?int $maximumBudget = null,
        public ?string $region = null,
        public ?string $destination = null,
        public bool $isRecommendation = false,
    ) {}

    public function regionLabel(): ?string
    {
        return match ($this->region) {
            'domestic' => 'سفر داخلی',
            'foreign' => 'سفر خارجی',
            default => null,
        };
    }
}
