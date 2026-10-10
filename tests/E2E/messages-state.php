<?php

use App\Support\QuyenMapper;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$app = require __DIR__.'/bootstrap.php';
$app->make(Kernel::class)->bootstrap();
$cases = json_decode(file_get_contents(getenv('WEB_E2E_RUNTIME').'/cases.json'), true, flags: JSON_THROW_ON_ERROR);
$action = $argv[1] ?? '';
if ($action === 'realtime-insert') {
    DB::table('realtime.test_signals')->truncate();
    DB::table('TinNhan')->insert(['NguoiGuiID' => 1, 'NguoiNhanID' => 2, 'DonHangID' => $cases['paid'],
        'NoiDung' => 'Realtime mobile message <img src=x>', 'ThoiGianGui' => now(), 'TrangThai' => 'Đã gửi']);
    echo json_encode(DB::table('realtime.test_signals')->get());
    exit;
}
if ($action === 'read-status') {
    echo json_encode(DB::table('TinNhan')->whereIn('DonHangID', [$cases['paid'], $cases['legacy']])->where('NguoiGuiID', 1)->get(['DonHangID', 'TrangThai']));
    exit;
}
if ($action === 'notification') {
    $id = DB::table('ThongBao')->insertGetId(['TaiKhoanID' => 2, 'LoaiThongBao' => 'new_message', 'TieuDe' => '<img src=x onerror=alert(1)> New mobile notification', 'NoiDung' => 'Fixture only', 'ThoiGianGui' => now(), 'DaDoc' => false], 'ThongBaoID');
    echo json_encode(['id' => $id]);
    exit;
}
if ($action === 'dashboard-cleanup') {
    $ids = DB::table('Quyen')->whereIn('MaQuyen', [QuyenMapper::resolveMaQuyen('dashboard.view'), QuyenMapper::resolveMaQuyen('payments.view')])->pluck('QuyenID');
    DB::table('VaiTro_Quyen')->where('VaiTroID', 1)->whereIn('QuyenID', $ids)->delete();
    DB::table('DanhGia')->where('DonHangID', $cases['paid'])->where('SoSao', 4)->delete();
    DB::table('KhachHang')->where('HoTen', 'New KPI customer')->delete();
    DB::table('DichVu')->where('DichVuID', 2)->where('TenDichVu', 'Unpriced fixture service')->delete();
    echo 'Fixture restored';
    exit;
}
if ($action === 'dashboard-access') {
    foreach (['dashboard.view', 'payments.view'] as $permission) {
        $id = DB::table('Quyen')->insertGetId(['MaQuyen' => QuyenMapper::resolveMaQuyen($permission), 'TenQuyen' => $permission], 'QuyenID');
        DB::table('VaiTro_Quyen')->insert(['VaiTroID' => 1, 'QuyenID' => $id]);
    }
    DB::table('TaiKhoan')->where('TaiKhoanID', 1)->update(['AvatarURL' => getenv('WEB_E2E_URL').'/assets/images/user_2.jpg']);
    DB::table('DichVu')->insert(['DichVuID' => 2, 'LoaiDichVuID' => 1, 'TenDichVu' => 'Unpriced fixture service']);
    echo 'Fixture access granted';
    exit;
}
if ($action === 'dashboard-change') {
    DB::table('KhachHang')->insert(['KhachHangID' => (int) DB::table('KhachHang')->max('KhachHangID') + 1, 'HoTen' => 'New KPI customer']);
    DB::table('DanhGia')->insert(['DonHangID' => $cases['paid'], 'KhachHangID' => 1, 'SoSao' => 4, 'TrangThai' => 'Hiển thị', 'NgayDanhGia' => now()]);
    echo 'KPI fixtures changed';
    exit;
}
if ($action === 'support-count') {
    echo json_encode(['count' => DB::table('TinNhan')->whereNull('DonHangID')->where('NoiDung', 'Unique support reply')->count()]);
    exit;
}
if ($action !== 'insert') {
    throw new RuntimeException('Expected isolated message fixture insertion');
}
foreach (['paid' => '<img src=x onerror="window.messageXss=true"> Mobile message', 'legacy' => 'Foreign conversation message'] as $key => $content) {
    DB::table('TinNhan')->insert(['NguoiGuiID' => 1, 'NguoiNhanID' => 2, 'DonHangID' => $cases[$key], 'NoiDung' => $content, 'ThoiGianGui' => now('UTC')->addSeconds($key === 'legacy' ? 1 : 0), 'TrangThai' => 'Đã gửi']);
}
echo "Fixture messages inserted\n";
