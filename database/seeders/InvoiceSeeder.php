<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Models\DonHang;
use App\Models\HoaDon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Sinh hóa đơn cho từng đơn hàng trong `HoaDon`.
 *
 * `HoaDon.TrangThai` lưu tiếng Việt ('Chưa thanh toán' / 'Đã thanh toán' /
 * 'Đã hủy') theo ràng buộc `HoaDon_TrangThai_check`.
 */
class InvoiceSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $orders = DonHang::with('hoaDons')->get();

        if ($orders->isEmpty()) {
            $this->command?->warn('Chưa có đơn hàng nào để sinh hóa đơn.');

            return;
        }

        $sequence = (int) (HoaDon::max('HoaDonID') ?? 0);
        $created = 0;

        foreach ($orders as $order) {
            if ($order->hoaDons->isNotEmpty()) {
                continue;
            }

            $sequence++;

            $status = match (true) {
                $order->TrangThai === OrderStatus::Paid->value => 'Đã thanh toán',
                $order->TrangThai === OrderStatus::Cancelled->value => 'Đã hủy',
                default => 'Chưa thanh toán',
            };

            HoaDon::create([
                'MaHoaDon' => 'HD'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT),
                'DonHangID' => $order->DonHangID,
                'TongTien' => (float) $order->TongTien,
                'GiamGia' => (float) $order->TienGiamKhuyenMai + (float) $order->TienGiamDoDiem,
                'PhiGiaoHang' => (float) $order->PhiGiaoHang,
                'ThanhTien' => (float) $order->ThanhTien,
                'NgayLap' => $order->NgayTao ?? now(),
                'TrangThai' => $status,
            ]);

            $created++;
        }

        $this->command?->info("Đã tạo {$created} hóa đơn.");
    }
}
