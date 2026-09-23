<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\OrderItem;
use App\Models\Order;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $customers = Customer::all();
        $services = Service::all();
        $statuses = ['pending', 'processing', 'washing', 'washed', 'delivering', 'completed', 'cancelled'];
        $weights = ['3kg', '5kg', '8kg', '2kg', '10kg', '4kg', '6kg', '7kg', '1kg', '15kg'];
        $items = [
            'đồ thường + 3 áo trắng', '2 áo sơ mi + quần tây', 'váy cưới',
            'chăn ga + gối', '5 đôi giày', 'áo khoác + sơ mi', 'quần jeans + áo thun',
            'đồ trẻ em', 'vest công sở', 'áo dài lễ phục',
        ];

        $orderCount = 25;

        for ($i = 1; $i <= $orderCount; $i++) {
            $customer = $customers->random();
            $service = $services->random();
            $status = $statuses[array_rand($statuses)];
            $createdAt = Carbon::now()->subDays(rand(0, 60))->subHours(rand(0, 23));

            $order = Order::create([
                'code' => 'DH' . str_pad($i, 3, '0', STR_PAD_LEFT),
                'customer_id' => $customer->id,
                'service_id' => $service->id,
                'weight_kg' => $weights[array_rand($weights)],
                'quantity_items' => $items[array_rand($items)],
                'total_amount' => rand(100000, 500000),
                'status' => $status,
                'notes' => 'Ghi chú đơn hàng #' . $i,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            if ($status === 'completed' && rand(0, 1)) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'service_id' => $service->id,
                    'item_name' => 'Mặt hàng 1',
                    'item_type' => 'garment',
                    'price' => $service->price,
                    'quantity' => rand(1, 5),
                    'subtotal' => $service->price * rand(1, 5),
                    'notes' => null,
                ]);
            }
        }
    }
}
