<?php

namespace Database\Seeders;

use App\Models\Garment;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class GarmentSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $garments = [
            ['name' => 'Áo dài', 'category' => 'Trang phục', 'price' => 30000, 'condition_note' => 'Cẩn thận khi giặt', 'status' => 'active'],
            ['name' => 'Áo vest', 'category' => 'Trang phục', 'price' => 45000, 'condition_note' => 'Giặt khô', 'status' => 'active'],
            ['name' => 'Áo sơ mi', 'category' => 'Trang phục', 'price' => 20000, 'condition_note' => 'Giặt thường', 'status' => 'active'],
            ['name' => 'Quần tây', 'category' => 'Trang phục', 'price' => 25000, 'condition_note' => 'Giặt thường', 'status' => 'active'],
            ['name' => 'Váy cưới', 'category' => 'Trang phục', 'price' => 150000, 'condition_note' => 'Rất cẩn thận', 'status' => 'active'],
            ['name' => 'Chăn ga', 'category' => 'Đồ dùng', 'price' => 50000, 'condition_note' => 'Giặt máy', 'status' => 'active'],
            ['name' => 'Gối chăn', 'category' => 'Đồ dùng', 'price' => 30000, 'condition_note' => 'Giặt máy', 'status' => 'active'],
            ['name' => 'Áo khoác', 'category' => 'Trang phục', 'price' => 40000, 'condition_note' => 'Giặt khô', 'status' => 'active'],
            ['name' => 'Quần jeans', 'category' => 'Trang phục', 'price' => 20000, 'condition_note' => 'Giặt thường', 'status' => 'active'],
            ['name' => 'Thảm', 'category' => 'Đồ dùng', 'price' => 60000, 'condition_note' => 'Giặt chuyên dụng', 'status' => 'active'],
        ];

        foreach ($garments as $garment) {
            \App\Models\Garment::create($garment);
        }
    }
}
