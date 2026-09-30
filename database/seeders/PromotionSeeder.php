<?php

namespace Database\Seeders;

use App\Models\KhuyenMai;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Nạp khuyến mãi vào bảng `KhuyenMai`.
 *
 * `LoaiKhuyenMai` chỉ nhận hai giá trị 'Phần trăm' hoặc 'Tiền mặt' theo ràng
 * buộc `KhuyenMai_LoaiKhuyenMai_check`.
 */
class PromotionSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $promotions = [
            [
                'MaKhuyenMai' => 'KM20',
                'TenKhuyenMai' => 'Giảm 20% đơn đầu',
                'LoaiKhuyenMai' => 'Phần trăm',
                'GiaTriGiam' => 20,
                'GiaTriDonToiThieu' => 0,
                'MucGiamToiDa' => 100000,
                'SoLuongSuDung' => 100,
                'DieuKienApDung' => 'Áp dụng cho đơn hàng đầu tiên của khách.',
                'NgayBatDau' => now()->subMonth()->format('Y-m-d'),
                'NgayKetThuc' => now()->addMonths(2)->format('Y-m-d'),
                'TrangThai' => 'Hoạt động',
            ],
            [
                'MaKhuyenMai' => 'KM50K',
                'TenKhuyenMai' => 'Giảm 50K đơn từ 500K',
                'LoaiKhuyenMai' => 'Tiền mặt',
                'GiaTriGiam' => 50000,
                'GiaTriDonToiThieu' => 500000,
                'MucGiamToiDa' => null,
                'SoLuongSuDung' => 200,
                'DieuKienApDung' => 'Đơn hàng từ 500.000đ trở lên.',
                'NgayBatDau' => now()->subMonth()->format('Y-m-d'),
                'NgayKetThuc' => now()->addMonth()->format('Y-m-d'),
                'TrangThai' => 'Hoạt động',
            ],
            [
                'MaKhuyenMai' => 'FREESHIP',
                'TenKhuyenMai' => 'Miễn phí giao hàng',
                'LoaiKhuyenMai' => 'Tiền mặt',
                'GiaTriGiam' => 30000,
                'GiaTriDonToiThieu' => 200000,
                'MucGiamToiDa' => null,
                'SoLuongSuDung' => null,
                'DieuKienApDung' => 'Giảm phí giao hàng cho đơn từ 200.000đ.',
                'NgayBatDau' => now()->subMonth()->format('Y-m-d'),
                'NgayKetThuc' => now()->addMonths(3)->format('Y-m-d'),
                'TrangThai' => 'Hoạt động',
            ],
            [
                'MaKhuyenMai' => 'KMDEC',
                'TenKhuyenMai' => 'Ưu đãi cuối mùa',
                'LoaiKhuyenMai' => 'Phần trăm',
                'GiaTriGiam' => 10,
                'GiaTriDonToiThieu' => 0,
                'MucGiamToiDa' => 50000,
                'SoLuongSuDung' => null,
                'DieuKienApDung' => null,
                'NgayBatDau' => now()->subWeeks(2)->format('Y-m-d'),
                'NgayKetThuc' => now()->subDays(1)->format('Y-m-d'),
                'TrangThai' => 'Hết hạn',
            ],
        ];

        foreach ($promotions as $promotion) {
            KhuyenMai::updateOrCreate(
                ['MaKhuyenMai' => $promotion['MaKhuyenMai']],
                $promotion,
            );
        }
    }
}
