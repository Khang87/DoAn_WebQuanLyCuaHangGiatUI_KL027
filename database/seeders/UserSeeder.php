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
        // Tài khoản mẫu dùng updateOrCreate theo email để chạy lại seeder
        // không bị lỗi unique và không làm mất role_id đã gán.
        // Cột `role` sẽ được User::booted() tự đồng bộ sang bảng `roles`.

        // Chủ cửa hàng (admin) - toàn quyền, kể cả đơn đã quyết toán
        User::updateOrCreate(['email' => 'admin@gmail.com'], [
            'name' => 'Chủ cửa hàng',
            'password' => Hash::make('123456'),
            'phone' => '0900000001',
            'role' => 'admin',
        ]);

        // Quản lý (manager) - không được sửa/xóa đơn đã quyết toán
        User::updateOrCreate(['email' => 'manager@gmail.com'], [
            'name' => 'Quản lý cửa hàng',
            'password' => Hash::make('123456'),
            'phone' => '0900000002',
            'role' => 'manager',
        ]);

        User::updateOrCreate(['email' => 'quanly@gmail.com'], [
            'name' => 'Quản lý',
            'password' => Hash::make('123456'),
            'phone' => '0900000012',
            'role' => 'manager',
        ]);

        // Nhân viên (staff)
        User::updateOrCreate(['email' => 'staff@gmail.com'], [
            'name' => 'Nhân viên',
            'password' => Hash::make('123456'),
            'phone' => '0900000003',
            'role' => 'staff',
        ]);

        // 'employee' là bí danh lịch sử, được ánh xạ về vai trò staff
        User::updateOrCreate(['email' => 'nhanvien@gmail.com'], [
            'name' => 'Nhân viên',
            'password' => Hash::make('123456'),
            'phone' => '0900000004',
            'role' => 'employee',
        ]);

        // Các nhân viên khác (giữ lại để test)
        $staffNames = [
            ['name' => 'Nhân viên 1', 'email' => 'staff1@giatui.com', 'phone' => '0900000005'],
            ['name' => 'Nhân viên 2', 'email' => 'staff2@giatui.com', 'phone' => '0900000006'],
            ['name' => 'Nhân viên 3', 'email' => 'staff3@giatui.com', 'phone' => '0900000007'],
        ];

        foreach ($staffNames as $staff) {
            User::factory()->create([
                'name' => $staff['name'],
                'email' => $staff['email'],
                'password' => Hash::make('123456'),
                'phone' => $staff['phone'],
                'role' => 'staff',
            ]);
        }

        // Khách hàng
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
