<?php

use App\Support\QuyenMapper;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

$app = require __DIR__.'/bootstrap.php';
$app->make(Kernel::class)->bootstrap();
require dirname(__DIR__).'/Postgres/support.php';
try {
    pgSeed();
    DB::table('TaiKhoan')->where('TaiKhoanID', 2)->update(['Email' => 'staff@example.test', 'MatKhau' => Hash::make(getenv('WEB_E2E_PASSWORD'))]);
    foreach (['payments.view', 'orders.view'] as $permission) {
        $id = DB::table('Quyen')->insertGetId(['MaQuyen' => QuyenMapper::resolveMaQuyen($permission), 'TenQuyen' => $permission], 'QuyenID');
        DB::table('VaiTro_Quyen')->insert(['VaiTroID' => 1, 'QuyenID' => $id]);
    }
    $order = DB::table('DonHang')->insertGetId(['MaDonHang' => 'HISTORY-ORDER', 'KhachHangID' => 1, 'NhanVienID' => 1, 'TrangThai' => 'Đã tiếp nhận', 'TongTien' => 1000000, 'ThanhTien' => 1000000], 'DonHangID');
    DB::table('HoaDon')->updateOrInsert(['DonHangID' => $order], ['MaHoaDon' => 'HISTORY-INVOICE', 'TongTien' => 1000000, 'ThanhTien' => 1000000]);
    foreach (range(1, 12) as $number) {
        DB::table('ThanhToan')->insert([
            'DonHangID' => $order, 'SoTien' => 15000, 'PhuongThuc' => $number % 2 ? 'Tiền mặt' : 'Chuyển khoản',
            'TrangThai' => ['Thành công', 'Thất bại', 'Chờ thanh toán', 'Đã hoàn tiền'][($number - 1) % 4],
            'MaGiaoDich' => $number === 12 ? 'CK_DH0001_20261011123000' : 'HISTORY-TX-'.$number, 'ThoiGian' => '2026-10-10 23:59:59',
            'GhiChu' => '<img src=x onerror="window.historyXss=true">',
        ]);
    }
    echo "PASS: isolated payment history fixture seeded\n";

} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage()."\n");
    exit(1);
}
