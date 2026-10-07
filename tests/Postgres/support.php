<?php

use App\Models\Booking;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

function pgExpect(mixed $actual, mixed $expected, string $label): void
{
    if ($actual !== $expected) {
        throw new RuntimeException($label.': expected '.json_encode($expected).', got '.json_encode($actual));
    }
    $GLOBALS['pg_assertions'] = ($GLOBALS['pg_assertions'] ?? 0) + 1;
}

function pgReject(callable $operation, string $label, ?string $field = null, ?string $sqlState = null): void
{
    try {
        DB::transaction($operation); // Savepoint preserves an outer contract-test transaction.
    } catch (ValidationException $exception) {
        if ($field === null || ! array_key_exists($field, $exception->errors())) {
            throw $exception;
        }
        pgExpect(true, true, $label);

        return;
    } catch (QueryException $exception) {
        if ($sqlState === null || ($exception->errorInfo[0] ?? null) !== $sqlState) {
            throw $exception;
        }
        pgExpect(true, true, $label);

        return;
    }
    throw new RuntimeException($label.': operation unexpectedly succeeded');
}

function pgActor(int $accountId): void
{
    Auth::setUser(User::findOrFail($accountId));
    DB::selectOne("SELECT set_config('test.user_id', ?, false)", [sprintf('00000000-0000-0000-0000-%012d', $accountId)]);
}

function pgSeed(): void
{
    DB::table('KhachHang')->insert([['KhachHangID' => 1, 'HoTen' => 'Local customer'], ['KhachHangID' => 3, 'HoTen' => 'Other customer']]);
    DB::table('NhanVien')->insert([
        ['NhanVienID' => 1, 'HoTen' => 'Local staff', 'SoDienThoai' => '0000000000', 'TrangThai' => 'Hoạt động'],
        ['NhanVienID' => 2, 'HoTen' => 'Inactive staff', 'SoDienThoai' => '0000000001', 'TrangThai' => 'Nghỉ việc'],
    ]);
    foreach ([1 => [1, null], 2 => [null, 1], 3 => [3, null]] as $id => [$customer, $employee]) {
        DB::table('TaiKhoan')->insert(['TaiKhoanID' => $id, 'TenDangNhap' => 'local-'.$id, 'KhachHangID' => $customer, 'NhanVienID' => $employee, 'UserAuthId' => sprintf('00000000-0000-0000-0000-%012d', $id)]);
    }
    DB::table('VaiTro')->insert(['VaiTroID' => 1, 'TenVaiTro' => 'Nhân viên']);
    DB::table('TaiKhoan_VaiTro')->insert(['TaiKhoanID' => 2, 'VaiTroID' => 1]);
    DB::table('DiemTichLuy')->insert(['KhachHangID' => 1, 'DiemHienTai' => 500]);
    DB::table('DichVu')->insert(['DichVuID' => 1, 'LoaiDichVuID' => 1, 'TenDichVu' => 'Local service']);
    DB::table('LoaiDoGiat')->insert(['LoaiDoGiatID' => 1, 'DanhMucID' => 1, 'TenLoaiDoGiat' => 'Local garment']);
    DB::table('DonViTinh')->insert([['DonViTinhID' => 1, 'TenDonViTinh' => 'Kilogram', 'KyHieu' => 'kg'], ['DonViTinhID' => 2, 'TenDonViTinh' => 'Piece', 'KyHieu' => 'cai']]);
    $today = today();
    foreach ([[1, 1, 1000, -10, null, 'Hoạt động'], [2, 1, 2000, 0, null, 'Hoạt động'], [3, 1, 99999, 1, null, 'Hoạt động'], [4, 1, 99999, -1, -1, 'Hoạt động'], [5, 2, 10000, 0, 0, 'Hoạt động'], [6, 1, 2500, 0, 0, 'Hoạt động'], [7, 1, 99999, 0, null, 'Ngừng hoạt động']] as [$id, $unit, $price, $start, $end, $status]) {
        DB::table('BangGia')->insert(['BangGiaID' => $id, 'DichVuID' => 1, 'LoaiDoGiatID' => 1, 'DonViTinhID' => $unit, 'DonGia' => $price, 'NgayApDung' => $today->copy()->addDays($start)->toDateString(), 'NgayKetThuc' => $end === null ? null : $today->copy()->addDays($end)->toDateString(), 'TrangThai' => $status]);
    }
    pgActor(2);
}

function pgItems(int $unit = 2, float $measurement = 1): array
{
    return [['DichVuID' => 1, 'LoaiDoGiatID' => 1, 'DonViTinhID' => $unit,
        'SoLuong' => $unit === 2 ? $measurement : null, 'KhoiLuong' => $unit === 1 ? $measurement : null,
        'TinhTrangTruocKhiGiat' => 'Local inspected condition', 'DonGia' => 1]];
}

function pgBooking(string $receive = 'Tại cửa hàng', string $return = 'Tại cửa hàng'): Booking
{
    return Booking::create(['MaBooking' => 'LOCAL-'.bin2hex(random_bytes(6)), 'KhachHangID' => 1,
        'HinhThucNhanDo' => $receive, 'DiaChiNhan' => $receive === 'Tại nhà' ? 'Local pickup' : null,
        'HinhThucTraDo' => $return, 'DiaChiTra' => $return === 'Tại nhà' ? 'Local return' : null,
        'NgayHen' => today()->addDay(), 'GioHen' => '10:00', 'TrangThai' => 'ChoTiepNhan']);
}
