<?php

namespace Database\Seeders;

use App\Models\DonHang;
use App\Models\TaiKhoan;
use App\Models\ThongBao;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Sinh thông báo trong `ThongBao` cho các tài khoản quản trị.
 *
 * `ThongBao` bắt buộc có `TaiKhoanID`; `DonHangID` là tuỳ chọn.
 */
class NotificationSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        if (ThongBao::query()->exists()) {
            $this->command?->info('Bảng ThongBao đã có dữ liệu, bỏ qua seed thông báo.');

            return;
        }

        $recipients = TaiKhoan::query()
            ->whereNotNull('NhanVienID')
            ->where('TrangThai', 'Hoạt động')
            ->get();

        if ($recipients->isEmpty()) {
            $this->command?->warn('Chưa có tài khoản nhân viên để gửi thông báo.');

            return;
        }

        $orders = DonHang::orderByDesc('DonHangID')->limit(5)->get();

        $notifications = [
            ['type' => 'order', 'title' => 'Đơn hàng mới', 'content' => 'Có đơn hàng mới cần xử lý.', 'order' => 0, 'hours' => 1, 'read' => false],
            ['type' => 'booking', 'title' => 'Lịch hẹn mới', 'content' => 'Khách hàng vừa đặt lịch giặt.', 'order' => null, 'hours' => 2, 'read' => false],
            ['type' => 'payment', 'title' => 'Thanh toán thành công', 'content' => 'Một giao dịch thanh toán đã được xác nhận.', 'order' => 1, 'hours' => 5, 'read' => true],
            ['type' => 'promotion', 'title' => 'Khuyến mãi sắp hết hạn', 'content' => 'Chương trình giảm giá sắp hết hạn trong 5 ngày.', 'order' => null, 'hours' => 8, 'read' => false],
            ['type' => 'order', 'title' => 'Đơn hàng hoàn thành', 'content' => 'Đơn hàng đã được giặt xong và sẵn sàng giao.', 'order' => 2, 'hours' => 26, 'read' => true],
        ];

        $created = 0;

        foreach ($recipients as $index => $recipient) {
            foreach ($notifications as $notification) {
                ThongBao::create([
                    'TaiKhoanID' => $recipient->getKey(),
                    'DonHangID' => $notification['order'] !== null
                        ? $orders->get($notification['order'])?->DonHangID
                        : null,
                    'LoaiThongBao' => $notification['type'],
                    'TieuDe' => $notification['title'],
                    'NoiDung' => $notification['content'],
                    'ThoiGianGui' => now()->subHours($notification['hours'] + $index),
                    'DaDoc' => $notification['read'],
                ]);

                $created++;
            }
        }

        $this->command?->info("Đã tạo {$created} thông báo.");
    }
}
