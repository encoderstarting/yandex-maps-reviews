<?php

namespace App\Actions;

use App\Jobs\SyncOrganizationJob;
use App\Models\Organization;
use App\Models\SyncRun;
use App\SyncStatus;
use Illuminate\Support\Facades\DB;

class StartOrganizationSync
{
    public function execute(Organization $organization): SyncRun
    {
        return DB::transaction(function () use ($organization): SyncRun {
            $lockedOrganization = Organization::query()
                ->whereKey($organization->id)
                ->lockForUpdate()
                ->firstOrFail();
            $activeSyncRun = $lockedOrganization->syncRuns()
                ->whereIn('status', [SyncStatus::Pending->value, SyncStatus::Running->value])
                ->latest('id')
                ->first();

            if ($activeSyncRun !== null) {
                return $activeSyncRun;
            }

            $syncRun = $lockedOrganization->syncRuns()->create([
                'status' => SyncStatus::Pending,
                'progress' => 0,
                'processed_reviews' => 0,
            ]);

            SyncOrganizationJob::dispatch($lockedOrganization->id, $syncRun->id)->afterCommit();

            return $syncRun;
        });
    }
}
