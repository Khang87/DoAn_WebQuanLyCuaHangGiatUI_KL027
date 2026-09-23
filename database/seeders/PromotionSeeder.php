<?php

namespace Database\Seeders;

use App\Models\Promotion;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PromotionSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $promotions = [
            [
                'name' => 'Giảm 20% Đơn Đầu',
                'code' => 'PROMO20',
                'discount' => '20%',
                'expires_at' => Carbon::now()->addMonths(2),
                'status' => 'active',
            ],
            [
                'name' => 'Tặng 50K Đơn 500K',
                'code' => 'PROMO500',
                'discount' => '50000',
                'expires_at' => Carbon::now()->addMonths(1),
                'status' => 'active',
            ],
            [
                'name' => 'Miễn Phí Giao Hàng',
                'code' => 'SHIPFREE',
                'discount' => 'free_shipping',
                'expires_at' => Carbon::now()->addMonths(3),
                'status' => 'active',
            ],
        ];

        foreach ($promotions as $promotion) {
            Promotion::create($promotion);
        }
    }
}
