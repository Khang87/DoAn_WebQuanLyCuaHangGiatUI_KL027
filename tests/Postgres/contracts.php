<?php

use App\Exceptions\SettledOrderException;
use App\Models\Booking;
use App\Models\DonHang;
use App\Models\KhuyenMai;
use App\Models\NhatKyHeThong;
use App\Services\BookingService;
use App\Services\DeliveryService;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Services\PricingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

DB::beginTransaction();
try {
    // A loopback database with the right name still needs the local fixture marker.
    // Probe from a separate process before running any business tests.
    $pdo->exec("COMMENT ON TABLE public.test_fixture_identity IS 'wrong fixture'");
    try {
        $process = proc_open([PHP_BINARY, __DIR__.'/bootstrap.php'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (! is_resource($process)) {
            throw new RuntimeException('Cannot start fixture safety probe.');
        }
        $output = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        pgExpect(proc_close($process) !== 0 && str_contains($output, 'fixture identity missing'), true, 'Wrong fixture marker refuses Laravel bootstrap');
    } finally {
        $pdo->exec("COMMENT ON TABLE public.test_fixture_identity IS 'laundry verification fixture v1'");
    }
    pgSeed();
    foreach ([[1, 1.5, 7500.0, 2500.0], [1, 4.2, 10500.0, 2500.0], [2, 2, 20000.0, 10000.0]] as [$unit, $measurement, $total, $price]) {
        pgActor(1);
        $key = (string) Str::uuid();
        $cart = json_encode([['banggiaid' => $unit === 1 ? 1 : 5, 'measurement' => $measurement]]);
        $query = "SELECT public.submit_laundry_order_cart_with_points(?::jsonb, 'Tại cửa hàng', NULL, current_date+1, '10:00', NULL, ?::uuid, true) AS result";
        $result = json_decode(DB::selectOne($query, [$cart, $key])->result, true);
        $booking = Booking::findOrFail($result['bookingid']);
        pgExpect((float) $result['thanhtien'], $total, 'RPC effective triple/minimum/date boundaries');
        pgExpect((int) DB::table('DiemTichLuy')->where('KhachHangID', 1)->value('DiemHienTai'), 500, 'RPC point intent does not redeem');
        pgExpect($booking->DiemDaTru, false, 'RPC reservation flag is false');
        $replay = json_decode(DB::selectOne($query, [$cart, $key])->result, true);
        pgExpect($replay['bookingid'], $result['bookingid'], 'RPC replay reuses Booking');
        pgExpect($booking->chiTietBookings()->count(), 1, 'RPC replay keeps one estimated line');
        pgActor(3);
        pgReject(fn () => DB::selectOne($query, [$cart, $key]), 'Different customer cannot reuse idempotency key', sqlState: 'P0001');
        pgActor(2);
        pgReject(fn () => DB::selectOne('SELECT public.confirm_laundry_booking(?)', [$booking->getKey()]), 'RPC cannot bypass inspection', sqlState: '22023');
        pgExpect(app(PricingService::class)->getLatestPrice(1, 1, $unit), $price, 'Web and RPC select the same effective triple');
        $order = app(BookingService::class)->inspectBookingAndCreateOrder($booking, 1, pgItems($unit, $measurement));
        pgExpect($order->TrangThai, 'Đã tiếp nhận', 'Inspection precedes washing');
        pgExpect($order->TongTien, $total, 'Web ignores tampered client price');
        pgExpect($order->chiTietDonHangs()->sole()->DonGia, $price, 'Web stores canonical price');
        pgExpect($order->chiTietDonHangs()->sole()->TinhTrangTruocKhiGiat, 'Local inspected condition', 'Actual condition persisted');
        pgExpect((float) $booking->chiTietBookings()->sole()->ThanhTien, $total, 'Estimated Booking lines preserved');
        $confirmed = json_decode(DB::selectOne('SELECT public.confirm_laundry_booking(?) AS result', [$booking->getKey()])->result, true);
        pgExpect((int) $confirmed['donhangid'], $order->getKey(), 'RPC replay after Web inspection returns existing Order');
    }
    foreach (['Tại cửa hàng', 'Tại nhà'] as $receive) {
        foreach (['Tại cửa hàng', 'Tại nhà'] as $return) {
            $order = app(BookingService::class)->inspectBookingAndCreateOrder(pgBooking($receive, $return), 1, pgItems());
            $legs = array_filter([$receive === 'Tại nhà' ? 'NHAN_DO' : null, $return === 'Tại nhà' ? 'GIAO_DO' : null]);
            $expected = array_values($legs);
            sort($expected);
            pgExpect($order->giaoNhans()->orderBy('LoaiGiaoNhan')->pluck('LoaiGiaoNhan')->all(), $expected, 'Only home legs created for '.$receive.'/'.$return);
            if ($return === 'Tại nhà') {
                pgExpect($order->giaoNhans()->where('LoaiGiaoNhan', 'GIAO_DO')->sole()->ThoiGianDuKien, null, 'Return schedule is nullable and independent');
            }
        }
    }
    $booking = pgBooking();
    $before = DonHang::count();
    pgReject(fn () => app(BookingService::class)->inspectBookingAndCreateOrder($booking, 2, pgItems()), 'Inactive staff cannot receive assignment', 'NhanVienID');
    pgExpect(DonHang::count(), $before, 'Rejected assignment creates no Order');

    // Redeemed points cover only services after voucher, never either delivery fee.
    DB::table('DiemTichLuy')->where('KhachHangID', 1)->update(['DiemHienTai' => 100000]);
    $promotion = KhuyenMai::create(['MaKhuyenMai' => 'LOCAL-VOUCHER', 'TenKhuyenMai' => 'Local voucher', 'LoaiKhuyenMai' => 'Tiền mặt', 'GiaTriGiam' => 10000, 'NgayBatDau' => today(), 'NgayKetThuc' => today()->addDay(), 'TrangThai' => 'Hoạt động']);
    $booking = pgBooking('Tại nhà', 'Tại nhà');
    DB::table('Booking')->where('BookingID', $booking->getKey())->update(['KhuyenMaiID' => $promotion->getKey(), 'PickupDeliveryFee' => 10000, 'DeliveryFee' => 20000]);
    $order = app(BookingService::class)->inspectBookingAndCreateOrder($booking->fresh(), 1, pgItems(2, 10), 100000);
    pgExpect([$order->TongTien, $order->TienGiamKhuyenMai, $order->DiemSuDung, $order->TienGiamDoDiem, $order->PhiGiaoHang, $order->ThanhTien], [100000.0, 10000.0, 90000, 90000.0, 30000.0, 30000.0], 'Voucher precedes points; both fees remain payable');
    pgExpect((int) DB::table('DiemTichLuy')->where('KhachHangID', 1)->value('DiemHienTai'), 10000, 'Actual redemption capped by remaining services');

    // Staff may record a prepayment; customers must wait until delivery.
    $prepaid = app(BookingService::class)->inspectBookingAndCreateOrder(pgBooking(), 1, pgItems());
    app(PaymentService::class)->create(['order_id' => $prepaid->getKey(), 'amount' => 1000, 'method' => 'cash', 'status' => 'Thành công']);
    pgExpect($prepaid->fresh()->TrangThai, 'Đã tiếp nhận', 'Staff prepayment cannot settle an undelivered order');
    pgActor(1);
    pgReject(fn () => DB::selectOne("SELECT public.request_order_payment(?, 'Tiền mặt', ?::uuid)", [$prepaid->getKey(), (string) Str::uuid()]), 'Customer cannot request payment before delivery', sqlState: 'P0001');
    pgActor(2);
    pgReject(fn () => app(OrderService::class)->updateStatus($prepaid, 'Đã giao'), 'Web cannot skip washing', 'TrangThai');
    pgReject(fn () => DB::selectOne("SELECT public.transition_laundry_order(?, 'Đã giao', NULL)", [$prepaid->getKey()]), 'RPC cannot skip washing', sqlState: 'P0001');
    app(OrderService::class)->updateStatus($prepaid, 'Đang giặt');
    DB::selectOne("SELECT public.transition_laundry_order(?, 'Hoàn thành giặt', NULL)", [$prepaid->getKey()]);
    app(OrderService::class)->updateStatus($prepaid->fresh(), 'Đã giao');
    DB::selectOne("SELECT public.transition_laundry_order(?, 'Đã giao', NULL)", [$prepaid->getKey()]);
    pgExpect(NhatKyHeThong::where('BangDuLieu', 'DonHang')->where('BanGhiID', $prepaid->getKey())->where('HanhDong', 'Cộng điểm tích lũy đơn hàng')->count(), 1, 'Web/RPC share one completion award marker');
    pgActor(3);
    pgReject(fn () => DB::selectOne("SELECT public.request_order_payment(?, 'Tiền mặt', ?::uuid)", [$prepaid->getKey(), (string) Str::uuid()]), 'Other customer cannot pay this order', sqlState: 'P0001');
    pgActor(1);
    $payment = json_decode(DB::selectOne("SELECT public.request_order_payment(?, 'Tiền mặt', ?::uuid) AS result", [$prepaid->getKey(), (string) Str::uuid()])->result, true);
    pgExpect((float) DB::table('ThanhToan')->where('ThanhToanID', $payment['thanhtoanid'])->value('SoTien'), 9000.0, 'RPC collects only remaining amount after Web prepayment');
    pgActor(2);
    DB::selectOne('SELECT public.confirm_order_payment(?, true, NULL)', [$payment['thanhtoanid']]);
    DB::selectOne('SELECT public.confirm_order_payment(?, true, NULL)', [$payment['thanhtoanid']]);
    pgExpect($prepaid->fresh()->TrangThai, 'Đã thanh toán', 'Delivered and fully collected order settles');
    pgExpect((float) $prepaid->thanhToans()->where('TrangThai', 'Thành công')->sum('SoTien'), 10000.0, 'Confirmation replay cannot collect twice');
    try {
        app(DeliveryService::class)->create(['order_id' => $prepaid->getKey(), 'method' => 'giao_do', 'address' => 'Local return']);
        throw new RuntimeException('Paid order unexpectedly accepted delivery mutation');
    } catch (SettledOrderException) {
        pgExpect($prepaid->giaoNhans()->count(), 0, 'Paid parent rejects delivery creation');
    }
    $cancelled = app(BookingService::class)->inspectBookingAndCreateOrder(pgBooking(), 1, pgItems(), 200);
    $balance = (int) DB::table('DiemTichLuy')->where('KhachHangID', 1)->value('DiemHienTai');
    app(OrderService::class)->updateStatus($cancelled, 'Đã hủy', false, 'Local test cancellation');
    DB::selectOne("SELECT public.transition_laundry_order(?, 'Đã hủy', 'Replay')", [$cancelled->getKey()]);
    pgExpect((int) DB::table('DiemTichLuy')->where('KhachHangID', 1)->value('DiemHienTai'), $balance + 200, 'Web cancellation/RPC replay refund exactly once');
    pgExpect(NhatKyHeThong::where('BangDuLieu', 'DonHang')->where('BanGhiID', $cancelled->getKey())->where('HanhDong', 'Hoàn điểm tích lũy đơn hàng')->count(), 1, 'Shared refund marker');
    pgReject(fn () => app(DeliveryService::class)->create(['order_id' => $cancelled->getKey(), 'method' => 'giao_do', 'address' => 'Local return']), 'Cancelled parent rejects delivery mutation', 'order_id');
} finally {
    DB::rollBack();
}
echo 'PASS: Web/RPC contract assertions ('.($GLOBALS['pg_assertions'] ?? 0).")\n";
