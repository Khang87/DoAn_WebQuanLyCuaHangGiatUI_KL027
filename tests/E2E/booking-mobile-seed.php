<?php

use App\Support\QuyenMapper;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

$app = require __DIR__.'/bootstrap.php';
$app->make(Kernel::class)->bootstrap();
require dirname(__DIR__).'/Postgres/support.php';
pgSeed();
DB::table('LoaiDichVu')->insert(['LoaiDichVuID' => 1, 'TenLoaiDichVu' => 'Local laundry']);
DB::table('DanhMucLoaiDoGiat')->insert(['DanhMucID' => 1, 'TenDanhMuc' => 'Local clothing']);
DB::table('TaiKhoan')->where('TaiKhoanID', 2)->update(['Email' => 'staff@example.test', 'MatKhau' => Hash::make(getenv('WEB_E2E_PASSWORD'))]);
DB::table('KhachHang')->where('KhachHangID', 1)->update(['HoTen' => 'Khách mobile', 'SoDienThoai' => '0900000001', 'Email' => 'mobile@example.test', 'DiaChi' => 'Địa chỉ hồ sơ mobile']);
foreach (['orders.view', 'orders.create', 'bookings.view', 'bookings.confirm', 'payments.create'] as $permission) {
    $id = DB::table('Quyen')->insertGetId(['MaQuyen' => QuyenMapper::resolveMaQuyen($permission), 'TenQuyen' => $permission], 'QuyenID');
    DB::table('VaiTro_Quyen')->insert(['VaiTroID' => 1, 'QuyenID' => $id]);
}
$booking = pgBooking('Tại nhà', 'Tại nhà');
$booking->forceFill([
    'NhanVienID' => 1, 'HinhThucTraDo' => null, 'DiaChiTra' => null,
    'HinhThucGiaoDo' => 'Tại nhà', 'DiaChiGiao' => 'Địa chỉ giao mobile',
    'PickupDeliveryFee' => 11111, 'DeliveryFee' => 22222,
    'PickupDistanceMeters' => 1234, 'DeliveryDistanceMeters' => 5678,
    'PhuongThucThanhToan' => 'Chuyển khoản', 'GhiChu' => 'Ghi chú từ mobile',
])->saveQuietly();
$booking->chiTietBookings()->create(['DichVuID' => 1, 'LoaiDoGiatID' => 1, 'DonViTinhID' => 2, 'SoLuong' => 1, 'DonGia' => 10000, 'ThanhTien' => 10000, 'GhiChu' => 'Ghi chú đồ từ mobile']);
$storeBooking = pgBooking('Tại nhà', 'Tại nhà');
$storeBooking->forceFill($booking->only([
    'NhanVienID', 'HinhThucGiaoDo', 'DiaChiGiao', 'PickupDeliveryFee', 'DeliveryFee',
    'PickupDistanceMeters', 'DeliveryDistanceMeters', 'PhuongThucThanhToan',
]))->forceFill(['HinhThucTraDo' => null, 'DiaChiTra' => null])->saveQuietly();
$storeBooking->chiTietBookings()->create(['DichVuID' => 1, 'LoaiDoGiatID' => 1, 'DonViTinhID' => 2, 'SoLuong' => 1, 'DonGia' => 10000, 'ThanhTien' => 10000]);
file_put_contents(getenv('WEB_E2E_RUNTIME').'/cases.json', json_encode(['booking' => $booking->getKey(), 'storeBooking' => $storeBooking->getKey()], JSON_THROW_ON_ERROR));
echo "PASS: isolated mobile Booking snapshot seeded\n";
