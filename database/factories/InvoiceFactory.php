<?php

namespace Database\Factories;

use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        $statuses = ['unpaid', 'partial', 'paid'];
        $totalAmount = fake()->randomFloat(2, 50000, 1000000);
        $discount = fake()->randomFloat(2, 0, 50000);
        $deliveryFee = fake()->randomElement([0, 15000, 20000]);

        return [
            'order_id' => \App\Models\Order::factory(),
            'code' => 'HD' . fake()->unique()->numerify('###'),
            'invoice_date' => now(),
            'total_amount' => $totalAmount,
            'discount_amount' => $discount,
            'delivery_fee' => $deliveryFee,
            'grand_total' => max(0, $totalAmount - $discount + $deliveryFee),
            'status' => fake()->randomElement($statuses),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
