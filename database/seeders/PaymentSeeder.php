<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Models\DonHang;
use App\Models\ThanhToan;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Sinh giao dịch thanh toán cho các đơn đã quyết toán trong `ThanhToan`.
 *
 * `PhuongThuc` chỉ nhận 'Tiền mặt' hoặc 'Chuyển khoản', `TrangThai` nhận
 * 'Chờ thanh toán' / 'Thành công' / 'Thất bại' / 'Đã hoàn tiền'.
 */
class PaymentSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $orders = DonHang::whereIn('TrangThai', [
            OrderStatus::Delivered->value,
            OrderStatus::Paid->value,
            OrderStatus::Cancelled->value,
        ])->get();

        if ($orders->isEmpty()) {
            $this->command?->warn('Chưa có đơn hàng phù hợp để sinh thanh toán.');

            return;
        }

        $created = 0;

        foreach ($orders as $order) {
            if ($order->thanhToans()->exists()) {
                continue;
            }

            $isPaid = $order->TrangThai === OrderStatus::Paid->value;
            $isCancelled = $order->TrangThai === OrderStatus::Cancelled->value;
            $isCash = $order->DonHangID % 2 === 0;

            ThanhToan::create([
                'DonHangID' => $order->DonHangID,
                'SoTien' => max(1, (float) $order->ThanhTien),
                'PhuongThuc' => $isCash ? 'Tiền mặt' : 'Chuyển khoản',
                'MaGiaoDich' => $isPaid ? 'TXN'.str_pad((string) $order->DonHangID, 6, '0', STR_PAD_LEFT) : null,
                'ThoiGian' => $order->NgayTao?->copy()->addHours(random_int(2, 48)) ?? now(),
                'TrangThai' => match (true) {
                    $isPaid => 'Thành công',
                    $isCancelled => 'Đã hoàn tiền',
                    default => 'Chờ thanh toán',
                },
                'GhiChu' => null,
            ]);

            $created++;
        }

        $this->command?->info("Đã tạo {$created} giao dịch thanh toán.");
    }
}
