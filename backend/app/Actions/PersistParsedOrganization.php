<?php

namespace App\Actions;

use App\Data\ParsedOrganization;
use App\Data\ParsedReview;
use App\Models\Organization;
use App\Models\Review;
use App\Models\SyncRun;
use App\SyncStatus;
use Illuminate\Support\Facades\DB;

class PersistParsedOrganization
{
    public function execute(
        Organization $organization,
        SyncRun $syncRun,
        ParsedOrganization $parsed,
    ): void {
        DB::transaction(function () use ($organization, $syncRun, $parsed): void {
            $organization->update([
                'external_id' => $parsed->externalId,
                'name' => $parsed->name,
                'rating' => $parsed->rating,
                'ratings_count' => $parsed->ratingsCount,
                'reviews_count' => $parsed->reviewsCount,
                'last_synced_at' => now(),
            ]);

            $this->upsertReviews($organization, $parsed->reviews);
            $this->storeSnapshotIfChanged($organization, $parsed);

            $syncRun->update([
                'status' => SyncStatus::Completed,
                'progress' => 100,
                'processed_reviews' => count($parsed->reviews),
                'error_code' => null,
                'error_message' => null,
                'finished_at' => now(),
            ]);
        });
    }

    /** @param list<ParsedReview> $reviews */
    private function upsertReviews(Organization $organization, array $reviews): void
    {
        if ($reviews === []) {
            return;
        }

        $timestamp = now();
        $rows = array_map(function (ParsedReview $review) use ($organization, $timestamp): array {
            $payload = [
                'author_name' => $review->authorName,
                'rating' => $review->rating,
                'text' => $review->text,
                'published_at' => $review->publishedAt->format(DATE_ATOM),
            ];

            return [
                'organization_id' => $organization->id,
                'external_id' => $review->externalId,
                ...$payload,
                'published_at' => $review->publishedAt,
                'payload_hash' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)),
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        }, $reviews);

        Review::query()->upsert(
            $rows,
            ['organization_id', 'external_id'],
            ['author_name', 'rating', 'text', 'published_at', 'payload_hash', 'updated_at'],
        );
    }

    private function storeSnapshotIfChanged(Organization $organization, ParsedOrganization $parsed): void
    {
        $latest = $organization->snapshots()
            ->latest('captured_at')
            ->latest('id')
            ->first();
        $rating = number_format($parsed->rating, 1, '.', '');

        if ($latest !== null
            && $latest->rating === $rating
            && $latest->ratings_count === $parsed->ratingsCount
            && $latest->reviews_count === $parsed->reviewsCount) {
            return;
        }

        $organization->snapshots()->create([
            'rating' => $parsed->rating,
            'ratings_count' => $parsed->ratingsCount,
            'reviews_count' => $parsed->reviewsCount,
            'captured_at' => now(),
        ]);
    }
}
