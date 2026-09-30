<?php

namespace Database\Seeders;

use App\Models\KhachHang;
use App\Models\NhanVien;
use App\Models\TaiKhoan;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Nạp tài khoản vào bảng `TaiKhoan`.
 *
 * Ràng buộc `CK_TaiKhoan_DoiTuong` bắt buộc mỗi tài khoản trỏ về đúng một
 * nhân viên HOẶC một khách hàng, nên tài khoản được nối theo email của
 * NhanVienSeeder / CustomerSeeder.
 *
 * Vai trò không phải cột của `TaiKhoan` mà nằm ở bảng nối `TaiKhoan_VaiTro`.
 */
class UserSeeder extends Seeder
{
    use WithoutModelEvents;

    /** Mật khẩu chung cho các tài khoản nhân viên/quản lý. */
    public const PASSWORD = '123456';

    /**
     * Mã vai trò => tên vai trò trong bảng `VaiTro`.
     *
     * @var array<string, string>
     */
    private const ROLE_NAMES = [
        'owner' => 'Chủ cửa hàng',
        'manager' => 'Quản lý',
        'staff' => 'Nhân viên',
        'customer' => 'Khách hàng',
    ];

    /**
     * @var list<array{username: string, email: string, phone: string, role: string}>
     */
    private const STAFF_ACCOUNTS = [
        ['username' => 'owner', 'email' => 'admin@gmail.com', 'phone' => '0900000001', 'role' => 'owner'],
        ['username' => 'quanly', 'email' => 'manager@gmail.com', 'phone' => '0900000002', 'role' => 'manager'],
        ['username' => 'nhanvien1', 'email' => 'staff@gmail.com', 'phone' => '0900000003', 'role' => 'staff'],
        ['username' => 'nhanvien2', 'email' => 'staff1@giatui.com', 'phone' => '0900000005', 'role' => 'staff'],
        ['username' => 'nhanvien3', 'email' => 'staff2@giatui.com', 'phone' => '0900000006', 'role' => 'staff'],
        ['username' => 'nhanvien4', 'email' => 'staff3@giatui.com', 'phone' => '0900000007', 'role' => 'staff'],
    ];

    public function run(): void
    {
        foreach (self::STAFF_ACCOUNTS as $account) {
            $staff = NhanVien::where('Email', $account['email'])->first();

            if (! $staff) {
                $this->command?->warn("Chưa có nhân viên cho {$account['email']}, bỏ qua tài khoản.");

                continue;
            }

            $this->createAccount([
                'TenDangNhap' => $account['username'],
                'Email' => $account['email'],
                'SoDienThoai' => $account['phone'],
                'NhanVienID' => $staff->NhanVienID,
            ], $account['role']);
        }

        // Khách hàng đã có trong CustomerSeeder được nối thẳng vào tài khoản.
        foreach (KhachHang::whereNotNull('Email')->get() as $customer) {
            $this->createAccount([
                'TenDangNhap' => $this->usernameFromEmail($customer->Email),
                'Email' => $customer->Email,
                'SoDienThoai' => $customer->SoDienThoai,
                'KhachHangID' => $customer->KhachHangID,
            ], 'customer');
        }
    }

    /**
     * Tạo (hoặc cập nhật) một tài khoản rồi gán vai trò tương ứng.
     *
     * Dùng `updateOrCreate` theo `Email` để chạy lại seeder không vi phạm ràng
     * buộc duy nhất và không mất vai trò đã gán.
     */
    private function createAccount(array $attributes, string $roleSlug): void
    {
        $account = TaiKhoan::updateOrCreate(
            ['Email' => $attributes['Email']],
            $attributes + [
                'MatKhau' => Hash::make(self::PASSWORD),
                'TrangThai' => 'Hoạt động',
                'NgayTao' => now(),
            ],
        );

        $role = DB::table('VaiTro')
            ->where('TenVaiTro', self::ROLE_NAMES[$roleSlug] ?? $roleSlug)
            ->first();

        if (! $role) {
            return;
        }

        $exists = DB::table('TaiKhoan_VaiTro')
            ->where('TaiKhoanID', $account->getKey())
            ->where('VaiTroID', $role->VaiTroID)
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('TaiKhoan_VaiTro')->insert([
            'TaiKhoanID' => $account->getKey(),
            'VaiTroID' => $role->VaiTroID,
        ]);
    }

    /**
     * `customer1@email.com` => `customer1`.
     */
    private function usernameFromEmail(string $email): string
    {
        return Str::before($email, '@');
    }
}
