<?php

namespace Database\Seeders;

use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        // Get category IDs by slug
        $cat1 = ServiceCategory::where('slug', 'giat-say-quan-ao')->first()?->id ?? 1;
        $cat2 = ServiceCategory::where('slug', 'giat-hap-giat-kho-cao-cap')->first()?->id ?? 2;
        $cat3 = ServiceCategory::where('slug', 'giat-chan-ga-nem-tham')->first()?->id ?? 3;
        $cat4 = ServiceCategory::where('slug', 'cham-soc-giay-phu-kien')->first()?->id ?? 4;

        $services = [
            // Danh mục 1: Giặt Sấy Quần Áo
            [
                'name' => 'Giặt sấy lấy liền (Dưới 4h)',
                'type' => 'express_wash_dry',
                'price' => 25000,
                'unit' => 'Kg',
                'status' => 'active',
                'description' => 'Dịch vụ giặt sấy nhanh, lấy đồ trong 4 giờ',
                'service_category_id' => $cat1,
                'processing_time' => 4,
                'icon' => 'fa-solid fa-bolt',
            ],
            [
                'name' => 'Giặt sấy thông thường',
                'type' => 'wash_dry',
                'price' => 15000,
                'unit' => 'Kg',
                'status' => 'active',
                'description' => 'Giặt sấy tiêu chuẩn, thời gian 12 giờ',
                'service_category_id' => $cat1,
                'processing_time' => 12,
                'icon' => 'fa-solid fa-dryer',
            ],
            [
                'name' => 'Giặt xả gấp xếp ngăn nắp',
                'type' => 'wash_fold',
                'price' => 18000,
                'unit' => 'Kg',
                'status' => 'active',
                'description' => 'Giặt xả và gấp xếp sẵn sàng sử dụng',
                'service_category_id' => $cat1,
                'processing_time' => 12,
                'icon' => 'fa-solid fa-shirt',
            ],

            // Danh mục 2: Giặt Hấp / Giặt Khô Cao Cấp
            [
                'name' => 'Giặt hấp bộ Vest / Suit',
                'type' => 'steam_suit',
                'price' => 120000,
                'unit' => 'Bộ',
                'status' => 'active',
                'description' => 'Giặt hấp chuyên nghiệp cho vest, suit công sở',
                'service_category_id' => $cat2,
                'processing_time' => 24,
                'icon' => 'fa-solid fa-user-tie',
            ],
            [
                'name' => 'Giặt khô Áo dạ / Áo măng tô',
                'type' => 'dry_clean_winter',
                'price' => 90000,
                'unit' => 'Cái',
                'status' => 'active',
                'description' => 'Giặt khô chuyên dụng cho áo dạ, áo măng tô',
                'service_category_id' => $cat2,
                'processing_time' => 24,
                'icon' => 'fa-solid fa-coat-hanger',
            ],
            [
                'name' => 'Giặt hấp Váy cưới / Đầm dạ hội',
                'type' => 'steam_wedding',
                'price' => 250000,
                'unit' => 'Cái',
                'status' => 'active',
                'description' => 'Giặt hấp tinh tế cho váy cưới, đầm dạ hội cao cấp',
                'service_category_id' => $cat2,
                'processing_time' => 48,
                'icon' => 'fa-solid fa-dress',
            ],
            [
                'name' => 'Giặt hấp Áo dài truyền thống',
                'type' => 'steam_ao_dai',
                'price' => 70000,
                'unit' => 'Bộ',
                'status' => 'active',
                'description' => 'Giặt hấp bảo quản áo dài truyền thống',
                'service_category_id' => $cat2,
                'processing_time' => 24,
                'icon' => 'fa-solid fa-person-dress',
            ],

            // Danh mục 3: Giặt Chăn Ga Nệm & Thảm
            [
                'name' => 'Giặt chăn mền / Ruột gối',
                'type' => 'blanket_pillow',
                'price' => 50000,
                'unit' => 'Cái',
                'status' => 'active',
                'description' => 'Giặt sạch chăn mền, ruột gối, ga gối nệm',
                'service_category_id' => $cat3,
                'processing_time' => 24,
                'icon' => 'fa-solid fa-bed',
            ],
            [
                'name' => 'Giặt Rèm cửa / Topper',
                'type' => 'curtain_topper',
                'price' => 35000,
                'unit' => 'Kg',
                'status' => 'active',
                'description' => 'Giặt rèm cửa, topper nệm, tính theo kg',
                'service_category_id' => $cat3,
                'processing_time' => 36,
                'icon' => 'fa-solid fa-window-maximize',
            ],
            [
                'name' => 'Giặt thảm trải sàn',
                'type' => 'carpet',
                'price' => 45000,
                'unit' => 'm²',
                'status' => 'active',
                'description' => 'Giặt thảm văn phòng, thảm nhà riêng tính theo m²',
                'service_category_id' => $cat3,
                'processing_time' => 24,
                'icon' => 'fa-solid fa-rug',
            ],

            // Danh mục 4: Chăm Sóc Giày & Phụ Kiện
            [
                'name' => 'Vệ sinh chuyên sâu Giày Sneaker',
                'type' => 'sneaker_clean',
                'price' => 80000,
                'unit' => 'Đôi',
                'status' => 'active',
                'description' => 'Vệ sinh, khử mùi, làm mới giày Sneaker chuyên nghiệp',
                'service_category_id' => $cat4,
                'processing_time' => 24,
                'icon' => 'fa-solid fa-shoe-prints',
            ],
            [
                'name' => 'Tẩy ố ếch ố vàng đế giày',
                'type' => 'sole_whitening',
                'price' => 50000,
                'unit' => 'Đôi',
                'status' => 'active',
                'description' => 'Tẩy trắng đế giày, khử ố vàng, ố ếch',
                'service_category_id' => $cat4,
                'processing_time' => 24,
                'icon' => 'fa-solid fa-magic-wand-sparkles',
            ],
            [
                'name' => 'Bảo dưỡng & Làm mới Túi xách da',
                'type' => 'leather_bag_care',
                'price' => 200000,
                'unit' => 'Cái',
                'status' => 'active',
                'description' => 'Vệ sinh, dưỡng da, khử mùi, bảo vệ túi xách da cao cấp',
                'service_category_id' => $cat4,
                'processing_time' => 48,
                'icon' => 'fa-solid fa-briefcase',
            ],
        ];

        foreach ($services as $service) {
            Service::create($service);
        }
    }
}