<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Models\DonHang;
use App\Models\GiaoNhan;
use App\Models\KhachHang;
use App\Models\NhanVien;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Sinh phiếu giao nhận trong `GiaoNhan` cho từng đơn hàng.
 *
 * `HinhThuc` nhận 'Tại cửa hàng'/'Tại nhà', `LoaiGiaoNhan` nhận
 * 'NHAN_DO'/'GIAO_DO' và `TrangThai` nhận 'Chờ thực hiện'/'Đang thực hiện'/
 * 'Hoàn thành'/'Đã hủy'.
 */
class DeliverySeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $orders = DonHang::with(['giaoNhans', 'khachHang'])->get();

        if ($orders->isEmpty()) {
            $this->command?->warn('Chưa có đơn hàng nào để sinh phiếu giao nhận.');

            return;
        }

        $staff = NhanVien::where('TrangThai', 'Hoạt động')->get();
        $created = 0;

        foreach ($orders as $order) {
            if ($order->giaoNhans->isNotEmpty()) {
                continue;
            }

            $customer = $order->khachHang ?? KhachHang::find($order->KhachHangID);
            $deliverToHome = ($order->DonHangID % 3) !== 0;
            $status = match ($order->TrangThai) {
                OrderStatus::Cancelled->value => 'Đã hủy',
                OrderStatus::Washed->value, OrderStatus::Delivering->value => 'Đang thực hiện',
                OrderStatus::Delivered->value, OrderStatus::Paid->value => 'Hoàn thành',
                default => 'Chờ thực hiện',
            };

            GiaoNhan::create([
                'DonHangID' => $order->DonHangID,
                'NhanVienID' => $order->NhanVienID ?: ($staff->isNotEmpty() ? $staff->random()->NhanVienID : null),
                'LoaiGiaoNhan' => $deliverToHome ? 'GIAO_DO' : 'NHAN_DO',
                'HinhThuc' => $deliverToHome ? 'Tại nhà' : 'Tại cửa hàng',
                'DiaChi' => $customer?->DiaChi,
                'ThoiGianDuKien' => ($order->NgayTao ?? now())->copy()->addDay()->setTime(random_int(9, 17), 0),
                'ThoiGianThucTe' => in_array($status, ['Hoàn thành'], true)
                    ? ($order->NgayTao ?? now())->copy()->addDay()->setTime(random_int(9, 18), 0)
                    : null,
                'PhiGiaoHang' => (float) $order->PhiGiaoHang,
                'TrangThai' => $status,
                'GhiChu' => null,
            ]);

            $created++;
        }

        $this->command?->info("Đã tạo {$created} phiếu giao nhận.");
    }
}
