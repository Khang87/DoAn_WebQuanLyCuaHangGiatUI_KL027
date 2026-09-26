<?php

namespace Database\Seeders;

use App\Models\LaundryCategory;
use Illuminate\Database\Seeder;

class LaundryCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Quần áo thường',
                'slug' => 'quan-ao-thuong',
                'description' => 'Quần áo hàng ngày, áo phông, quần jean, quần short...',
                'icon' => 'fa-solid fa-tshirt',
                'status' => 'active',
            ],
            [
                'name' => 'Đồ dạ & áo khoác',
                'slug' => 'do-da-ao-khoac',
                'description' => 'Áo khoác dạ, áo len, vest, blazer, trench coat...',
                'icon' => 'fa-solid fa-shirt-long-sleeve',
                'status' => 'active',
            ],
            [
                'name' => 'Chăn ga gối đệm',
                'slug' => 'chan-ga-goi-dem',
                'description' => 'Chăn bông, ga giường, vỏ gối, đệm, chăn điện...',
                'icon' => 'fa-solid fa-bed',
                'status' => 'active',
            ],
            [
                'name' => 'Rèm & vải trang trí',
                'slug' => 'rem-vai-trang-tri',
                'description' => 'Rèm cửa, rèm chắn nắng, khăn trải bàn, khăn trải giường...',
                'icon' => 'fa-solid fa-window-maximize',
                'status' => 'active',
            ],
            [
                'name' => 'Đồ thể thao & outdoor',
                'slug' => 'do-the-thao-outdoor',
                'description' => 'Áo thun thể thao, quần tập, áo mưa, balo, túi xách...',
                'icon' => 'fa-solid fa-person-running',
                'status' => 'active',
            ],
            [
                'name' => 'Đồ em bé & trẻ em',
                'slug' => 'do-em-be-tre-em',
                'description' => 'Quần áo sơ sinh, khăn ủi, chăn em bé, đồ chơi vải...',
                'icon' => 'fa-solid fa-baby',
                'status' => 'active',
            ],
            [
                'name' => 'Giày dép & phụ kiện',
                'slug' => 'giay-dep-phu-kien',
                'description' => 'Giày sneaker, boots, sandal, túi xách, mũ nón, khăn quàng...',
                'icon' => 'fa-solid fa-shoe-prints',
                'status' => 'active',
            ],
            [
                'name' => 'Đồ nội y & đồ ngủ',
                'slug' => 'do-noi-y-do-ngu',
                'description' => 'Áo ngực, quần lót, áo ngủ, váy ngủ, cao su y tế...',
                'icon' => 'fa-solid fa-heart',
                'status' => 'active',
            ],
        ];

        foreach ($categories as $index => $cat) {
            $code = 'DM' . str_pad($index + 1, 3, '0', STR_PAD_LEFT);
            LaundryCategory::create(array_merge($cat, ['code' => $code]));
        }
    }
}