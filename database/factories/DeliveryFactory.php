<?php

namespace Database\Factories;

use App\Models\Delivery;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Delivery>
 */
class DeliveryFactory extends Factory
{
    public function definition(): array
    {
        $methods = ['nhan_do', 'giao_do'];
        $statuses = ['pending', 'picking', 'delivering', 'completed', 'cancelled'];

        return [
            'code' => 'GH' . fake()->unique()->numerify('####'),
            'order_id' => \App\Models\Order::factory(),
            'customer_id' => \App\Models\Customer::factory(),
            'employee_id' => null,
            'method' => fake()->randomElement($methods),
            'address' => fake()->address(),
            'pickup_date' => fake()->dateTimeBetween('+1 day', '+5 days'),
            'pickup_time' => fake()->dateTimeBetween('+1 hour', '+5 days'),
            'status' => fake()->randomElement($statuses),
        ];
    }
}
