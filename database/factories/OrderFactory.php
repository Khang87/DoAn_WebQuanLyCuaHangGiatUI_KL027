<?php

namespace Database\Factories;

use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        $statuses = ['pending', 'received', 'sorting', 'processing', 'washed', 'delivering', 'completed', 'cancelled'];
        $weights = ['1kg', '3kg', '5kg', '8kg', '10kg', '15kg'];

        return [
            'code' => 'DH' . fake()->unique()->numerify('###'),
            'customer_id' => \App\Models\Customer::factory(),
            'employee_id' => \App\Models\User::factory()->state(['role' => 'staff']),
            'service_id' => \App\Models\Service::factory(),
            'promotion_id' => null,
            'subtotal' => 0,
            'discount_by_promotion' => 0,
            'points_used' => 0,
            'discount_by_points' => 0,
            'weight_kg' => fake()->randomElement($weights),
            'quantity_items' => fake()->randomElement(['2 món', '3 món', '5 món', '10 món', '15 món']),
            'total_amount' => fake()->randomFloat(2, 50000, 500000),
            'status' => fake()->randomElement($statuses),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
