<?php

namespace Database\Factories;

use App\Models\Booking;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
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
            'service_id' => \App\Models\Service::factory(),
            'garment_type' => fake()->randomElement(['Áo dài', 'Váy cưới', 'Áo vest', 'Chăn ga', 'Áo sơ mi', 'Quần tây', 'Áo khoác']),
            'quantity' => fake()->numberBetween(1, 5),
            'delivery_method' => fake()->randomElement($methods),
            'address' => fake()->address(),
            'pickup_date' => fake()->dateTimeBetween('+1 day', '+7 days'),
            'pickup_time' => fake()->dateTimeBetween('+1 hour', '+5 days'),
            'notes' => fake()->optional()->sentence(),
            'status' => fake()->randomElement($statuses),
        ];
    }
}
