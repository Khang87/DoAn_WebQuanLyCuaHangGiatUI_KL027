<?php

namespace Database\Factories;

use App\Models\Booking;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    public function definition(): array
    {
        $methods = ['nhan_do', 'giao_do'];
        $statuses = ['pending', 'confirmed', 'completed', 'cancelled'];

        return [
            'customer_id' => \App\Models\Customer::factory(),
            'staff_id' => null,
            'method' => fake()->randomElement($methods),
            'scheduled_date' => fake()->dateTimeBetween('+1 day', '+7 days'),
            'scheduled_time' => fake()->dateTimeBetween('+1 hour', '+5 days'),
            'address' => fake()->address(),
            'notes' => fake()->optional()->sentence(),
            'status' => fake()->randomElement($statuses),
        ];
    }
}
