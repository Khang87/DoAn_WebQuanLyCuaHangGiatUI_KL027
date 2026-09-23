<?php

namespace Database\Seeders;

use App\Models\Coupon;
use App\Models\Promotion;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CouponSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $promotions = Promotion::all();
        $couponCodes = ['GIAOHANG', 'GIAM20', 'MUANGIA', 'FREESHIP', 'NEWCUSTOMER', 'SUMMER2024'];
        $discountTypes = ['percent', 'fixed', 'free_shipping'];

        foreach ($couponCodes as $index => $code) {
            Coupon::create([
                'code' => $code,
                'promotion_id' => $promotions->random()->id,
                'discount_type' => $discountTypes[$index % 3],
                'discount_value' => $discountTypes[$index % 3] === 'percent' ? rand(10, 30) : rand(20000, 100000),
                'max_uses' => rand(50, 200),
                'used_count' => rand(0, 30),
                'expires_at' => now()->addMonths(rand(1, 3)),
                'status' => rand(0, 1) ? 'active' : 'inactive',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
