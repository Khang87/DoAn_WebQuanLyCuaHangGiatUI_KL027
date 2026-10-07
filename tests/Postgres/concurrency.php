<?php

use App\Models\DonHang;
use App\Models\GiaoNhan;
use App\Models\NhatKyHeThong;
use App\Services\BookingService;
use Illuminate\Support\Facades\DB;

require __DIR__.'/race.php';
pgSeed(); // Committed so independently bootstrapped workers can see it.
$start = $GLOBALS['pg_assertions'] ?? 0;
$booking = pgBooking('Tại nhà', 'Tại nhà');
$payload = ['operation' => 'inspect', 'booking_id' => $booking->getKey(), 'points' => 200];
$results = pgRace('SELECT * FROM "Booking" WHERE "BookingID" = ? FOR UPDATE', [$booking->getKey()], [$payload, $payload]);
pgExpect(array_column($results, 'status'), ['success', 'success'], 'Both Booking requests return successfully');
pgExpect($results[0]['id'], $results[1]['id'], 'Same Booking returns one Order ID');
$order = DonHang::where('BookingID', $booking->getKey())->sole();
pgExpect($order->DiemSuDung, 200, 'One inspected redemption');
pgExpect((int) DB::table('DiemTichLuy')->where('KhachHangID', 1)->value('DiemHienTai'), 300, 'Balance deducted once');
pgExpect($order->chiTietDonHangs()->count(), 1, 'One actual detail line');
pgExpect($order->giaoNhans()->count(), 2, 'One set of receive/return legs');
pgExpect(NhatKyHeThong::where('BangDuLieu', 'Booking')->where('BanGhiID', $booking->getKey())->where('HanhDong', 'Xác nhận Booking')->count(), 1, 'One confirmation audit');

$deliveryOrder = app(BookingService::class)->inspectBookingAndCreateOrder(pgBooking(), 1, pgItems());
$payload = ['operation' => 'delivery', 'order_id' => $deliveryOrder->getKey()];
$results = pgRace('SELECT * FROM "DonHang" WHERE "DonHangID" = ? FOR UPDATE', [$deliveryOrder->getKey()], [$payload, $payload]);
$statuses = array_column($results, 'status');
sort($statuses);
pgExpect($statuses, ['rejected', 'success'], 'Concurrent duplicate leg rejected');
$rejected = array_values(array_filter($results, fn ($result) => $result['status'] === 'rejected'))[0];
pgExpect($rejected['fields'], ['method'], 'Duplicate is rejected by the leg guard');
pgExpect(GiaoNhan::where('DonHangID', $deliveryOrder->getKey())->where('LoaiGiaoNhan', 'GIAO_DO')->where('TrangThai', '!=', 'Đã hủy')->count(), 1, 'One noncancelled delivery leg');

DB::table('DiemTichLuy')->where('KhachHangID', 1)->update(['DiemHienTai' => 500]);
$bookings = [pgBooking('Tại nhà', 'Tại nhà'), pgBooking('Tại nhà', 'Tại nhà')];
$before = [DonHang::count(), DB::table('ChiTietDonHang')->count(), GiaoNhan::count()];
$payloads = array_map(fn ($booking) => ['operation' => 'inspect', 'booking_id' => $booking->getKey(), 'points' => 400], $bookings);
$results = pgRace('SELECT * FROM "DiemTichLuy" WHERE "KhachHangID" = ? FOR UPDATE', [1], $payloads);
$statuses = array_column($results, 'status');
sort($statuses);
pgExpect($statuses, ['rejected', 'success'], 'Only one competing redemption succeeds');
$rejected = array_values(array_filter($results, fn ($result) => $result['status'] === 'rejected'))[0];
pgExpect($rejected['fields'], ['DiemSuDung'], 'Insufficient balance rejected explicitly');
pgExpect((int) DB::table('DiemTichLuy')->where('KhachHangID', 1)->value('DiemHienTai'), 100, 'Shared balance cannot become negative');
pgExpect([DonHang::count(), DB::table('ChiTietDonHang')->count(), GiaoNhan::count()], [$before[0] + 1, $before[1] + 1, $before[2] + 2], 'Rejected redemption leaves no orphan Order/detail/legs');
foreach ($bookings as $booking) {
    $converted = DonHang::where('BookingID', $booking->getKey())->exists();
    pgExpect($booking->fresh()->TrangThai, $converted ? 'DaXacNhan' : 'ChoTiepNhan', 'Loser Booking remains pending');
    pgExpect(NhatKyHeThong::where('BangDuLieu', 'Booking')->where('BanGhiID', $booking->getKey())->where('HanhDong', 'Xác nhận Booking')->count(), $converted ? 1 : 0, 'Rejected redemption has no confirmation audit');
}
echo 'PASS: 3 PostgreSQL races; '.(($GLOBALS['pg_assertions'] ?? 0) - $start)." assertions\n";
