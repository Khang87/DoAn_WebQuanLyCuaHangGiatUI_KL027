<?php

namespace Database\Factories;

use App\Models\Promotion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Promotion>
 */
class PromotionFactory extends Factory
{
    public function definition(): array
    {
        $discountType = fake()->randomElement(['percentage', 'fixed']);

        return [
            'name' => fake()->words(3, true),
            'code' => fake()->bothify('PROMO???'),
            'discount_type' => $discountType,
            'discount_value' => $discountType === 'percentage'
                ? fake()->numberBetween(10, 30)
                : fake()->numberBetween(20000, 100000),
            'min_order_amount' => 0,
            'max_discount' => null,
            'usage_limit' => null,
            'conditions' => null,
            'starts_at' => now()->subMonth(),
            'expires_at' => fake()->dateTimeBetween('+1 month', '+6 months'),
            'status' => fake()->randomElement(['active', 'inactive']),
        ];
    }
}
