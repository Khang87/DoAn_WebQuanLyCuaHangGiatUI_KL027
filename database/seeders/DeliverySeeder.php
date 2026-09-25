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

        $addresses = [
            '140 Lê Trọng Tấn, Phường Tây Thạnh, Quận Tân Phú, TP.HCM',
            '54/12 Chế Lan Viên, Phường Tây Thạnh, Quận Tân Phú, TP.HCM',
            '102 Trường Chinh, Phường 12, Quận Tân Bình, TP.HCM',
            '215 Tân Sơn Nhì, Phường Tân Sơn Nhì, Quận Tân Phú, TP.HCM',
            '88 Bình Long, Phường Phú Thạnh, Quận Tân Phú, TP.HCM',
            '45 Nguyễn Văn Lượng, Phường Tây Thạnh, Quận Tân Phú, TP.HCM',
            '78/42 Lê Đức Thọ, Phường 15, Quận Tân Bình, TP.HCM',
            '300/14 Đỗ Thúc Tĩnh, Phường Tây Thạnh, Quận Tân Phú, TP.HCM',
            '126 Trường Đinh, Phường 12, Quận Tân Bình, TP.HCM',
            '999 Lê Văn Sỹ, Phường 14, Quận 3, TP.HCM',
        ];

        $cancelReasons = [
            'Khách đổi lịch bận',
            'Cửa hàng quá tải khung giờ',
            'Địa chỉ giao hàng không chính xác',
            'Khách hủy chuyến đi cuối tuần',
            'Thời gian không phù hợp với giờ mở cửa',
        ];

        $methods = ['pickup', 'dropoff', 'home_pickup'];
        $statuses = ['pending', 'picking', 'delivering', 'completed', 'cancelled'];

        $methodStatusMap = [
            'pickup' => ['pending', 'picking', 'completed', 'cancelled'],
            'dropoff' => ['pending', 'delivering', 'completed', 'cancelled'],
            'home_pickup' => ['pending', 'picking', 'delivering', 'completed', 'cancelled'],
        ];

        for ($i = 1; $i <= 15; $i++) {
            $method = $methods[($i - 1) % count($methods)];
            $availableStatuses = $methodStatusMap[$method];
            $status = $availableStatuses[($i - 1) % count($availableStatuses)];
            $customer = $customers[($i - 1) % $customers->count()];

            $pickupDate = ($i <= 4)
                ? Carbon::today()->format('Y-m-d')
                : Carbon::now()->addDays(rand(0, 5))->format('Y-m-d');
            $pickupTime = sprintf('%02d:00:00', rand(9, 17));

            $notes = '';
            if ($status === 'cancelled') {
                $notes = $cancelReasons[array_rand($cancelReasons)];
            }

            Delivery::create([
                'code' => 'GH' . str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                'customer_id' => $customer->id,
                'method' => $method,
                'address' => $addresses[($i - 1) % count($addresses)],
                'pickup_date' => $pickupDate,
                'pickup_time' => $pickupTime,
                'notes' => $notes,
                'status' => $status,
                'created_at' => Carbon::now()->subDays(rand(0, 30)),
                'updated_at' => Carbon::now()->subDays(rand(0, 30)),
            ]);
        }
    }
}
