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
            ['TenLoaiDoGiat' => 'Áo dài', 'MoTa' => 'Cẩn thận khi giặt', 'TrangThai' => 'Hoạt động'],
            ['TenLoaiDoGiat' => 'Áo vest', 'MoTa' => 'Giặt khô', 'TrangThai' => 'Hoạt động'],
            ['TenLoaiDoGiat' => 'Áo sơ mi', 'MoTa' => 'Giặt thường', 'TrangThai' => 'Hoạt động'],
            ['TenLoaiDoGiat' => 'Quần tây', 'MoTa' => 'Giặt thường', 'TrangThai' => 'Hoạt động'],
            ['TenLoaiDoGiat' => 'Váy cưới', 'MoTa' => 'Rất cẩn thận', 'TrangThai' => 'Hoạt động'],
            ['TenLoaiDoGiat' => 'Chăn ga', 'MoTa' => 'Giặt máy', 'TrangThai' => 'Hoạt động'],
            ['TenLoaiDoGiat' => 'Gối chăn', 'MoTa' => 'Giặt máy', 'TrangThai' => 'Hoạt động'],
            ['TenLoaiDoGiat' => 'Áo khoác', 'MoTa' => 'Giặt khô', 'TrangThai' => 'Hoạt động'],
            ['TenLoaiDoGiat' => 'Quần jeans', 'MoTa' => 'Giặt thường', 'TrangThai' => 'Hoạt động'],
            ['TenLoaiDoGiat' => 'Thảm', 'MoTa' => 'Giặt chuyên dụng', 'TrangThai' => 'Hoạt động'],
        ];

        foreach ($garments as $garment) {
            Garment::create($garment);
        }
    }
}
