<?php

namespace Database\Seeders;

use App\Models\NhanVien;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Nạp nhân viên vào bảng `NhanVien`.
 *
 * Tài khoản đăng nhập (`TaiKhoan`) bắt buộc phải trỏ về đúng một nhân viên
 * hoặc một khách hàng (ràng buộc `CK_TaiKhoan_DoiTuong`), nên seeder này chạy
 * TRƯỚC UserSeeder và email ở đây là khoá nối với tài khoản.
 *
 * `SoDienThoai` là khoá duy nhất nên dùng làm khoá của updateOrCreate; nhân
 * viên thật đang có trong database không bị ghi đè.
 */
class NhanVienSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * @var list<array{name: string, email: string, phone: string, title: string}>
     */
    private const STAFF = [
        ['name' => 'Nguyễn Minh Quân', 'email' => 'admin@gmail.com', 'phone' => '0905000001', 'title' => 'Chủ cửa hàng'],
        ['name' => 'Trần Thị Mai Anh', 'email' => 'manager@gmail.com', 'phone' => '0905000002', 'title' => 'Quản lý'],
        ['name' => 'Lê Văn Hùng', 'email' => 'staff@gmail.com', 'phone' => '0905000003', 'title' => 'Nhân viên giặt ủi'],
        ['name' => 'Phạm Thị Thu Hà', 'email' => 'staff1@giatui.com', 'phone' => '0905000004', 'title' => 'Nhân viên giao nhận'],
        ['name' => 'Đỗ Anh Tuấn', 'email' => 'staff2@giatui.com', 'phone' => '0905000005', 'title' => 'Nhân viên giặt ủi'],
        ['name' => 'Vũ Thị Hồng Nhung', 'email' => 'staff3@giatui.com', 'phone' => '0905000006', 'title' => 'Nhân viên chăm sóc khách hàng'],
    ];

    public function run(): void
    {
        foreach (self::STAFF as $staff) {
            NhanVien::updateOrCreate(
                ['SoDienThoai' => $staff['phone']],
                [
                    'HoTen' => $staff['name'],
                    'Email' => $staff['email'],
                    'DiaChi' => '140 Lê Trọng Tấn, Phường Tây Thạnh, Quận Tân Phú, TP.HCM',
                    'ChucDanh' => $staff['title'],
                    'NgayVaoLam' => now()->subYears(random_int(1, 5))->format('Y-m-d'),
                    'TrangThai' => 'Hoạt động',
                ],
            );
        }
    }
}
