<?php

namespace Database\Factories;

use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $types = ['wash', 'dry_clean', 'iron', 'blanket', 'shoes', 'express', 'steam', 'wash_dry'];

        return [
            'name' => fake()->word(),
            'type' => fake()->randomElement($types),
            'price' => fake()->randomFloat(0, 15000, 200000),
            'unit' => fake()->randomElement(['kg', 'cái', 'món', 'đôi']),
            'status' => 'active',
            'description' => fake()->optional()->sentence(),
            'service_category_id' => \App\Models\ServiceCategory::factory(),
        ];
    }
}
