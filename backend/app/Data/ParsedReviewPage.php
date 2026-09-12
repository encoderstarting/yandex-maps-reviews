<?php

namespace App\Data;

final readonly class ParsedReviewPage
{
    /** @param list<ParsedReview> $reviews */
    public function __construct(
        public array $reviews,
        public int $currentPage,
        public int $totalPages,
        public int $totalReviews,
    ) {}
}
