<?php

namespace App\Data;

final readonly class ParsedBusiness
{
    public function __construct(
        public string $externalId,
        public string $name,
        public float $rating,
        public int $ratingsCount,
        public int $reviewsCount,
    ) {}
}
