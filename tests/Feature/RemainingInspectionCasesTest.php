<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\OrderStatus;
use App\Http\Middleware\EnsureUserHasPermission;
use App\Http\Middleware\RejectCustomerRole;
use App\Http\Middleware\RestoreRememberedLogin;
use App\Models\ChiTietDonHang;
use App\Models\DonHang;
use App\Models\User;
use App\Services\BookingService;
use App\Services\OrderService;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\BookingInspectionFixture;
use Tests\TestCase;

class RemainingInspectionCasesTest extends TestCase
{
    use BookingInspectionFixture;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        $this->createSchema();
        $this->createRequestCatalog();
        $this->actingAsBookingEmployee();
        $this->withoutMiddleware([Authenticate::class, EnsureUserHasPermission::class, RejectCustomerRole::class, RestoreRememberedLogin::class]);
        $this->travelTo(now()->setDate(2026, 10, 9));
        DB::table('BangGia')->insert([
            ['DichVuID' => 1, 'LoaiDoGiatID' => 2, 'DonViTinhID' => 2, 'DonGia' => 15000, 'NgayApDung' => '2026-01-01', 'TrangThai' => 'Hoạt động'],
            ['DichVuID' => 1, 'LoaiDoGiatID' => 2, 'DonViTinhID' => 1, 'DonGia' => 40000, 'NgayApDung' => '2026-01-01', 'TrangThai' => 'Hoạt động'],
        ]);
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        foreach (['KhuyenMai', 'NhatKyHeThong', 'ChiTietBooking', 'ChiTietDonHang', 'GiaoNhan', 'HoaDon', 'ThanhToan', 'DiemTichLuy', 'DonHang', 'Booking', 'BangGia', 'DonViTinh', 'LoaiDoGiat', 'DanhMucLoaiDoGiat', 'DichVu', 'LoaiDichVu', 'NhanVien', 'TaiKhoan', 'KhachHang'] as $table) {
            Schema::dropIfExists($table);
        }
        parent::tearDown();
    }

    private function row(array $changes = []): array
    {
        return array_merge(['DichVuID' => 1, 'LoaiDoGiatID' => 2, 'DonViTinhID' => 2, 'SoLuong' => 2, 'TinhTrangTruocKhiGiat' => 'Áo bẩn', 'DonGia' => 1], $changes);
    }

    private function snapshot(): array
    {
        return array_map(fn ($t) => DB::table($t)->get()->toJson(), ['DonHang', 'ChiTietDonHang', 'Booking', 'DiemTichLuy', 'NhatKyHeThong']);
    }

    public static function invalidRows(): array
    {
        return [
            '29 empty items' => [[], 'items'],
            '30 missing condition' => [[['TinhTrangTruocKhiGiat' => null]], 'items.0.TinhTrangTruocKhiGiat'],
            '30 empty condition' => [[['TinhTrangTruocKhiGiat' => '']], 'items.0.TinhTrangTruocKhiGiat'],
            '30 whitespace condition' => [[['TinhTrangTruocKhiGiat' => '   ']], 'items.0.TinhTrangTruocKhiGiat'],
            '31 too long condition' => [[['TinhTrangTruocKhiGiat' => str_repeat('a', 321)]], 'items.0.TinhTrangTruocKhiGiat'],
            '32 zero pieces' => [[['SoLuong' => 0]], 'items.0.SoLuong'],
            '32 negative pieces' => [[['SoLuong' => -1]], 'items.0.SoLuong'],
            '32 fractional pieces' => [[['SoLuong' => 1.5]], 'items.0.SoLuong'],
            '33 zero kg' => [[['DonViTinhID' => 1, 'SoLuong' => null, 'KhoiLuong' => 0]], 'items.0.SoLuong'],
            '33 negative kg' => [[['DonViTinhID' => 1, 'SoLuong' => null, 'KhoiLuong' => -1]], 'items.0.KhoiLuong'],
            '33 two measurements' => [[['DonViTinhID' => 1, 'SoLuong' => 2, 'KhoiLuong' => 1.5]], 'items.0.SoLuong'],
            '19 missing price tuple' => [[['DichVuID' => 2]], 'items.0.DonViTinhID'],
        ];
    }

    #[DataProvider('invalidRows')]
    public function test_create_and_inspection_requests_reject_invalid_rows(array $changes, string $field): void
    {
        DB::table('DichVu')->insert(['DichVuID' => 2]);
        $items = array_map(fn ($c) => $this->row($c), $changes);
        $before = $this->snapshot();
        $this->post(route('orders.store'), ['KhachHangID' => 9, 'NhanVienID' => 1, 'TrangThai' => OrderStatus::Received->value, 'items' => $items])->assertSessionHasErrors($field);
        $this->assertSame($before, $this->snapshot());
        $booking = $this->createBooking(['HinhThucNhanDo' => 'Tại cửa hàng']);
        $payload = $this->inspectionPayload($booking, 1);
        $payload['items'] = $items;
        $before = $this->snapshot();
        $this->post(route('bookings.confirm', $booking), $payload)->assertSessionHasErrors($field);
        $this->assertSame($before, $this->snapshot());
    }

    public function test_20_and_31_both_requests_accept_320_chars_and_ignore_client_price(): void
    {
        $row = $this->row(['TinhTrangTruocKhiGiat' => str_repeat('a', 320)]);
        $this->post(route('orders.store'), ['KhachHangID' => 9, 'NhanVienID' => 1, 'TrangThai' => OrderStatus::Received->value, 'items' => [$row]])->assertSessionHasNoErrors();
        $booking = $this->createBooking(['HinhThucNhanDo' => 'Tại cửa hàng']);
        $payload = $this->inspectionPayload($booking, 1);
        $payload['items'] = [$row];
        $this->post(route('bookings.confirm', $booking), $payload)->assertSessionHasNoErrors();
        $this->assertSame(2, DonHang::count());
        foreach (ChiTietDonHang::all() as $item) {
            $this->assertSame(15000.0, (float) $item->DonGia);
            $this->assertSame(30000.0, (float) $item->ThanhTien);
            $this->assertSame(320, mb_strlen($item->TinhTrangTruocKhiGiat));
        }
    }

    public static function employees(): array
    {
        return ['84 active' => ['Hoạt động', 1, true], '85 locked' => ['Khóa', 1, false], '86 inactive' => ['Ngừng hoạt động', 1, false], '87 missing' => ['Hoạt động', 999, false], '87 wrong type' => ['Hoạt động', 'abc', false], '88 null' => ['Hoạt động', null, false], '88 empty' => ['Hoạt động', '', false]];
    }

    #[DataProvider('employees')]
    public function test_assignment_checked_by_both_requests_and_direct_services(string $status, mixed $id, bool $valid): void
    {
        DB::table('NhanVien')->where('NhanVienID', 1)->update(['TrangThai' => $status]);
        $data = ['KhachHangID' => 9, 'NhanVienID' => $id, 'TrangThai' => OrderStatus::Received->value, 'items' => [$this->row()]];
        $booking = $this->createBooking(['HinhThucNhanDo' => 'Tại cửa hàng']);
        $payload = $this->inspectionPayload($booking, 1);
        $payload['staff_id'] = $id;
        $payload['items'] = [$this->row()];
        foreach ([fn () => $this->post(route('orders.store'), $data), fn () => $this->post(route('bookings.confirm', $booking), $payload)] as $request) {
            if ($valid) {
                $request()->assertSessionHasNoErrors();
            } else {
                $before = $this->snapshot();
                $request()->assertSessionHasErrors();
                $this->assertSame($before, $this->snapshot());
            }
        }
        if ($valid) {
            $this->assertSame(2, DonHang::count());
            app(OrderService::class)->create($data);
            $newBooking = $this->createBooking(['HinhThucNhanDo' => 'Tại cửa hàng']);
            app(BookingService::class)->inspectBookingAndCreateOrder($newBooking, 1, [$this->row()]);
            $this->assertSame(4, DonHang::count());

            return;
        }
        foreach ([fn () => app(OrderService::class)->create($data), fn () => app(BookingService::class)->inspectBookingAndCreateOrder($booking, is_numeric($id) ? (int) $id : 0, [$this->row()])] as $operation) {
            $before = $this->snapshot();
            try {
                $operation();
                $this->fail('Invalid employee assigned');
            } catch (ValidationException $e) {
                $this->assertNotEmpty($e->errors());
            }
            $this->assertSame($before, $this->snapshot());
        }
    }

    public function test_90_91_302_legacy_preserves_historical_employee_but_rejects_other_inactive_assignment(): void
    {
        DB::table('NhanVien')->where('NhanVienID', 1)->update(['TrangThai' => 'Ngừng hoạt động']);
        DB::table('NhanVien')->insert([['NhanVienID' => 2, 'TrangThai' => 'Ngừng hoạt động'], ['NhanVienID' => 3, 'TrangThai' => 'Hoạt động']]);
        $order = DonHang::create(['MaDonHang' => 'LEG-EMP', 'KhachHangID' => 9, 'NhanVienID' => 1, 'TrangThai' => OrderStatus::Pending->value]);
        $service = app(OrderService::class);
        $service->update($order, ['NhanVienID' => 1, 'GhiChu' => 'Keep historical employee']);
        $this->assertSame(1, $order->fresh()->NhanVienID);
        $before = $this->snapshot();
        try {
            $service->update($order, ['NhanVienID' => 2]);
            $this->fail('New inactive assignment accepted');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('NhanVienID', $e->errors());
        }
        $this->assertSame($before, $this->snapshot());
        $service->completeReceivingInspection($order, [$this->row()]);
        $this->assertSame(1, $order->fresh()->NhanVienID);
        $other = DonHang::create(['MaDonHang' => 'LEG-EMP-ACTIVE', 'KhachHangID' => 9, 'NhanVienID' => 1, 'TrangThai' => OrderStatus::Pending->value]);
        $service->update($other, ['NhanVienID' => 3]);
        $this->assertSame(3, $other->fresh()->NhanVienID);
    }

    public function test_303_cancelled_legacy_refunds_once_and_cannot_be_inspected(): void
    {
        DB::table('DiemTichLuy')->insert(['KhachHangID' => 9, 'DiemHienTai' => 10]);
        $order = DonHang::create(['MaDonHang' => 'LEG-CANCEL', 'KhachHangID' => 9, 'NhanVienID' => 1, 'TrangThai' => OrderStatus::Pending->value, 'DiemSuDung' => 100, 'TienGiamDoDiem' => 100]);
        $service = app(OrderService::class);
        $service->updateStatus($order, OrderStatus::Cancelled->value, reason: 'Local cancellation');
        $this->assertSame(110, (int) DB::table('DiemTichLuy')->value('DiemHienTai'));
        $before = $this->snapshot();
        foreach ([fn () => $service->updateStatus($order->fresh(), OrderStatus::Cancelled->value, reason: 'Replay'), fn () => $service->completeReceivingInspection($order, [$this->row()])] as $call) {
            try {
                $call();
            } catch (ValidationException $e) {
                $this->assertNotEmpty($e->errors());
            }
            $this->assertSame($before, $this->snapshot());
        }
    }

    public function test_299_legacy_reprices_services_refunds_delta_and_keeps_delivery_fee(): void
    {
        Schema::create('KhuyenMai', function (Blueprint $t): void {
            $t->increments('KhuyenMaiID');
            foreach (['MaKhuyenMai', 'TenKhuyenMai', 'LoaiKhuyenMai', 'TrangThai', 'DieuKienApDung'] as $c) {
                $t->string($c)->nullable();
            }
            foreach (['GiaTriGiam', 'GiaTriDonToiThieu', 'MucGiamToiDa'] as $c) {
                $t->decimal($c, 18, 2)->nullable();
            }
            $t->date('NgayBatDau');
            $t->date('NgayKetThuc');
            $t->integer('SoLuongSuDung')->nullable();
        });
        DB::table('KhuyenMai')->insert(['KhuyenMaiID' => 1, 'MaKhuyenMai' => 'LEG10K', 'TenKhuyenMai' => 'Local voucher', 'LoaiKhuyenMai' => 'Tiền mặt', 'GiaTriGiam' => 10000, 'NgayBatDau' => '2026-01-01', 'NgayKetThuc' => '2026-12-31', 'TrangThai' => 'Hoạt động']);
        DB::table('DiemTichLuy')->insert(['KhachHangID' => 9, 'DiemHienTai' => 10000]);
        $order = DonHang::create(['MaDonHang' => 'LEG-POINTS', 'KhachHangID' => 9, 'NhanVienID' => 1, 'TrangThai' => OrderStatus::Pending->value, 'TongTien' => 100000, 'KhuyenMaiID' => 1, 'TienGiamKhuyenMai' => 10000, 'DiemSuDung' => 90000, 'TienGiamDoDiem' => 90000, 'PhiGiaoHang' => 30000, 'ThanhTien' => 30000]);
        $item = ChiTietDonHang::create(['DonHangID' => $order->getKey(), 'DichVuID' => 1, 'LoaiDoGiatID' => 2, 'DonViTinhID' => 2, 'SoLuong' => 4, 'DonGia' => 25000, 'ThanhTien' => 100000]);
        $row = $this->row(['ChiTietDonHangID' => $item->getKey()]);
        unset($row['DonGia']);
        $result = app(OrderService::class)->completeReceivingInspection($order, [$row]);
        $this->assertSame(50000.0, (float) $result->TongTien);
        $this->assertSame(10000.0, (float) $result->TienGiamKhuyenMai);
        $this->assertSame(40000, (int) $result->DiemSuDung);
        $this->assertSame(60000, (int) DB::table('DiemTichLuy')->value('DiemHienTai'));
        $this->assertSame(30000.0, (float) $result->ThanhTien);
    }

    public function test_300_point_write_failure_rolls_back_legacy_details_status_and_balance(): void
    {
        DB::table('DiemTichLuy')->insert(['KhachHangID' => 9, 'DiemHienTai' => 10000]);
        $order = DonHang::create(['MaDonHang' => 'LEG-FAIL-POINTS', 'KhachHangID' => 9, 'NhanVienID' => 1, 'TrangThai' => OrderStatus::Pending->value, 'DiemSuDung' => 100]);
        DB::statement("CREATE TRIGGER fail_points BEFORE UPDATE ON DiemTichLuy BEGIN SELECT RAISE(ABORT, 'isolated point failure'); END");
        $before = $this->snapshot();
        try {
            app(OrderService::class)->completeReceivingInspection($order, [$this->row()]);
            $this->fail('Injected failure not observed');
        } catch (QueryException $e) {
            $this->assertStringContainsString('isolated point failure', $e->getMessage());
        }
        $this->assertSame($before, $this->snapshot());
    }

    public static function invalidBookingStates(): array
    {
        return [[BookingStatus::Confirmed->value], [BookingStatus::Cancelled->value], [BookingStatus::Completed->value]];
    }

    #[DataProvider('invalidBookingStates')]
    public function test_46_direct_conversion_requires_convertible_booking(string $status): void
    {
        $booking = $this->createBooking(['TrangThai' => $status]);
        $before = $this->snapshot();
        try {
            app(BookingService::class)->inspectBookingAndCreateOrder($booking, 1, [$this->row()]);
            $this->fail('Invalid state accepted');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('booking', $e->errors());
        }
        $this->assertSame($before, $this->snapshot());
    }

    public static function lifecycleRequests(): array
    {
        $cases = [];
        foreach (['staff', 'manager', 'owner'] as $role) {
            foreach (['web', 'api'] as $interface) {
                foreach ([OrderStatus::Washed->value, OrderStatus::Delivered->value] as $status) {
                    $cases[$role.' '.$interface.' '.$status] = [$role, $interface, $status];
                }
            }
        }

        return $cases;
    }

    #[DataProvider('lifecycleRequests')]
    public function test_98_all_interfaces_and_roles_cannot_skip_required_lifecycle(string $role, string $interface, string $status): void
    {
        $user = \Mockery::mock(User::class)->makePartial();
        $user->forceFill(['TaiKhoanID' => 1, 'NhanVienID' => 1, 'TrangThai' => 'Hoạt động']);
        $user->shouldReceive('isActive')->andReturn(true);
        $user->shouldReceive('isCustomer')->andReturn(false);
        $user->shouldReceive('isOwner')->andReturn($role === 'owner');
        $user->shouldReceive('canPermission')->andReturnUsing(fn ($permission) => $permission === 'orders.edit_completed' ? $role === 'owner' : true);
        $this->actingAs($user);
        $order = DonHang::create(['MaDonHang' => 'LIFECYCLE', 'KhachHangID' => 9, 'NhanVienID' => 1, 'TrangThai' => OrderStatus::Received->value]);
        $before = $this->snapshot();
        if ($interface === 'web') {
            $this->patch(route('orders.update-status',$order),['TrangThai' => $status])->assertSessionHasErrors('TrangThai');
        } else {
            $this->patchJson(route('api.v1.orders.status',$order),['status' => $status])->assertUnprocessable();
        }
        $this->assertSame($before,$this->snapshot());
    }
}
