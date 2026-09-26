<?php

namespace Database\Factories;

use App\Models\Notification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Notification>
 */
class NotificationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => \App\Models\User::factory(),
            'type' => fake()->randomElement(['order', 'payment', 'booking', 'promotion']),
            'message' => fake()->paragraph(),
            'order_id' => null,
            'sent_at' => now(),
            'read_at' => null,
        ];
    }
}
