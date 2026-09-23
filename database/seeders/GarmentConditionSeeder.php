<?php

namespace Database\Seeders;

use App\Models\Garment;
use App\Models\GarmentCondition;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class GarmentConditionSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $garments = \App\Models\Garment::all();
        $conditionTypes = ['Rách', 'Bẩn nặng', 'Phai màu', 'Mất cúc', 'Ống rách', 'Sờn', 'Vết ố', 'Nhăn'];

        foreach ($garments as $garment) {
            $conditionCount = rand(1, 3);
            for ($i = 0; $i < $conditionCount; $i++) {
                GarmentCondition::create([
                    'garment_id' => $garment->id,
                    'condition_type' => $conditionTypes[array_rand($conditionTypes)],
                    'description' => 'Mô tả hiện trạng cho ' . $garment->name,
                    'photo' => null,
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
