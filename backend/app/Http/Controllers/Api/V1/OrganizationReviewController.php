<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReviewResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrganizationReviewController extends Controller
{
    public function __invoke(Request $request, int $organizationId): AnonymousResourceCollection
    {
        $organization = $request->user()
            ->organizations()
            ->findOrFail($organizationId);

        $reviews = $organization->reviews()
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate(50);

        return ReviewResource::collection($reviews);
    }
}
