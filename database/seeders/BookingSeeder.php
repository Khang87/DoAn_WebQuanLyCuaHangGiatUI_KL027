<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BookingSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $customers = Customer::all();
        $staff = User::whereIn('role', ['manager', 'staff'])->get();

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

        $methods = ['nhan_do', 'giao_do'];

        $statuses = ['pending', 'pending', 'pending', 'pending', 'confirmed', 'confirmed', 'confirmed', 'cancelled', 'cancelled', 'cancelled'];

        for ($i = 1; $i <= 10; $i++) {
            $status = $statuses[$i - 1];
            $customer = $customers[($i - 1) % $customers->count()];

            $scheduledDate = ($i <= 4)
                ? Carbon::today()->format('Y-m-d')
                : Carbon::now()->addDays(rand(0, 5))->format('Y-m-d');
            $scheduledTime = sprintf('%02d:00:00', rand(9, 17));

            $notes = '';
            if ($status === 'cancelled') {
                $notes = $cancelReasons[array_rand($cancelReasons)];
            }

            $staffId = $staff->isNotEmpty() ? $staff->random()->id : null;

            Booking::create([
                'customer_id' => $customer->id,
                'staff_id' => $staffId,
                'method' => $methods[array_rand($methods)],
                'scheduled_date' => $scheduledDate,
                'scheduled_time' => $scheduledTime,
                'address' => $addresses[($i - 1) % count($addresses)],
                'notes' => $notes,
                'status' => $status,
                'created_at' => Carbon::now()->subDays(rand(0, 30)),
                'updated_at' => Carbon::now()->subDays(rand(0, 30)),
            ]);
        }
    }
}
