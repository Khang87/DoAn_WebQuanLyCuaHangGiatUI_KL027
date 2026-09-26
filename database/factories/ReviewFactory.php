<?php

namespace Database\Factories;

use App\Models\Review;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id' => \App\Models\Order::factory(),
            'customer_id' => \App\Models\Customer::factory(),
            'rating' => fake()->numberBetween(1, 5),
            'content' => fake()->optional()->paragraph(),
            'images' => null,
            'shop_response' => null,
            'status' => fake()->randomElement(['visible', 'hidden']),
            'reviewed_at' => now(),
        ];
    }
}
