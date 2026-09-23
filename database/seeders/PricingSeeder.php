<?php

namespace Database\Seeders;

use App\Models\Pricing;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PricingSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $pricings = [
            ['name' => 'Giặt thường', 'unit' => 'kg', 'price' => 25000, 'status' => 'active', 'description' => 'Giặt ủi thường theo kg'],
            ['name' => 'Giặt khô', 'unit' => 'cái', 'price' => 45000, 'status' => 'active', 'description' => 'Giặt khô theo món'],
            ['name' => 'Ủi đồ', 'unit' => 'món', 'price' => 15000, 'status' => 'active', 'description' => 'Ủi phẳng đồ giặt'],
            ['name' => 'Giặt chăn mền', 'unit' => 'món', 'price' => 80000, 'status' => 'active', 'description' => 'Giặt chăn mền'],
            ['name' => 'Giặt giày', 'unit' => 'đôi', 'price' => 60000, 'status' => 'active', 'description' => 'Giặt giày'],
        ];

        foreach ($pricings as $pricing) {
            Pricing::create($pricing);
        }
    }
}
