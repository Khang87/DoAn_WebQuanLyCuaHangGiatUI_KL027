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
            'TenLoaiDoGiat' => fake()->randomElement(['Áo dài', 'Áo vest', 'Áo sơ mi', 'Quần tây', 'Váy cưới', 'Chăn ga', 'Áo khoác', 'Quần jeans', 'Thảm', 'Gối chăn']),
            'MoTa' => fake()->optional()->sentence(),
            'TrangThai' => 'Hoạt động',
        ];
    }
}
