<?php

use App\Enums\OrderStatus;
use App\Models\ChiTietDonHang;
use App\Models\DonHang;
use App\Services\DeliveryService;
use App\Support\QuyenMapper;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

try {
    $app = require __DIR__.'/bootstrap.php';
    $app->make(Kernel::class)->bootstrap();
    require dirname(__DIR__).'/Postgres/support.php';
    $action = $argv[1] ?? '';
    if ($action === 'inactive-voucher') {
        $id = (int) ($argv[2] ?? 0);
        $order = DonHang::findOrFail($id);
        $voucher = DB::table('KhuyenMai')->insertGetId([
            'MaKhuyenMai' => 'INACTIVE-EDIT', 'TenKhuyenMai' => 'Inactive saved voucher',
            'LoaiKhuyenMai' => 'Tiền mặt', 'GiaTriGiam' => 10000,
            'NgayBatDau' => today()->subDay(), 'NgayKetThuc' => today()->addDay(),
            'TrangThai' => 'Ngừng hoạt động',
        ], 'KhuyenMaiID');
        $order->forceFill(['KhuyenMaiID' => $voucher])->saveQuietly();
        echo json_encode(['ok' => true]);
        exit;
    }
    if ($action === 'order-count') {
        echo json_encode(['count' => DB::table('DonHang')->count()]);
        exit;
    }
    if (in_array($action, ['inactivate', 'reactivate'], true)) {
        DB::table('NhanVien')->where('NhanVienID', 1)->update(['TrangThai' => $action === 'inactivate' ? 'Ngừng hoạt động' : 'Hoạt động']);
        echo json_encode(['ok' => true]);
        exit;
    }
    if ($action === 'deny-edit') {
        $ids = DB::table('Quyen')->where('MaQuyen', QuyenMapper::resolveMaQuyen('orders.edit'))->pluck('QuyenID');
        DB::table('VaiTro_Quyen')->where('VaiTroID', 1)->whereIn('QuyenID', $ids)->delete();
        echo json_encode(['ok' => true]);
        exit;
    }
    if ($action !== 'prepare') {
        throw new RuntimeException('Expected isolated fixture action.');
    }
    DB::table('BangGia')->where('DichVuID', 1)->where('TrangThai', 'Hoạt động')->where('DonViTinhID', 2)->update(['DonGia' => 15000]);
    DB::table('BangGia')->where('DichVuID', 1)->where('TrangThai', 'Hoạt động')->where('DonViTinhID', 1)->update(['DonGia' => 40000]);
    DB::table('LoaiDichVu')->insert([['LoaiDichVuID' => 2, 'TenLoaiDichVu' => 'Category B'], ['LoaiDichVuID' => 3, 'TenLoaiDichVu' => 'Empty C']]);
    DB::table('DichVu')->insert([
        ['DichVuID' => 2, 'LoaiDichVuID' => 1, 'TenDichVu' => 'Single piece'],
        ['DichVuID' => 3, 'LoaiDichVuID' => 2, 'TenDichVu' => 'Single KG'],
        ['DichVuID' => 4, 'LoaiDichVuID' => 1, 'TenDichVu' => 'Missing tuple'],
    ]);
    foreach ([[8, 2, 2, 15000], [9, 3, 1, 40000]] as [$id,$service,$unit,$price]) {
        DB::table('BangGia')->insert(['BangGiaID' => $id, 'DichVuID' => $service, 'LoaiDoGiatID' => 1, 'DonViTinhID' => $unit, 'DonGia' => $price, 'NgayApDung' => today(), 'TrangThai' => 'Hoạt động']);
    }
    DB::table('NhanVien')->insert(['NhanVienID' => 3, 'HoTen' => 'Locked other employee', 'SoDienThoai' => '0000000003', 'TrangThai' => 'Khóa']);
    foreach (['bookings.edit', 'promotions.view', 'promotions.create'] as $permission) {
        $id = DB::table('Quyen')->insertGetId(['MaQuyen' => QuyenMapper::resolveMaQuyen($permission), 'TenQuyen' => $permission], 'QuyenID');
        DB::table('VaiTro_Quyen')->insert(['VaiTroID' => 1, 'QuyenID' => $id]);
    }
    $booking = pgBooking();
    $booking->chiTietBookings()->create(['DichVuID' => 1, 'LoaiDoGiatID' => 1, 'DonViTinhID' => 2, 'SoLuong' => 1, 'DonGia' => 15000, 'ThanhTien' => 15000]);
    $legacy = DonHang::create(['MaDonHang' => 'REMAIN-LEG', 'KhachHangID' => 1, 'NhanVienID' => 2, 'TrangThai' => OrderStatus::Pending->value, 'TongTien' => 15000, 'ThanhTien' => 15000]);
    ChiTietDonHang::create(['DonHangID' => $legacy->getKey(), 'DichVuID' => 1, 'LoaiDoGiatID' => 1, 'DonViTinhID' => 2, 'SoLuong' => 1, 'DonGia' => 15000, 'ThanhTien' => 15000]);
    $washed = DonHang::create(['MaDonHang' => 'REMAIN-WASHED', 'KhachHangID' => 1, 'NhanVienID' => 1, 'TrangThai' => OrderStatus::Washed->value]);
    $delivery = app(DeliveryService::class)->create(['order_id' => $washed->getKey(), 'employee_id' => 1, 'method' => 'giao_do', 'address' => 'Local return']);
    DB::table('GiaoNhan')->where('GiaoNhanID', $delivery->getKey())->update(['NhanVienID' => 2]);
    echo json_encode(['booking' => $booking->getKey(), 'legacy' => $legacy->getKey(), 'delivery' => $delivery->getKey()], JSON_THROW_ON_ERROR);
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage()."\n");
    exit(1);
}
