<?php

namespace Database\Seeders;

use App\Models\Garment;
use App\Models\Pricing;
use App\Models\Service;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PricingSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $services = Service::all();
        $garments = Garment::all();

        $pricings = [
            ['name' => 'Giặt thường', 'unit' => 'kg', 'price' => 25000, 'service_key' => 'express_wash_dry'],
            ['name' => 'Giặt khô', 'unit' => 'cái', 'price' => 45000, 'service_key' => 'dry_clean_winter'],
            ['name' => 'Ủi đồ', 'unit' => 'món', 'price' => 15000, 'service_key' => 'steam_ao_dai'],
            ['name' => 'Giặt chăn mền', 'unit' => 'món', 'price' => 80000, 'service_key' => 'blanket_pillow'],
            ['name' => 'Giặt giày', 'unit' => 'đôi', 'price' => 60000, 'service_key' => 'sneaker_clean'],
        ];

        foreach ($pricings as $pricing) {
            $serviceKey = $pricing['service_key'];
            unset($pricing['service_key']);

            $service = $services->firstWhere('type', $serviceKey) ?? $services->random();
            $garment = $garments->isNotEmpty() ? $garments->random() : null;

            $pricing['service_id'] = $service ? $service->id : null;
            $pricing['garment_id'] = $garment ? $garment->id : null;
            $pricing['effective_date'] = now()->toDateString();

            Pricing::create($pricing);
        }
    }
}
