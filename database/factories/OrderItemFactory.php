<?php

namespace Database\Factories;

use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => \App\Models\Order::factory(),
            'service_id' => \App\Models\Service::factory(),
            'item_name' => fake()->word(),
            'item_type' => 'garment',
            'price' => fake()->randomFloat(2, 15000, 150000),
            'quantity' => fake()->numberBetween(1, 5),
            'subtotal' => function (array $attributes) {
                return $attributes['price'] * $attributes['quantity'];
            },
            'notes' => null,
        ];
    }
}
