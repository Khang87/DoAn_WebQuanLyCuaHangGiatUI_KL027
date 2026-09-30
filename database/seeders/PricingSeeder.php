<?php

namespace Database\Seeders;

use App\Models\BangGia;
use App\Models\DichVu;
use App\Models\DonViTinh;
use App\Models\LoaiDoGiat;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Nạp bảng giá vào `BangGia` (DichVuID + LoaiDoGiatID + DonViTinhID + DonGia).
 *
 * Đơn vị tính của mỗi dịch vụ lấy từ ServiceSeeder; mỗi dịch vụ sinh một giá
 * theo loại đồ giặt mặc định của nó.
 */
class PricingSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Tên dịch vụ => [loại đồ giặt, đơn vị tính, đơn giá].
     *
     * @var array<string, array{0: string, 1: string, 2: float}>
     */
    private const PRICES = [
        'Giặt sấy lấy liền' => ['Quần áo', 'Kilogram', 25000],
        'Giặt sấy thông thường' => ['Quần áo', 'Kilogram', 15000],
        'Giặt xả gấp xếp ngăn nắp' => ['Quần áo', 'Kilogram', 18000],
        'Giặt hấp vest / suit' => ['Quần áo', 'Bộ', 120000],
        'Giặt khô áo dài / áo măng tô' => ['Quần áo', 'Cái', 90000],
        'Giặt hấp váy cưới / đầm dạ hội' => ['Quần áo', 'Cái', 250000],
        'Giặt hấp áo dài truyền thống' => ['Quần áo', 'Cái', 70000],
        'Giặt chăn mền / ruột gối' => ['Chăn ga gối đệm', 'Cái', 50000],
        'Giặt rèm cửa / topper nệm' => ['Chăn ga gối đệm', 'Kilogram', 35000],
        'Giặt thảm trải sàn' => ['Chăn ga gối đệm', 'Bộ', 45000],
        'Vệ sinh chuyên sâu giày Sneaker' => ['Giày dép', 'Đôi', 80000],
        'Tẩy trắng đế và vàng đế giày' => ['Giày dép', 'Đôi', 50000],
        'Bảo dưỡng túi xách da' => ['Đồ da & phụ kiện', 'Cái', 200000],
    ];

    public function run(): void
    {
        $garments = LoaiDoGiat::pluck('LoaiDoGiatID', 'TenLoaiDoGiat');
        $units = DonViTinh::pluck('DonViTinhID', 'TenDonViTinh');
        $services = DichVu::pluck('DichVuID', 'TenDichVu');

        foreach (self::PRICES as $serviceName => [$garmentName, $unitName, $price]) {
            $serviceId = $services[$serviceName] ?? null;
            $garmentId = $garments[$garmentName] ?? null;
            $unitId = $units[$unitName] ?? null;

            if (! $serviceId || ! $garmentId || ! $unitId) {
                continue;
            }

            BangGia::updateOrCreate(
                [
                    'DichVuID' => $serviceId,
                    'LoaiDoGiatID' => $garmentId,
                    'DonViTinhID' => $unitId,
                ],
                [
                    'DonGia' => $price,
                    'NgayApDung' => now()->subMonth()->format('Y-m-d'),
                    'NgayKetThuc' => null,
                    'TrangThai' => 'Hoạt động',
                ],
            );
        }
    }
}
