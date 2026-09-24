<?php

namespace Database\Seeders;

use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $categories = [
            ['name' => 'Giặt thường', 'slug' => 'giat-thuong', 'description' => 'Dịch vụ giặt ủi thường', 'icon' => 'bi-shirt', 'status' => 'active'],
            ['name' => 'Giặt khô', 'slug' => 'giat-kho', 'description' => 'Dịch vụ giặt khô', 'icon' => 'bi-droplet', 'status' => 'active'],
            ['name' => 'Ủi đồ', 'slug' => 'ui-do', 'description' => 'Dịch vụ ủi đồ', 'icon' => 'bi-fire', 'status' => 'active'],
            ['name' => 'Giặt chăn mền', 'slug' => 'giat-chan-mien', 'description' => 'Dịch vụ giặt chăn mền', 'icon' => 'bi-bed', 'status' => 'active'],
            ['name' => 'Giặt giày', 'slug' => 'giat-giay', 'description' => 'Dịch vụ giặt giày', 'icon' => 'bi-shoe-prints', 'status' => 'active'],
        ];

        foreach ($categories as $cat) {
            ServiceCategory::create($cat);
        }

        $services = [
            ['name' => 'Giặt thường', 'type' => 'wash', 'price' => 25000, 'unit' => 'kg', 'status' => 'active', 'description' => 'Giặt ủi thường bằng máy', 'service_category_id' => 1, 'processing_time' => 18, 'icon' => 'fa-solid fa-washer'],
            ['name' => 'Giặt khô', 'type' => 'dry_clean', 'price' => 45000, 'unit' => 'cái', 'status' => 'active', 'description' => 'Giặt khô chuyên dụp cho đồ cao cấp', 'service_category_id' => 2, 'processing_time' => 40, 'icon' => 'fa-solid fa-broom-ball'],
            ['name' => 'Ủi đồ', 'type' => 'iron', 'price' => 15000, 'unit' => 'món', 'status' => 'active', 'description' => 'Ủi phẳng đồ giặt', 'service_category_id' => 3, 'processing_time' => 12, 'icon' => 'fa-solid fa-jug-detergent'],
            ['name' => 'Giặt chăn mền', 'type' => 'blanket', 'price' => 80000, 'unit' => 'món', 'status' => 'active', 'description' => 'Giặt và ủi chăn mền, ga gối nệm', 'service_category_id' => 4, 'processing_time' => 36, 'icon' => 'fa-solid fa-bed'],
            ['name' => 'Giặt giày', 'type' => 'shoes', 'price' => 60000, 'unit' => 'đôi', 'status' => 'active', 'description' => 'Giặt và làm sạch giày dép', 'service_category_id' => 5, 'processing_time' => 24, 'icon' => 'fa-solid fa-shoe-prints'],
            ['name' => 'Giặt nhanh', 'type' => 'express', 'price' => 50000, 'unit' => 'kg', 'status' => 'active', 'description' => 'Dịch vụ giặt nhanh trong 2 giờ', 'service_category_id' => 1, 'processing_time' => 2, 'icon' => 'fa-solid fa-bolt'],
            ['name' => 'Giặt hấp', 'type' => 'steam', 'price' => 35000, 'unit' => 'món', 'status' => 'active', 'description' => 'Giặt hấp khử khuẩn, bảo quản đồ dệt', 'service_category_id' => 1, 'processing_time' => 30, 'icon' => 'fa-solid fa-steam-symbol'],
            ['name' => 'Giặt sấy', 'type' => 'wash_dry', 'price' => 55000, 'unit' => 'kg', 'status' => 'active', 'description' => 'Giặt và sấy khô tự động', 'service_category_id' => 1, 'processing_time' => 18, 'icon' => 'fa-solid fa-dryer'],
        ];

        foreach ($services as $service) {
            Service::create($service);
        }
    }
}
