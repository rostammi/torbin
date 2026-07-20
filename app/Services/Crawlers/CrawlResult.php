<?php

namespace App\Services\Crawlers;

final readonly class CrawlResult
{
    public function __construct(
        public int $price,
        public ?string $buyUrl = null,
        public ?float $rating = null,
        public ?int $ratingCount = null,
        public ?string $ratingType = null,
        public array $details = [],
    ) {}
}
