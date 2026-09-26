<?php

namespace Database\Factories;

use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    public function definition(): array
    {
        $methods = ['cash', 'bank_transfer', 'e_wallet'];
        $statuses = ['pending', 'partial', 'paid'];

        return [
            'order_id' => \App\Models\Order::factory(),
            'amount' => fake()->randomFloat(2, 50000, 1000000),
            'method' => fake()->randomElement($methods),
            'paid_at' => fake()->optional()->dateTime(),
            'status' => fake()->randomElement($statuses),
            'transaction_code' => fake()->optional()->bothify('TXN####'),
        ];
    }
}
