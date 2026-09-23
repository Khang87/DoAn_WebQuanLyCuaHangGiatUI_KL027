<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Service;
use App\Models\Garment;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $seedUsers = [
            [
                'name' => 'Quản lý',
                'email' => 'admin@giatui.com',
                'password' => 'admin123',
                'role' => 'admin',
            ],
            [
                'name' => 'Nhân viên',
                'email' => 'staff@giatui.com',
                'password' => 'staff123',
                'role' => 'staff',
            ],
            [
                'name' => 'Khách hàng VIP',
                'email' => 'vip@giatui.com',
                'password' => 'vip123',
                'role' => 'vip',
            ],
        ];

        foreach ($seedUsers as $userData) {
            User::updateOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'password' => Hash::make($userData['password']),
                    'role' => $userData['role'],
                ]
            );
        }

        $services = [
            ['name' => 'Giặt thường', 'type' => 'wash', 'price' => 25000, 'unit' => 'kg'],
            ['name' => 'Giặt khô', 'type' => 'dry_clean', 'price' => 45000, 'unit' => 'cái'],
            ['name' => 'Ủi đồ', 'type' => 'iron', 'price' => 15000, 'unit' => 'món'],
            ['name' => 'Giặt chăn mền', 'type' => 'blanket', 'price' => 80000, 'unit' => 'món'],
            ['name' => 'Giặt giày', 'type' => 'shoes', 'price' => 60000, 'unit' => 'đôi'],
        ];

        foreach ($services as $service) {
            Service::firstOrCreate(['name' => $service['name']], $service);
        }

        $customers = [
            ['code' => 'KH001', 'name' => 'Nguyễn Văn A', 'email' => 'nguyenvana@email.com', 'phone' => '0901234567', 'address' => '123 Đường ABC, Quận 1, TP.HCM', 'type' => 'VIP', 'points' => 1250],
            ['code' => 'KH002', 'name' => 'Trần Thị B', 'email' => 'tranthib@email.com', 'phone' => '0912345678', 'address' => '456 Đường XYZ, Quận 3, TP.HCM', 'type' => 'Thường', 'points' => 850],
            ['code' => 'KH003', 'name' => 'Phạm Thị C', 'email' => 'phamthic@email.com', 'phone' => '0923456789', 'address' => '789 Đường DEF, Quận 5, TP.HCM', 'type' => 'Mới', 'points' => 0],
        ];

        foreach ($customers as $customer) {
            Customer::firstOrCreate(['code' => $customer['code']], $customer);
        }

        if (Order::count() === 0) {
            Order::create([
                'code' => 'DH001',
                'customer_id' => Customer::where('code', 'KH001')->value('id'),
                'service_id' => Service::where('name', 'Giặt thường')->value('id'),
                'weight_kg' => '5kg',
                'quantity_items' => 'đồ thường + 3 áo trắng',
                'total_amount' => 250000,
                'status' => 'processing',
                'notes' => 'Giặt nhẹ, lấy trước 18h tối nay.',
            ]);
        }
    }
}
