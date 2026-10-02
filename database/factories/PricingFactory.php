<?php

namespace Database\Factories;

use App\Models\Garment;
use App\Models\Pricing;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pricing>
 */
class PricingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'service_id' => Service::factory(),
            'garment_id' => Garment::factory(),
            'name' => fake()->word(),
            'unit' => fake()->randomElement(['kg', 'cái', 'món', 'đôi', 'bộ']),
            'price' => fake()->randomFloat(0, 15000, 200000),
            'effective_date' => now(),
            'status' => 'active',
            'description' => fake()->optional()->sentence(),
        ];
    }
}
