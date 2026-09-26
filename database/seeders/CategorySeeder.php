<?php

namespace Database\Seeders;

use App\Models\ServiceCategory;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $categories = [
            [
                'code' => 'CAT_QUAN_AO',
                'name' => 'Quần áo thường hằng ngày',
                'slug' => Str::slug('Quần áo thường hàng ngày'),
                'description' => 'Quần áo thun, sơ mi, quần tây, đồ mặc nhà giặt sấy sấy khô sếp gọn.',
                'icon' => 'fa-solid fa-shirt',
                'status' => 'active',
            ],
            [
                'code' => 'CAT_CAO_CAP',
                'name' => 'Đồ cao cấp & tế nhị',
                'slug' => Str::slug('Đồ cao cấp Tế nhị'),
                'description' => 'Vest/Suit, áo dài, đầm tiệc, váy cưới, đồ lụa, đồ dạ, đồ da cần giặt hấp/giặt khô.',
                'icon' => 'fa-solid fa-gem',
                'status' => 'active',
            ],
            [
                'code' => 'CAT_CHANNGU',
                'name' => 'Chăn ga & mền gối',
                'slug' => Str::slug('Chăn ga Mền gối'),
                'description' => 'Mền thun, ruột chăn bông, ga giường, vỏ gối, gấu bông các kích thước.',
                'icon' => 'fa-solid fa-bed',
                'status' => 'active',
            ],
            [
                'code' => 'CAT_GIAY_TUI',
                'name' => 'Giày & túi xách / phụ kiện',
                'slug' => Str::slug('Giày Túi xách Phụ kiện'),
                'description' => 'Vệ sinh chuyên sâu giày Sneaker, giày da, túi xách, balo, nón bảo hiểm.',
                'icon' => 'fa-solid fa-shoe-prints',
                'status' => 'active',
            ],
            [
                'code' => 'CAT_REM_THAM',
                'name' => 'Rèm cửa & thảm trải sàn',
                'slug' => Str::slug('Rèm cửa Thảm trải sàn'),
                'description' => 'Rèm vải chống nắng, thảm sofa, thảm trải sàn văn phòng/gia đình.',
                'icon' => 'fa-solid fa-rug',
                'status' => 'inactive',
            ],
        ];

        foreach ($categories as $cat) {
            ServiceCategory::updateOrCreate(
                ['slug' => $cat['slug']],
                $cat
            );
        }
    }
}
