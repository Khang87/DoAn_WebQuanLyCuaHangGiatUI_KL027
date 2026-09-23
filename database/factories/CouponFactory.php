<?php

namespace Database\Factories;

use App\Models\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Coupon>
 */
class CouponFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $discountTypes = ['percent', 'fixed', 'free_shipping'];
        $type = fake()->randomElement($discountTypes);

        return [
            'promotion_id' => \App\Models\Promotion::factory(),
            'code' => fake()->bothify('GIAO????'),
            'discount_type' => $type,
            'discount_value' => $type === 'percent' ? fake()->numberBetween(10, 30) : fake()->numberBetween(20000, 100000),
            'max_uses' => fake()->numberBetween(50, 200),
            'used_count' => fake()->numberBetween(0, 30),
            'expires_at' => fake()->dateTimeBetween('+1 month', '+3 months'),
            'status' => fake()->randomElement(['active', 'inactive']),
        ];
    }
}
