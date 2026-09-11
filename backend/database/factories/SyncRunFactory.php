<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\SyncRun;
use App\SyncStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SyncRun>
 */
class SyncRunFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'status' => SyncStatus::Pending,
            'progress' => 0,
            'processed_reviews' => 0,
            'error_code' => null,
            'error_message' => null,
            'started_at' => null,
            'finished_at' => null,
        ];
    }
}
