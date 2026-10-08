<?php

try {
    [, , , $pdo] = require dirname(__DIR__).'/Postgres/connection.php';
    $pdo->exec("SET statement_timeout = '10s'");
    $id = $argv[2] ?? '';
    if (! ctype_digit($id) || ! in_array($argv[1] ?? '', ['booking', 'order'], true)) {
        throw new RuntimeException('State probe requires booking/order and numeric ID.');
    }
    $rows = static function (string $sql, array $args = []) use ($pdo): array {
        $query = $pdo->prepare($sql);
        $query->execute($args);

        return $query->fetchAll(PDO::FETCH_ASSOC);
    };
    $order = $rows('SELECT * FROM "DonHang" WHERE '.($argv[1] === 'booking' ? '"BookingID"' : '"DonHangID"').' = ?', [$id]);
    $orderId = $order[0]['DonHangID'] ?? 0;
    $bookingId = $argv[1] === 'booking' ? (int) $id : ($order[0]['BookingID'] ?? 0);
    $state = [
        'orders' => $order,
        'booking' => $rows('SELECT * FROM "Booking" WHERE "BookingID" = ?', [$bookingId]),
        'estimate' => $rows('SELECT * FROM "ChiTietBooking" WHERE "BookingID" = ? ORDER BY "ChiTietBookingID"', [$bookingId]),
        'details' => $rows('SELECT * FROM "ChiTietDonHang" WHERE "DonHangID" = ? ORDER BY "ChiTietDonHangID"', [$orderId]),
        'legs' => $rows('SELECT * FROM "GiaoNhan" WHERE "DonHangID" = ? ORDER BY "LoaiGiaoNhan"', [$orderId]),
        'payments' => $rows('SELECT * FROM "ThanhToan" WHERE "DonHangID" = ? ORDER BY "ThanhToanID"', [$orderId]),
        'audit' => $rows('SELECT * FROM "NhatKyHeThong" WHERE ("BangDuLieu" = ? AND "BanGhiID" = ?) OR ("BangDuLieu" = ? AND "BanGhiID" = ?) ORDER BY "NhatKyID"', ['Booking', $bookingId, 'DonHang', $orderId]),
        'balance' => (int) $rows('SELECT "DiemHienTai" FROM "DiemTichLuy" WHERE "KhachHangID" = 1')[0]['DiemHienTai'],
    ];
    echo json_encode($state, JSON_THROW_ON_ERROR);
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage()."\n");
    exit(1);
}
