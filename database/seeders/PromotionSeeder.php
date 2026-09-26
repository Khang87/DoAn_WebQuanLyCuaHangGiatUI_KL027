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
                'discount_type' => 'percentage',
                'discount_value' => 20,
                'min_order_amount' => 0,
                'max_discount' => null,
                'usage_limit' => null,
                'conditions' => null,
                'starts_at' => Carbon::now()->subMonth(),
                'expires_at' => Carbon::now()->addMonths(2),
                'status' => 'active',
            ],
            [
                'name' => 'Tặng 50K Đơn 500K',
                'code' => 'PROMO500',
                'discount_type' => 'fixed',
                'discount_value' => 50000,
                'min_order_amount' => 500000,
                'max_discount' => null,
                'usage_limit' => null,
                'conditions' => null,
                'starts_at' => Carbon::now()->subMonth(),
                'expires_at' => Carbon::now()->addMonths(1),
                'status' => 'active',
            ],
            [
                'name' => 'Miễn Phí Giao Hàng',
                'code' => 'SHIPFREE',
                'discount_type' => 'fixed',
                'discount_value' => 0,
                'min_order_amount' => 0,
                'max_discount' => null,
                'usage_limit' => null,
                'conditions' => null,
                'starts_at' => Carbon::now()->subMonth(),
                'expires_at' => Carbon::now()->addMonths(3),
                'status' => 'active',
            ],
        ];

        foreach ($promotions as $promotion) {
            Promotion::create($promotion);
        }
    }
}
