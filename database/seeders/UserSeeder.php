<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        User::factory()->create([
            'name' => 'Quản lý',
            'email' => 'admin@giatui.com',
            'password' => Hash::make('12345678'),
            'phone' => '0900000001',
            'role' => 'admin',
        ]);

        $staffNames = [
            ['name' => 'Nhân viên 1', 'email' => 'staff1@giatui.com', 'password' => 'staff123', 'phone' => '0900000002'],
            ['name' => 'Nhân viên 2', 'email' => 'staff2@giatui.com', 'password' => 'staff123', 'phone' => '0900000003'],
            ['name' => 'Nhân viên 3', 'email' => 'staff3@giatui.com', 'password' => 'staff123', 'phone' => '0900000004'],
        ];

        foreach ($staffNames as $staff) {
            User::factory()->create([
                'name' => $staff['name'],
                'email' => $staff['email'],
                'password' => Hash::make($staff['password']),
                'phone' => $staff['phone'],
                'role' => 'staff',
            ]);
        }

        $customerNames = [
            'Nguyễn Văn A', 'Trần Thị B', 'Phạm Thị C', 'Lê Văn D', 'Hoàng Thị E',
            'Đặng Minh F', 'Bùi Thu G', 'Vũ Văn H', 'Trần Lee I', 'Lương Văn J',
        ];

        $emails = [
            'customer1@email.com', 'customer2@email.com', 'customer3@email.com',
            'customer4@email.com', 'customer5@email.com', 'customer6@email.com',
            'customer7@email.com', 'customer8@email.com', 'customer9@email.com',
            'customer10@email.com',
        ];

        $phones = [
            '0901234567', '0912345678', '0923456789', '0934567890', '0945678901',
            '0956789012', '0967890123', '0978901234', '0989012345', '0990123456',
        ];

        $addresses = [
            '123 Đường ABC, Quận 1, TP.HCM', '456 Đường XYZ, Quận 3, TP.HCM',
            '789 Đường DEF, Quận 5, TP.HCM', '101 Đường GHI, Quận 2, TP.HCM',
            '202 Đường JKL, Quận 4, TP.HCM', '303 Đường MNO, Quận 7, TP.HCM',
            '404 Đường PQR, Quận 6, TP.HCM', '505 Đường STU, Quận 8, TP.HCM',
            '606 Đường VWX, Quận 9, TP.HCM', '707 Đường YZ, Quận 10, TP.HCM',
        ];

        $types = ['Mới', 'Thường', 'VIP', 'VIP', 'Thường', 'Mới', 'Thường', 'VIP', 'Mới', 'Thường'];

        foreach ($customerNames as $index => $name) {
            User::factory()->create([
                'name' => $name,
                'email' => $emails[$index],
                'password' => Hash::make('password'),
                'phone' => $phones[$index],
                'role' => 'customer',
            ]);

            Customer::create([
                'code' => 'KH' . str_pad($index + 1, 3, '0', STR_PAD_LEFT),
                'name' => $name,
                'email' => $emails[$index],
                'phone' => $phones[$index],
                'address' => $addresses[$index],
                'points' => [1250, 850, 0, 2000, 500, 300, 1500, 0, 750, 1200][$index],
                'type' => $types[$index],
            ]);
        }
    }
}
