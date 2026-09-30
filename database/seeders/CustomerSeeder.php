<?php

namespace Database\Seeders;

use App\Models\DiemTichLuy;
use App\Models\KhachHang;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Nạp khách hàng vào bảng `KhachHang` kèm điểm tích luỹ trong `DiemTichLuy`.
 *
 * Email ở đây là khoá nối với tài khoản đăng nhập loại khách hàng, nên phải
 * khớp với UserSeeder.
 */
class CustomerSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * @var list<array{name: string, email: string, phone: string, address: string, points: int}>
     */
    private const CUSTOMERS = [
        ['name' => 'Nguyễn Văn An', 'email' => 'customer1@email.com', 'phone' => '0901234567', 'address' => '123 Đường ABC, Quận 1, TP.HCM', 'points' => 1250],
        ['name' => 'Trần Thị B', 'email' => 'customer2@email.com', 'phone' => '0912345678', 'address' => '456 Đường XYZ, Quận 3, TP.HCM', 'points' => 850],
        ['name' => 'Phạm Thị C', 'email' => 'customer3@email.com', 'phone' => '0923456789', 'address' => '789 Đường DEF, Quận 5, TP.HCM', 'points' => 0],
        ['name' => 'Lê Văn D', 'email' => 'customer4@email.com', 'phone' => '0934567890', 'address' => '101 Đường GHI, Quận 2, TP.HCM', 'points' => 2000],
        ['name' => 'Hoàng Thị E', 'email' => 'customer5@email.com', 'phone' => '0945678901', 'address' => '202 Đường JKL, Quận 4, TP.HCM', 'points' => 500],
        ['name' => 'Đặng Minh F', 'email' => 'customer6@email.com', 'phone' => '0956789012', 'address' => '303 Đường MNO, Quận 7, TP.HCM', 'points' => 300],
        ['name' => 'Bùi Thu G', 'email' => 'customer7@email.com', 'phone' => '0967890123', 'address' => '404 Đường PQR, Quận 6, TP.HCM', 'points' => 1500],
        ['name' => 'Vũ Văn H', 'email' => 'customer8@email.com', 'phone' => '0978901234', 'address' => '505 Đường STU, Quận 8, TP.HCM', 'points' => 0],
        ['name' => 'Trần Lee I', 'email' => 'customer9@email.com', 'phone' => '0989012345', 'address' => '606 Đường VWX, Quận 9, TP.HCM', 'points' => 750],
        ['name' => 'Lương Văn J', 'email' => 'customer10@email.com', 'phone' => '0990123456', 'address' => '707 Đường YZ, Quận 10, TP.HCM', 'points' => 1200],
    ];

    public function run(): void
    {
        foreach (self::CUSTOMERS as $customer) {
            $record = KhachHang::updateOrCreate(
                ['SoDienThoai' => $customer['phone']],
                [
                    'HoTen' => $customer['name'],
                    'Email' => $customer['email'],
                    'DiaChi' => $customer['address'],
                    'NgayTao' => now()->subDays(random_int(30, 365)),
                    'TrangThai' => 'Hoạt động',
                ],
            );

            DiemTichLuy::updateOrCreate(
                ['KhachHangID' => $record->KhachHangID],
                ['DiemHienTai' => $customer['points'], 'NgayCapNhat' => now()],
            );
        }
    }
}
