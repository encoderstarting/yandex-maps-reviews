<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrganizationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'url' => $this->source_url,
            'external_id' => $this->external_id,
            'name' => $this->name,
            'rating' => $this->rating,
            'ratings_count' => $this->ratings_count,
            'reviews_count' => $this->reviews_count,
            'last_synced_at' => $this->last_synced_at?->toIso8601String(),
            'sync' => new SyncRunResource($this->whenLoaded('latestSyncRun')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
