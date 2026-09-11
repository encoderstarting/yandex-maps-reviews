<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\OrganizationSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganizationSnapshot>
 */
class OrganizationSnapshotFactory extends Factory
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
            'rating' => fake()->randomFloat(1, 1, 5),
            'ratings_count' => fake()->numberBetween(1, 5000),
            'reviews_count' => fake()->numberBetween(1, 600),
            'captured_at' => now(),
        ];
    }
}
