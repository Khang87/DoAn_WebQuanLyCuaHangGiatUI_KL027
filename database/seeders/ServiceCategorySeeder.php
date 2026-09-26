<?php

namespace Database\Seeders;

use App\Models\ServiceCategory;
use Illuminate\Database\Seeder;

class ServiceCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Giặt sấy quần áo',
                'slug' => 'giat-say-quan-ao',
                'description' => 'Giặt quần áo hằng ngày, tính theo kg',
                'icon' => 'fa-solid fa-shirt',
                'status' => 'active',
            ],
            [
                'name' => 'Giặt hấp / giặt khô cao cấp',
                'slug' => 'giat-hap-giat-kho-cao-cap',
                'description' => 'Dành cho Vest, Váy cưới, Áo dạ, Áo dài, Đồ hiệu',
                'icon' => 'fa-solid fa-shirt-long-sleeve',
                'status' => 'active',
            ],
            [
                'name' => 'Giặt chăn ga nệm & thảm',
                'slug' => 'giat-chan-ga-nem-tham',
                'description' => 'Giặt chăn màn, ga gối, rèm cửa, thảm văn phòng',
                'icon' => 'fa-solid fa-bed',
                'status' => 'active',
            ],
            [
                'name' => 'Chăm sóc giày & phụ kiện',
                'slug' => 'cham-soc-giay-phu-kien',
                'description' => 'Vệ sinh giày Sneaker, tẩy ố, bảo dưỡng đồ da',
                'icon' => 'fa-solid fa-shoe-prints',
                'status' => 'active',
            ],
        ];

        foreach ($categories as $cat) {
            ServiceCategory::create($cat);
        }
    }
}