<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BookingSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $customers = Customer::all();
        $services = Service::all();
        $statuses = ['pending', 'confirmed', 'completed', 'cancelled'];
        $deliveryMethods = ['pickup', 'dropoff'];
        $garmentTypes = ['Áo dài', 'Váy cưới', 'Áo vest', 'Chăn ga', 'Áo sơ mi', 'Quần tây', 'Áo khoác'];

        for ($i = 1; $i <= 10; $i++) {
            Booking::create([
                'customer_id' => $customers->random()->id,
                'service_id' => $services->random()->id,
                'garment_type' => $garmentTypes[array_rand($garmentTypes)],
                'quantity' => rand(1, 5),
                'delivery_method' => $deliveryMethods[array_rand($deliveryMethods)],
                'address' => 'Địa chỉ pickup #' . $i,
                'pickup_date' => Carbon::now()->addDays(rand(1, 7)),
                'pickup_time' => Carbon::now()->addHours(rand(9, 17)),
                'notes' => 'Ghi chú booking #' . $i,
                'status' => $statuses[array_rand($statuses)],
                'created_at' => Carbon::now()->subDays(rand(0, 30)),
                'updated_at' => Carbon::now()->subDays(rand(0, 30)),
            ]);
        }
    }
}
