<?php

namespace Database\Seeders;

use App\Enums\BookingMethod;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\KhachHang;
use App\Models\NhanVien;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class BookingSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $customers = KhachHang::all();
        $staff = NhanVien::where('TrangThai', 'Hoạt động')->get();

        if ($customers->isEmpty()) {
            $this->command?->warn('Bảng KhachHang đang trống, bỏ qua seed đặt lịch.');

            return;
        }

        $addresses = [
            '140 Lê Trọng Tấn, Phường Tây Thạnh, Quận Tân Phú, TP.HCM',
            '54/12 Chế Lan Viên, Phường Tây Thạnh, Quận Tân Phú, TP.HCM',
            '102 Trường Chinh, Phường 12, Quận Tân Bình, TP.HCM',
            '215 Tân Sơn Nhì, Phường Tân Sơn Nhì, Quận Tân Phú, TP.HCM',
            '88 Bình Long, Phường Phú Thạnh, Quận Tân Phú, TP.HCM',
            '45 Nguyễn Văn Lượng, Phường Tây Thạnh, Quận Tân Phú, TP.HCM',
            '78/42 Lê Đức Thọ, Phường 15, Quận Tân Bình, TP.HCM',
            '300/14 Đỗ Thúc Tĩnh, Phường Tây Thạnh, Quận Tân Phú, TP.HCM',
            '126 Trường Đình, Phường 12, Quận Tân Bình, TP.HCM',
            '999 Lê Văn Sỹ, Phường 14, Quận 3, TP.HCM',
        ];

        $cancelReasons = [
            'Khách đổi lịch bận',
            'Cửa hàng quá tải khung giờ',
            'Địa chỉ giao hàng không chính xác',
            'Khách hủy chuyến đi cuối tuần',
            'Thời gian không phù hợp với giờ mở cửa',
        ];

        $methods = BookingMethod::values();

        $statuses = [
            BookingStatus::Pending->value,
            BookingStatus::Pending->value,
            BookingStatus::Pending->value,
            BookingStatus::Pending->value,
            BookingStatus::Confirmed->value,
            BookingStatus::Confirmed->value,
            BookingStatus::Confirmed->value,
            BookingStatus::Cancelled->value,
            BookingStatus::Cancelled->value,
            BookingStatus::Cancelled->value,
        ];

        for ($i = 1; $i <= 10; $i++) {
            $status = $statuses[$i - 1];
            $customer = $customers[($i - 1) % $customers->count()];

            $scheduledDate = ($i <= 4)
                ? Carbon::today()
                : Carbon::now()->addDays(random_int(0, 5));
            $scheduledTime = sprintf('%02d:00:00', random_int(9, 17));

            $notes = $status === BookingStatus::Cancelled->value
                ? $cancelReasons[array_rand($cancelReasons)]
                : null;

            Booking::create([
                'KhachHangID' => $customer->KhachHangID,
                'NhanVienID' => $staff->isNotEmpty() ? $staff->random()->NhanVienID : null,
                'HinhThucNhanDo' => $methods[array_rand($methods)],
                'NgayHen' => $scheduledDate->format('Y-m-d'),
                'GioHen' => $scheduledTime,
                'DiaChiNhan' => $addresses[($i - 1) % count($addresses)],
                'GhiChu' => $notes,
                'TrangThai' => $status,
            ]);
        }
    }
}
