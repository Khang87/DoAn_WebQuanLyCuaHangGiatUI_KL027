<?php

namespace Database\Factories;

use App\Models\Pricing;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pricing>
 */
class PricingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->word(),
            'unit' => fake()->randomElement(['kg', 'cái', 'món', 'đôi', 'bộ']),
            'price' => fake()->randomFloat(0, 15000, 200000),
            'status' => 'active',
            'description' => fake()->optional()->sentence(),
        ];
    }
}
