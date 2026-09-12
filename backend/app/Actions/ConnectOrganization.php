<?php

namespace App\Actions;

use App\Jobs\SyncOrganizationJob;
use App\Models\Organization;
use App\Models\User;
use App\Support\YandexMapsUrlNormalizer;
use App\SyncStatus;
use Illuminate\Support\Facades\DB;

class ConnectOrganization
{
    public function __construct(private readonly YandexMapsUrlNormalizer $normalizer) {}

    public function execute(User $user, string $sourceUrl): Organization
    {
        $organization = DB::transaction(function () use ($user, $sourceUrl): Organization {
            $organization = $user->organizations()->firstOrCreate(
                ['normalized_url' => $this->normalizer->normalize($sourceUrl)],
                ['source_url' => $sourceUrl],
            );

            if ($organization->wasRecentlyCreated) {
                $syncRun = $organization->syncRuns()->create([
                    'status' => SyncStatus::Pending,
                    'progress' => 0,
                    'processed_reviews' => 0,
                ]);

                SyncOrganizationJob::dispatch($organization->id, $syncRun->id)->afterCommit();
            }

            return $organization->load('latestSyncRun');
        });

        return $organization;
    }
}
