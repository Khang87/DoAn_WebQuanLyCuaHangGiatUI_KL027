<?php

namespace Database\Factories;

use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    public function definition(): array
    {
        $price = fake()->randomFloat(2, 15000, 150000);
        $quantity = fake()->numberBetween(1, 5);

        return [
            'order_id' => \App\Models\Order::factory(),
            'service_id' => \App\Models\Service::factory(),
            'garment_id' => \App\Models\Garment::factory(),
            'item_name' => fake()->word(),
            'item_type' => 'garment',
            'price' => $price,
            'quantity' => $quantity,
            'weight' => fake()->randomElement(['1kg', '2kg', '3kg', null]),
            'subtotal' => $price * $quantity,
            'notes' => null,
        ];
    }
}
