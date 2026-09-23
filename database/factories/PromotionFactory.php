<?php

namespace Database\Factories;

use App\Models\Promotion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Promotion>
 */
class PromotionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'code' => fake()->bothify('PROMO???'),
            'discount' => fake()->randomElement(['10%', '20%', '50K', '100K', 'free_shipping']),
            'expires_at' => fake()->dateTimeBetween('+1 month', '+6 months'),
            'status' => fake()->randomElement(['active', 'inactive']),
        ];
    }
}
