<?php

namespace Database\Factories;

use App\Models\GarmentCondition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GarmentCondition>
 */
class GarmentConditionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $conditionTypes = ['Rách', 'Bẩn nặng', 'Phai màu', 'Mất cúc', 'Ống rách', 'Sờn', 'Vết ố', 'Nhăn'];

        return [
            'garment_id' => \App\Models\Garment::factory(),
            'condition_type' => fake()->randomElement($conditionTypes),
            'description' => fake()->sentence(),
            'photo' => null,
            'status' => 'active',
        ];
    }
}
