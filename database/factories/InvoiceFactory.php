<?php

namespace Database\Factories;

use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $statuses = ['unpaid', 'partial', 'paid'];

        return [
            'order_id' => \App\Models\Order::factory(),
            'code' => 'HD' . fake()->unique()->numerify('###'),
            'total' => fake()->randomFloat(2, 50000, 1000000),
            'status' => fake()->randomElement($statuses),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
