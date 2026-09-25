<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Promotion;
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
        $promotions = Promotion::all();

        if ($customers->isEmpty() || $services->isEmpty()) {
            $this->command->error('LỖI: Cần tạo dữ liệu Customers và Services trước!');
            return;
        }

        $statuses = ['pending', 'processing', 'washing', 'washed', 'delivering', 'completed', 'cancelled'];
        $methods = ['cash', 'bank_transfer', 'e_wallet'];
        $itemNotes = [
            'Giặt riêng áo trắng, không tẩy mạnh.',
            'Váy cưới vải voan mỏng, yêu cầu giặt hấp nhẹ nhàng.',
            'Chăn bông có vết ố trà, xử lý kỹ vết bẩn.',
            'Ủi gấp nếp quần tây phẳng, giao trước 17h.',
            'Giày Sneaker vải lưới, không phơi dưới nắng.',
            'Áo len mỏng, giặt nước lạnh.',
            'Đồ trẻ em nhạy cảm da, dùng xà phòng không hương liệu.',
        ];

        foreach ($customers as $customer) {
            $orderCount = rand(2, 4);

            for ($i = 0; $i < $orderCount; $i++) {
                $service = $services->random();

                $isKgBased = strtolower($service->unit ?? '') === 'kg';
                $quantity = $isKgBased ? rand(2, 5) : rand(1, 4);
                $price = $service->price ?? 20000;
                $totalAmount = $quantity * $price;

                $status = $statuses[array_rand($statuses)];
                $createdAt = Carbon::now()->subDays(rand(1, 30))->subHours(rand(0, 23));

                // 1. Tính giảm giá khuyến mãi (ngẫu nhiên áp dụng)
                $promotionId = null;
                $discountAmount = 0;

                if ($promotions->isNotEmpty() && rand(0, 3) === 0) {
                    $promotion = $promotions->random();
                    $discount = $promotion->discount;

                    if (str_ends_with($discount, '%')) {
                        $percent = (float) rtrim($discount, '%');
                        $discountAmount = $totalAmount * ($percent / 100);
                    } elseif ($discount !== 'free_shipping') {
                        $discountAmount = min((float) $discount, $totalAmount);
                    }

                    if ($discountAmount > 0) {
                        $promotionId = $promotion->id;
                    }
                }

                // Đảm bảo tổng tiền sau giảm giá không âm
                $finalAmount = max(0, $totalAmount - $discountAmount);

                // 2. Tạo Đơn Hàng
                $order = Order::create([
                    'code' => 'DH' . str_pad($customer->id . $i, 6, '0', STR_PAD_LEFT),
                    'customer_id' => $customer->id,
                    'service_id' => $service->id,
                    'promotion_id' => $promotionId,
                    'discount_amount' => $discountAmount,
                    'weight_kg' => $isKgBased ? ($quantity . 'kg') : null,
                    'quantity_items' => $isKgBased ? null : ($quantity . ' ' . ($service->unit ?? 'món')),
                    'total_amount' => $finalAmount,
                    'status' => $status,
                    'notes' => $itemNotes[array_rand($itemNotes)],
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);

                // 3. Tạo Chi Tiết Đơn Hàng (1 item duy nhất)
                OrderItem::create([
                    'order_id' => $order->id,
                    'service_id' => $service->id,
                    'item_name' => $service->name,
                    'item_type' => 'garment',
                    'price' => $price,
                    'quantity' => $quantity,
                    'subtotal' => $finalAmount,
                    'notes' => null,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);

                // 4. Đồng bộ tạo Thanh Toán (trừ đơn hủy)
                if ($status !== 'cancelled') {
                    $paymentStatus = in_array($status, ['completed', 'washed']) ? 'paid' : 'pending';

                    Payment::create([
                        'order_id' => $order->id,
                        'amount' => $finalAmount,
                        'method' => $methods[array_rand($methods)],
                        'status' => $paymentStatus,
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ]);
                }

                // 5. Đồng bộ tạo Giao Nhận (đơn đang xử lý, đã giặt, đang giao, hoàn thành)
                if (in_array($status, ['processing', 'washing', 'washed', 'delivering', 'completed'])) {
                    $deliveryStatus = ($status === 'completed') ? 'completed' : 'delivering';

                    Delivery::create([
                        'order_id' => $order->id,
                        'customer_id' => $customer->id,
                        'method' => 'delivery',
                        'address' => $customer->address ?? 'Địa chỉ mặc định',
                        'pickup_date' => $createdAt->addDay()->toDateString(),
                        'pickup_time' => sprintf('%02d:00:00', rand(9, 17)),
                        'notes' => null,
                        'status' => $deliveryStatus,
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ]);
                }
            }
        }
    }
}
