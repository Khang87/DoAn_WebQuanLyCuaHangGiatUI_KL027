<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Delivery;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DeliverySeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $customers = Customer::all();
        $statuses = ['pending', 'confirmed', 'completed', 'cancelled'];
        $methods = ['pickup', 'dropoff'];

        for ($i = 1; $i <= 10; $i++) {
            Delivery::create([
                'customer_id' => $customers->random()->id,
                'method' => $methods[array_rand($methods)],
                'address' => 'Địa chỉ giao hàng #' . $i,
                'pickup_date' => Carbon::now()->addDays(rand(1, 5)),
                'pickup_time' => Carbon::now()->addHours(rand(9, 17)),
                'status' => $statuses[array_rand($statuses)],
                'created_at' => Carbon::now()->subDays(rand(0, 30)),
                'updated_at' => Carbon::now()->subDays(rand(0, 30)),
            ]);
        }
    }
}
