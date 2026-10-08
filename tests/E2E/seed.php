<?php

use App\Services\BookingService;
use App\Services\DeliveryService;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Support\QuyenMapper;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

try {
    $app = require __DIR__.'/bootstrap.php';
    $app->make(Kernel::class)->bootstrap();
    require dirname(__DIR__).'/Postgres/support.php';
    pgSeed();
    DB::table('LoaiDichVu')->insert(['LoaiDichVuID' => 1, 'TenLoaiDichVu' => 'Local laundry']);
    DB::table('DanhMucLoaiDoGiat')->insert(['DanhMucID' => 1, 'TenDanhMuc' => 'Local clothing']);
    DB::table('TaiKhoan')->where('TaiKhoanID', 2)->update(['Email' => 'staff@example.test', 'MatKhau' => Hash::make(getenv('WEB_E2E_PASSWORD'))]);
    $permissions = ['orders.view', 'orders.create', 'orders.edit', 'orders.update_status', 'bookings.view', 'bookings.confirm', 'deliveries.view', 'deliveries.create', 'deliveries.edit'];
    foreach ($permissions as $permission) {
        $id = DB::table('Quyen')->insertGetId(['MaQuyen' => QuyenMapper::resolveMaQuyen($permission), 'TenQuyen' => $permission], 'QuyenID');
        DB::table('VaiTro_Quyen')->insert(['VaiTroID' => 1, 'QuyenID' => $id]);
    }
    DB::table('DiemTichLuy')->where('KhachHangID', 1)->update(['DiemHienTai' => 100000]);
    $cases = [];
    foreach (['store' => ['Tại cửa hàng', 'Tại cửa hàng'], 'pickup' => ['Tại nhà', 'Tại cửa hàng'], 'return' => ['Tại cửa hàng', 'Tại nhà'], 'both' => ['Tại nhà', 'Tại nhà'], 'replay' => ['Tại nhà', 'Tại nhà'], 'kg' => ['Tại cửa hàng', 'Tại cửa hàng'], 'voucher' => ['Tại nhà', 'Tại nhà']] as $name => [$receive, $return]) {
        $booking = pgBooking($receive, $return);
        $booking->chiTietBookings()->create(['DichVuID' => 1, 'LoaiDoGiatID' => 1, 'DonViTinhID' => 2, 'SoLuong' => 1, 'DonGia' => 10000, 'ThanhTien' => 10000, 'GhiChu' => 'Original estimate']);
        $cases[$name] = $booking->getKey();
    }
    $promotion = DB::table('KhuyenMai')->insertGetId(['MaKhuyenMai' => 'E2E-VOUCHER', 'TenKhuyenMai' => 'Local voucher', 'LoaiKhuyenMai' => 'Tiền mặt', 'GiaTriGiam' => 10000, 'NgayBatDau' => today(), 'NgayKetThuc' => today()->addDay(), 'TrangThai' => 'Hoạt động'], 'KhuyenMaiID');
    DB::table('Booking')->where('BookingID', $cases['voucher'])->update(['KhuyenMaiID' => $promotion, 'PickupDeliveryFee' => 10000, 'DeliveryFee' => 20000]);
    $paid = app(BookingService::class)->inspectBookingAndCreateOrder(pgBooking(), 1, pgItems());
    app(DeliveryService::class)->create(['order_id' => $paid->getKey(), 'method' => 'giao_do', 'address' => 'Local paid return']);
    app(OrderService::class)->updateStatus($paid, 'Đang giặt');
    app(OrderService::class)->updateStatus($paid->fresh(), 'Hoàn thành giặt');
    app(OrderService::class)->updateStatus($paid->fresh(), 'Đã giao');
    app(PaymentService::class)->create(['order_id' => $paid->getKey(), 'amount' => $paid->fresh()->ThanhTien, 'method' => 'cash', 'status' => 'Thành công']);
    $cases['paid'] = $paid->getKey();
    $legacy = DB::table('DonHang')->insertGetId(['MaDonHang' => 'E2E-LEGACY', 'KhachHangID' => 1, 'NhanVienID' => 1, 'TrangThai' => 'Chờ tiếp nhận', 'TongTien' => 10000, 'ThanhTien' => 10000], 'DonHangID');
    DB::table('ChiTietDonHang')->insert(['DonHangID' => $legacy, 'DichVuID' => 1, 'LoaiDoGiatID' => 1, 'DonViTinhID' => 2, 'SoLuong' => 1, 'DonGia' => 10000, 'ThanhTien' => 10000]);
    $cases['legacy'] = $legacy;
    file_put_contents(getenv('WEB_E2E_RUNTIME').'/cases.json', json_encode($cases, JSON_THROW_ON_ERROR));
    echo "PASS: isolated browser fixture seeded\n";
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage()."\n");
    exit(1);
}
