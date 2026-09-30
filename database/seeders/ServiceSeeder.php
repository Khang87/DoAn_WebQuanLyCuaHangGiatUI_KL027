<?php

namespace Database\Seeders;

use App\Models\DichVu;
use App\Models\DonViTinh;
use App\Models\LoaiDichVu;
use App\Models\LoaiDoGiat;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Nạp danh mục dịch vụ giặt: `LoaiDichVu`, `LoaiDoGiat`, `DonViTinh` và `DichVu`.
 *
 * `BangGia` (PricingSeeder) và `ChiTietDonHang` (OrderSeeder) đều cần ba bảng
 * danh mục này làm khóa ngoại nên seeder này phải chạy trước.
 */
class ServiceSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * @var array<string, string> tên danh mục => mô tả
     */
    private const CATEGORIES = [
        'Giặt sấy quần áo' => 'Giặt sấy tiêu chuẩn cho quần áo hằng ngày.',
        'Giặt hấp cao cấp' => 'Giặt hấp chuyên sâu cho vest, áo dài và đồ cao cấp.',
        'Giặt chăn ga nệm thảm' => 'Giặt chăn mền, nệm, rèm và thảm trải sàn.',
        'Chăm sóc giày & phụ kiện' => 'Vệ sinh chuyên sâu giày, túi xách và phụ kiện da.',
    ];

    /**
     * @var array<string, string> tên loại đồ giặt => mô tả
     */
    private const GARMENT_TYPES = [
        'Quần áo' => 'Quần áo thường, sơ mi, đồng phục.',
        'Chăn ga gối đệm' => 'Chăn, gối, nệm, rèm cửa.',
        'Giày dép' => 'Giày thể thao, giày da, dép.',
        'Đồ da & phụ kiện' => 'Túi xách, ví, thắt lưng da.',
    ];

    /**
     * @var array<string, string> tên đơn vị tính => ký hiệu
     */
    private const UNITS = [
        'Kilogram' => 'KG',
        'Cái' => 'CAI',
        'Đôi' => 'DOI',
        'Bộ' => 'BO',
    ];

    /**
     * Mỗi dịch vụ gắn với một loại đồ giặt và một đơn vị tính để PricingSeeder
     * sinh bảng giá đúng nghĩa.
     *
     * @var list<array{category: string, name: string, garment: string, unit: string, price: float, hours: int, description: string}>
     */
    private const SERVICES = [
        ['category' => 'Giặt sấy quần áo', 'name' => 'Giặt sấy lấy liền', 'garment' => 'Quần áo', 'unit' => 'Kilogram', 'price' => 25000, 'hours' => 4, 'description' => 'Dịch vụ giặt sấy nhanh, lấy đồ trong 4 giờ.'],
        ['category' => 'Giặt sấy quần áo', 'name' => 'Giặt sấy thông thường', 'garment' => 'Quần áo', 'unit' => 'Kilogram', 'price' => 15000, 'hours' => 12, 'description' => 'Giặt sấy tiêu chuẩn, thời gian 12 giờ.'],
        ['category' => 'Giặt sấy quần áo', 'name' => 'Giặt xả gấp xếp ngăn nắp', 'garment' => 'Quần áo', 'unit' => 'Kilogram', 'price' => 18000, 'hours' => 12, 'description' => 'Giặt xả và gấp xếp sẵn sàng sử dụng.'],
        ['category' => 'Giặt hấp cao cấp', 'name' => 'Giặt hấp vest / suit', 'garment' => 'Quần áo', 'unit' => 'Bộ', 'price' => 120000, 'hours' => 24, 'description' => 'Giặt hấp chuyên nghiệp cho vest, suit công sở.'],
        ['category' => 'Giặt hấp cao cấp', 'name' => 'Giặt khô áo dài / áo măng tô', 'garment' => 'Quần áo', 'unit' => 'Cái', 'price' => 90000, 'hours' => 24, 'description' => 'Giặt khô chuyên dụng cho áo dài, áo măng tô.'],
        ['category' => 'Giặt hấp cao cấp', 'name' => 'Giặt hấp váy cưới / đầm dạ hội', 'garment' => 'Quần áo', 'unit' => 'Cái', 'price' => 250000, 'hours' => 48, 'description' => 'Giặt hấp tinh tế cho váy cưới, đầm dạ hội cao cấp.'],
        ['category' => 'Giặt hấp cao cấp', 'name' => 'Giặt hấp áo dài truyền thống', 'garment' => 'Quần áo', 'unit' => 'Cái', 'price' => 70000, 'hours' => 24, 'description' => 'Giặt hấp bảo quản áo dài truyền thống.'],
        ['category' => 'Giặt chăn ga nệm thảm', 'name' => 'Giặt chăn mền / ruột gối', 'garment' => 'Chăn ga gối đệm', 'unit' => 'Cái', 'price' => 50000, 'hours' => 24, 'description' => 'Giặt sạch chăn mền, ruột gối, ga gối nệm.'],
        ['category' => 'Giặt chăn ga nệm thảm', 'name' => 'Giặt rèm cửa / topper nệm', 'garment' => 'Chăn ga gối đệm', 'unit' => 'Kilogram', 'price' => 35000, 'hours' => 36, 'description' => 'Giặt rèm cửa, topper nệm, tính theo kilogram.'],
        ['category' => 'Giặt chăn ga nệm thảm', 'name' => 'Giặt thảm trải sàn', 'garment' => 'Chăn ga gối đệm', 'unit' => 'Bộ', 'price' => 45000, 'hours' => 24, 'description' => 'Giặt thảm văn phòng, thảm nhà riêng.'],
        ['category' => 'Chăm sóc giày & phụ kiện', 'name' => 'Vệ sinh chuyên sâu giày Sneaker', 'garment' => 'Giày dép', 'unit' => 'Đôi', 'price' => 80000, 'hours' => 24, 'description' => 'Vệ sinh, khử mùi, làm mới giày Sneaker chuyên nghiệp.'],
        ['category' => 'Chăm sóc giày & phụ kiện', 'name' => 'Tẩy trắng đế và vàng đế giày', 'garment' => 'Giày dép', 'unit' => 'Đôi', 'price' => 50000, 'hours' => 24, 'description' => 'Tẩy trắng đế giày, khử mùi, vệ sinh đế.'],
        ['category' => 'Chăm sóc giày & phụ kiện', 'name' => 'Bảo dưỡng túi xách da', 'garment' => 'Đồ da & phụ kiện', 'unit' => 'Cái', 'price' => 200000, 'hours' => 48, 'description' => 'Vệ sinh, dưỡng da, khử mùi túi xách da cao cấp.'],
    ];

    public function run(): void
    {
        foreach (self::CATEGORIES as $name => $description) {
            LoaiDichVu::updateOrCreate(
                ['TenLoaiDichVu' => $name],
                ['MoTa' => $description, 'TrangThai' => 'Hoạt động'],
            );
        }

        foreach (self::GARMENT_TYPES as $name => $description) {
            LoaiDoGiat::updateOrCreate(
                ['TenLoaiDoGiat' => $name],
                ['MoTa' => $description, 'TrangThai' => 'Hoạt động'],
            );
        }

        foreach (self::UNITS as $name => $symbol) {
            DonViTinh::updateOrCreate(
                ['TenDonViTinh' => $name],
                ['KyHieu' => $symbol, 'TrangThai' => 'Hoạt động'],
            );
        }

        $categories = LoaiDichVu::pluck('LoaiDichVuID', 'TenLoaiDichVu');

        foreach (self::SERVICES as $service) {
            DichVu::updateOrCreate(
                ['TenDichVu' => $service['name']],
                [
                    'LoaiDichVuID' => $categories[$service['category']] ?? null,
                    'MoTa' => $service['description'],
                    'ThoiGianDuKien' => $service['hours'],
                    'TrangThai' => 'Hoạt động',
                    'NgayTao' => now(),
                ],
            );
        }
    }
}
