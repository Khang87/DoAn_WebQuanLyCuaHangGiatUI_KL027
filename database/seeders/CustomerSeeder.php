<?php

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $customers = [
            ['code' => 'KH001', 'name' => 'Nguyễn Văn A', 'email' => 'customer1@email.com', 'phone' => '0901234567', 'address' => '123 Đường ABC, Quận 1, TP.HCM', 'points' => 1250, 'type' => 'Thường'],
            ['code' => 'KH002', 'name' => 'Trần Thị B', 'email' => 'customer2@email.com', 'phone' => '0912345678', 'address' => '456 Đường XYZ, Quận 3, TP.HCM', 'points' => 850, 'type' => 'Thường'],
            ['code' => 'KH003', 'name' => 'Phạm Thị C', 'email' => 'customer3@email.com', 'phone' => '0923456789', 'address' => '789 Đường DEF, Quận 5, TP.HCM', 'points' => 0, 'type' => 'Mới'],
            ['code' => 'KH004', 'name' => 'Lê Văn D', 'email' => 'customer4@email.com', 'phone' => '0934567890', 'address' => '101 Đường GHI, Quận 2, TP.HCM', 'points' => 2000, 'type' => 'VIP'],
            ['code' => 'KH005', 'name' => 'Hoàng Thị E', 'email' => 'customer5@email.com', 'phone' => '0945678901', 'address' => '202 Đường JKL, Quận 4, TP.HCM', 'points' => 500, 'type' => 'Thường'],
            ['code' => 'KH006', 'name' => 'Đặng Minh F', 'email' => 'customer6@email.com', 'phone' => '0956789012', 'address' => '303 Đường MNO, Quận 7, TP.HCM', 'points' => 300, 'type' => 'Mới'],
            ['code' => 'KH007', 'name' => 'Bùi Thu G', 'email' => 'customer7@email.com', 'phone' => '0967890123', 'address' => '404 Đường PQR, Quận 6, TP.HCM', 'points' => 1500, 'type' => 'Thường'],
            ['code' => 'KH008', 'name' => 'Vũ Văn H', 'email' => 'customer8@email.com', 'phone' => '0978901234', 'address' => '505 Đường STU, Quận 8, TP.HCM', 'points' => 0, 'type' => 'Mới'],
            ['code' => 'KH009', 'name' => 'Trần Lee I', 'email' => 'customer9@email.com', 'phone' => '0989012345', 'address' => '606 Đường VWX, Quận 9, TP.HCM', 'points' => 750, 'type' => 'Thường'],
            ['code' => 'KH010', 'name' => 'Lương Văn J', 'email' => 'customer10@email.com', 'phone' => '0990123456', 'address' => '707 Đường YZ, Quận 10, TP.HCM', 'points' => 1200, 'type' => 'Thường'],
        ];

        foreach ($customers as $data) {
            Customer::create($data);
        }
    }
}
