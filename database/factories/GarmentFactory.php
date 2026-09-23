<?php

namespace Database\Factories;

use App\Models\Garment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Garment>
 */
class GarmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Áo dài', 'Áo vest', 'Áo sơ mi', 'Quần tây', 'Váy cưới', 'Chăn ga', 'Áo khoác', 'Quần jeans', 'Thảm', 'Gối chăn']),
            'category' => fake()->randomElement(['Trang phục', 'Đồ dùng', 'Thảm', 'Phụ kiện']),
            'price' => fake()->randomElement([20000, 25000, 30000, 35000, 40000, 45000, 50000, 60000, 80000, 150000]),
            'condition_note' => fake()->optional()->sentence(),
            'status' => 'active',
        ];
    }
}
