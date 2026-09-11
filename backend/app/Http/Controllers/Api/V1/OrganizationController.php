<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ConnectOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrganizationRequest;
use App\Http\Resources\OrganizationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrganizationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $organizations = $request->user()
            ->organizations()
            ->with('latestSyncRun')
            ->latest()
            ->paginate(15);

        return OrganizationResource::collection($organizations);
    }

    public function store(StoreOrganizationRequest $request, ConnectOrganization $connectOrganization): JsonResponse
    {
        $organization = $connectOrganization->execute(
            $request->user(),
            $request->validated('url'),
        );
        $status = $organization->wasRecentlyCreated ? 201 : 200;

        return (new OrganizationResource($organization))
            ->response()
            ->setStatusCode($status);
    }

    public function show(Request $request, int $organizationId): OrganizationResource
    {
        $organization = $request->user()
            ->organizations()
            ->with('latestSyncRun')
            ->findOrFail($organizationId);

        return new OrganizationResource($organization);
    }
}
