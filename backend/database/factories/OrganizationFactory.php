<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $slug = fake()->unique()->slug(2);

        return [
            'user_id' => User::factory(),
            'external_id' => (string) fake()->unique()->numberBetween(1000000, 999999999),
            'source_url' => "https://yandex.ru/maps/org/{$slug}/",
            'normalized_url' => "https://yandex.ru/maps/org/{$slug}",
            'name' => fake()->company(),
            'rating' => fake()->randomFloat(1, 1, 5),
            'ratings_count' => fake()->numberBetween(1, 5000),
            'reviews_count' => fake()->numberBetween(1, 600),
            'last_synced_at' => now(),
        ];
    }
}
