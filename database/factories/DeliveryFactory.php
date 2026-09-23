<?php

namespace Database\Factories;

use App\Models\Delivery;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Delivery>
 */
class DeliveryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $methods = ['pickup', 'dropoff'];
        $statuses = ['pending', 'confirmed', 'completed', 'cancelled'];

        return [
            'customer_id' => \App\Models\Customer::factory(),
            'method' => fake()->randomElement($methods),
            'address' => fake()->address(),
            'pickup_date' => fake()->dateTimeBetween('+1 day', '+5 days'),
            'pickup_time' => fake()->dateTimeBetween('+1 hour', '+5 days'),
            'status' => fake()->randomElement($statuses),
        ];
    }
}
