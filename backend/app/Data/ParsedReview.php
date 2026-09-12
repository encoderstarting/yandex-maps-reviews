<?php

namespace App\Data;

use DateTimeImmutable;

final readonly class ParsedReview
{
    public function __construct(
        public string $externalId,
        public string $authorName,
        public int $rating,
        public ?string $text,
        public DateTimeImmutable $publishedAt,
    ) {}
}
