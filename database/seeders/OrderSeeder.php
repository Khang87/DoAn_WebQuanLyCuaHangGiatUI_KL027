<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Garment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Promotion;
use App\Models\Service;
use App\Models\User;
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
        $garments = Garment::all();
        $promotions = Promotion::all();
        $employees = User::whereIn('role', ['manager', 'staff'])->get();

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

                $employeeId = $employees->isNotEmpty() ? $employees->random()->id : null;

                $pointsUsed = 0;
                $pointsDiscount = 0;
                if ($customer->points > 0 && rand(0, 2) === 0) {
                    $pointsUsed = min(rand(1, 100), $customer->points);
                    $pointsDiscount = $pointsUsed * 1000;
                }

                // 1. Tính giảm giá khuyến mãi
                $promotionId = null;
                $discountByPromotion = 0;

                if ($promotions->isNotEmpty() && rand(0, 3) === 0) {
                    $promotion = $promotions->random();

                    if ($promotion->discount_type === 'percentage') {
                        $discountByPromotion = $totalAmount * ($promotion->discount_value / 100);
                    } elseif ($promotion->discount_type === 'fixed') {
                        $discountByPromotion = min($promotion->discount_value, $totalAmount);
                    }

                    if ($discountByPromotion > 0) {
                        $promotionId = $promotion->id;
                    }
                }

                $discountByPromotion = round(min($discountByPromotion, $totalAmount), 2);

                // Tiền giảm do điểm không được vượt quá số tiền còn lại.
                $discountByPoints = round(min($pointsDiscount, max(0, $totalAmount - $discountByPromotion)), 2);
                $pointsUsed = (int) floor($discountByPoints / 1000);

                $finalAmount = max(0, $totalAmount - $discountByPromotion - $discountByPoints);

                // 2. Tạo Đơn Hàng
                $order = Order::create([
                    'code' => 'DH' . str_pad($customer->id . $i, 6, '0', STR_PAD_LEFT),
                    'customer_id' => $customer->id,
                    'employee_id' => $employeeId,
                    'service_id' => $service->id,
                    'promotion_id' => $promotionId,
                    'subtotal' => round($totalAmount, 2),
                    'discount_by_promotion' => $discountByPromotion,
                    'points_used' => $pointsUsed,
                    'discount_by_points' => $discountByPoints,
                    'weight_kg' => $isKgBased ? ($quantity . 'kg') : null,
                    'quantity_items' => $isKgBased ? null : ($quantity . ' ' . ($service->unit ?? 'món')),
                    'total_amount' => $finalAmount,
                    'status' => $status,
                    'notes' => $itemNotes[array_rand($itemNotes)],
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);

                // 3. Tạo Chi Tiết Đơn Hàng
                $garment = $garments->isNotEmpty() ? $garments->random() : null;
                $itemWeight = $isKgBased ? (string) $quantity : null;

                OrderItem::create([
                    'order_id' => $order->id,
                    'service_id' => $service->id,
                    'garment_id' => $garment ? $garment->id : null,
                    'item_name' => $service->name,
                    'item_type' => 'garment',
                    'price' => $price,
                    'quantity' => $quantity,
                    'weight' => $itemWeight,
                    'subtotal' => $finalAmount,
                    'notes' => null,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);

                // 4. Tạo Thanh Toán
                if ($status !== 'cancelled') {
                    $paymentStatus = in_array($status, ['completed', 'washed']) ? 'paid' : 'pending';
                    $paidAt = null;
                    $transactionCode = null;

                    if ($paymentStatus === 'paid') {
                        $paidAt = $createdAt->copy()->addDays(rand(0, 3));
                        $transactionCode = 'TXN' . str_pad($order->id, 6, '0', STR_PAD_LEFT);
                    }

                    Payment::create([
                        'order_id' => $order->id,
                        'amount' => $finalAmount,
                        'method' => $methods[array_rand($methods)],
                        'paid_at' => $paidAt,
                        'status' => $paymentStatus,
                        'transaction_code' => $transactionCode,
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ]);
                }

                // 5. Tạo Giao Nhận
                if (in_array($status, ['processing', 'washing', 'washed', 'delivering', 'completed'])) {
                    $deliveryStatus = ($status === 'completed') ? 'completed' : 'delivering';
                    $deliveryMethod = ['nhan_do', 'giao_do'][array_rand(['nhan_do', 'giao_do'])];

                    Delivery::create([
                        'order_id' => $order->id,
                        'customer_id' => $customer->id,
                        'employee_id' => $employeeId,
                        'code' => 'GH' . str_pad($order->id, 4, '0', STR_PAD_LEFT),
                        'method' => $deliveryMethod,
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
