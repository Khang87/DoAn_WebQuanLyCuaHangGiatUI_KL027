<?php

use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Models\DonHang;
use App\Models\HoaDon;
use App\Models\ThanhToan;
use App\Support\PermissionCache;
use Illuminate\Support\Facades\DB;

require __DIR__.'/race.php';
require __DIR__.'/beepay-support.php';
pgSeed();
DB::table('VaiTro')->where('VaiTroID', 1)->update(['TenVaiTro' => 'Chủ cửa hàng']);
PermissionCache::forgetAll();
$orders = [];
foreach ([1, 2, 3] as $id) {
    $orders[] = DonHang::create(['MaDonHang' => 'BEEPAY-PG-'.$id, 'KhachHangID' => 1, 'NhanVienID' => 1,
        'TrangThai' => OrderStatus::Received->value, 'TongTien' => 10000, 'ThanhTien' => 10000]);
}
$response = pgBeePay($orders[0]->getKey(), 'BANK-PG-FIRST');
pgExpect([$response->getStatusCode(), json_decode($response->getContent(), true)], [200, ['ok' => true]], 'Anonymous PostgreSQL signed credit succeeds');
pgExpect(HoaDon::where('DonHangID', $orders[0]->getKey())->value('TrangThai'), InvoiceStatus::Paid->value, 'Invoice collected');
pgExpect((int) DB::table('LichSuThayDoiHoaDon')->where('HoaDonID', $orders[0]->hoaDons()->first()->getKey())->value('TaiKhoanID'), 2, 'Integration actor recorded');
pgExpect(pgBeePay($orders[0]->getKey(), 'BANK-PG-FIRST')->getStatusCode(), 200, 'Exact retry succeeds without duplicate');
pgExpect(ThanhToan::where('MaGiaoDich', 'BANK-PG-FIRST')->count(), 1, 'Exact retry has one credit');
$reference = 'BANK-PG-RACE';
$payloads = array_map(fn ($order) => ['operation' => 'beepay', 'order_id' => $order->getKey(), 'reference' => $reference], [$orders[1], $orders[2]]);
$results = pgRace('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', ['beepay:'.$reference], $payloads);
$statuses = array_column($results, 'status');
sort($statuses);
pgExpect($statuses, ['rejected', 'success'], 'Same bank reference can settle only one of two orders');
pgExpect(ThanhToan::where('MaGiaoDich', $reference)->count(), 1, 'One bank reference creates one credit');
pgExpect(HoaDon::whereIn('DonHangID', [$orders[1]->getKey(), $orders[2]->getKey()])->where('TrangThai', InvoiceStatus::Paid->value)->count(), 1, 'Exactly one invoice settles');
echo 'PASS: BeePay PostgreSQL anonymous audit, retry and two-process cross-order reference lock; '.($GLOBALS['pg_assertions'] ?? 0)." assertions\n";
