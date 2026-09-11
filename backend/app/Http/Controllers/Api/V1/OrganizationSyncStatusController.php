<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\SyncRunResource;
use Illuminate\Http\Request;

class OrganizationSyncStatusController extends Controller
{
    public function __invoke(Request $request, int $organizationId): SyncRunResource
    {
        $organization = $request->user()
            ->organizations()
            ->findOrFail($organizationId);
        $syncRun = $organization->latestSyncRun()->firstOrFail();

        return new SyncRunResource($syncRun);
    }
}
