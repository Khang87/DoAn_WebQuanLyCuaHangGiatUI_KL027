<?php

namespace Database\Factories;

use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $methods = ['cash', 'bank_transfer', 'e_wallet'];
        $statuses = ['pending', 'partial', 'paid'];

        return [
            'order_id' => \App\Models\Order::factory(),
            'amount' => fake()->randomFloat(2, 50000, 1000000),
            'method' => fake()->randomElement($methods),
            'status' => fake()->randomElement($statuses),
        ];
    }
}
