<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Nạp tài khoản mẫu vào bảng `TaiKhoan`.
 *
 * Vai trò không phải cột của `TaiKhoan` mà nằm ở bảng nối `TaiKhoan_VaiTro`,
 * nên mỗi tài khoản được gán thêm qua `assignRole()`.
 */
class UserSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Mật khẩu chung cho các tài khoản nhân viên/quản lý.
     */
    public const PASSWORD = '123456';

    /**
     * @var array<string, string> slug vai trò => tên hiển thị trong bảng `VaiTro`
     */
    private const ROLE_LABELS = [
        'owner' => 'Chủ cửa hàng',
        'manager' => 'Quản lý',
        'staff' => 'Nhân viên',
        'customer' => 'Khách hàng',
    ];

    /**
     * Tài khoản quản trị: Chủ cửa hàng, quản lý và nhân viên.
     *
     * @var list<array{username: string, email: string, phone: string, role: string}>
     */
    public const STAFF_ACCOUNTS = [
        ['username' => 'owner', 'email' => 'admin@gmail.com', 'phone' => '0900000001', 'role' => 'owner'],
        ['username' => 'quanly', 'email' => 'manager@gmail.com', 'phone' => '0900000002', 'role' => 'manager'],
        ['username' => 'nhanvien1', 'email' => 'staff@gmail.com', 'phone' => '0900000003', 'role' => 'staff'],
    ];

    public function run(): void
    {
        foreach (self::STAFF_ACCOUNTS as $account) {
            $this->createUser($account);
        }

        // Nhân viên còn lại, giữ lại để test có đủ dữ liệu.
        $staffNames = [
            ['username' => 'nhanvien2', 'email' => 'staff1@giatui.com', 'phone' => '0900000005'],
            ['username' => 'nhanvien3', 'email' => 'staff2@giatui.com', 'phone' => '0900000006'],
            ['username' => 'nhanvien4', 'email' => 'staff3@giatui.com', 'phone' => '0900000007'],
        ];

        foreach ($staffNames as $staff) {
            $this->createUser($staff + ['role' => 'staff']);
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
            $this->createUser([
                'username' => $name,
                'email' => $emails[$index],
                'phone' => $phones[$index],
                'role' => 'customer',
            ]);
        }
    }

    /**
     * Tạo (hoặc cập nhật) một tài khoản rồi gán vai trò tương ứng.
     *
     * Dùng `updateOrCreate` theo `Email` để chạy lại seeder không vi phạm ràng
     * buộc duy nhất và không mất vai trò đã gán.
     *
     * @param  array{username: string, email: string, phone: string, role: string}  $account
     */
    private function createUser(array $account): void
    {
        $user = User::updateOrCreate(
            ['Email' => $account['email']],
            [
                'TenDangNhap' => $account['username'],
                'MatKhau' => Hash::make(self::PASSWORD),
                'SoDienThoai' => $account['phone'],
                'TrangThai' => 'Hoạt động',
                'NgayTao' => now(),
            ],
        );

        $this->assignRole($user, $account['role']);
    }

    /**
     * Ghi vai trò vào bảng nối `TaiKhoan_VaiTro`.
     */
    private function assignRole(User $user, string $slug): void
    {
        $role = DB::table('VaiTro')
            ->where('TenVaiTro', self::ROLE_LABELS[$slug] ?? $slug)
            ->first();

        if (! $role) {
            return;
        }

        $exists = DB::table('TaiKhoan_VaiTro')
            ->where('TaiKhoanID', $user->getKey())
            ->where('VaiTroID', $role->VaiTroID)
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('TaiKhoan_VaiTro')->insert([
            'TaiKhoanID' => $user->getKey(),
            'VaiTroID' => $role->VaiTroID,
        ]);
    }
}
