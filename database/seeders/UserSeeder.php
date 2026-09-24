<?php

namespace Database\Seeders;

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

        foreach ($customerNames as $index => $name) {
            User::factory()->create([
                'name' => $name,
                'email' => $emails[$index],
                'password' => Hash::make('password'),
                'phone' => $phones[$index],
                'role' => 'customer',
            ]);
        }
    }
}
