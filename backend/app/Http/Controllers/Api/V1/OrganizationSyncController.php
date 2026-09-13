<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\StartOrganizationSync;
use App\Http\Controllers\Controller;
use App\Http\Resources\SyncRunResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationSyncController extends Controller
{
    public function __invoke(
        Request $request,
        int $organizationId,
        StartOrganizationSync $startOrganizationSync,
    ): JsonResponse {
        $organization = $request->user()
            ->organizations()
            ->findOrFail($organizationId);
        $syncRun = $startOrganizationSync->execute($organization);
        $status = $syncRun->wasRecentlyCreated ? 202 : 200;

        return (new SyncRunResource($syncRun))
            ->response()
            ->setStatusCode($status);
    }
}
