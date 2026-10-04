<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\OrderStatus;
use App\Http\Middleware\EnsureUserHasPermission;
use App\Http\Middleware\RejectCustomerRole;
use App\Http\Requests\Admin\LuuBookingRequest;
use App\Http\Requests\Admin\LuuDonHangRequest;
use App\Models\BangGia;
use App\Models\Booking;
use App\Models\ChiTietDonHang;
use App\Models\DonHang;
use App\Models\GiaoNhan;
use App\Models\NhatKyHeThong;
use App\Models\User;
use App\Services\BookingService;
use App\Services\OrderService;
use App\Services\PricingService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use PDOException;
use Tests\TestCase;

class BookingOrderConversionTest extends TestCase
{
    private BookingService $bookingService;

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        $this->createSchema();
        $this->bookingService = app(BookingService::class);
    }

    protected function tearDown(): void
    {
        foreach (['NhatKyHeThong', 'ChiTietBooking', 'ChiTietDonHang', 'GiaoNhan', 'HoaDon', 'ThanhToan', 'DiemTichLuy', 'DonHang', 'Booking', 'BangGia', 'DonViTinh', 'LoaiDoGiat', 'DichVu', 'NhanVien', 'KhachHang'] as $table) {
            Schema::dropIfExists($table);
        }

        parent::tearDown();
    }

    public function test_confirming_a_booking_creates_its_order_item_and_scheduled_delivery(): void
    {
        DB::table('DonViTinh')->insert([
            'DonViTinhID' => 3,
            'TenDonViTinh' => 'Cái',
            'KyHieu' => 'Cái',
            'TrangThai' => 'Hoạt động',
        ]);
        DB::table('BangGia')->insert([
            'BangGiaID' => 1,
            'DichVuID' => 1,
            'LoaiDoGiatID' => 2,
            'DonViTinhID' => 3,
            'DonGia' => 15000,
            'NgayApDung' => '2026-01-01',
            'TrangThai' => 'Hoạt động',
        ]);
        $booking = $this->createBooking();
        $this->actingAsBookingEmployee();

        $this->bookingService->update($booking, [
            'customer_id' => 9,
            'method' => 'Tại nhà',
            'address' => '12 Nguyễn Huệ',
            'scheduled_date' => '2026-10-05',
            'scheduled_time' => '14:30',
            'items' => [
                ['DichVuID' => 1, 'LoaiDoGiatID' => 2, 'DonViTinhID' => 3, 'SoLuong' => 2],
                ['DichVuID' => 1, 'LoaiDoGiatID' => 2, 'DonViTinhID' => 3, 'SoLuong' => 1],
            ],
        ]);
        $this->bookingService->confirmPendingBooking($booking->fresh(), 1);

        $order = DonHang::query()->where('BookingID', $booking->BookingID)->firstOrFail();
        $items = ChiTietDonHang::query()->where('DonHangID', $order->DonHangID)->orderBy('ChiTietDonHangID')->get();
        $item = $items->firstOrFail();
        $delivery = GiaoNhan::query()->where('DonHangID', $order->DonHangID)->firstOrFail();

        $this->assertCount(2, $items);
        $this->assertSame('DH'.str_pad((string) $order->DonHangID, 4, '0', STR_PAD_LEFT), $order->MaDonHang);
        $this->assertSame(OrderStatus::Pending->value, $order->TrangThai);
        $this->assertSame(45000.0, (float) $order->TongTien);
        $this->assertSame(45000.0, (float) $order->ThanhTien);
        $this->assertSame(1, $item->DichVuID);
        $this->assertSame(2, $item->LoaiDoGiatID);
        $this->assertSame(3, $item->DonViTinhID);
        $this->assertSame(2.0, (float) $item->SoLuong);
        $this->assertSame(15000.0, (float) $item->DonGia);
        $this->assertSame(30000.0, (float) $item->ThanhTien);
        $this->assertSame('GIAO_DO', $delivery->LoaiGiaoNhan);
        $this->assertSame('Tại nhà', $delivery->HinhThuc);
        $this->assertSame('12 Nguyễn Huệ', $delivery->DiaChi);
        $this->assertSame('2026-10-05 14:30:00', $delivery->ThoiGianDuKien->format('Y-m-d H:i:s'));
        $this->assertSame(1, $booking->fresh()->NhanVienXacNhanID);
        $this->assertSame(2, DB::table('ChiTietBooking')->where('BookingID', $booking->BookingID)->count());
        $this->assertNotNull($booking->fresh()->ThoiGianXacNhan);
        $orderAudit = NhatKyHeThong::query()
            ->where('BangDuLieu', 'DonHang')
            ->where('BanGhiID', $order->DonHangID)
            ->where('HanhDong', 'Chuyển trạng thái đơn hàng')
            ->firstOrFail();
        $this->assertSame(['TrangThai' => null], $orderAudit->DuLieuCu);
        $this->assertSame(['TrangThai' => OrderStatus::Pending->value], $orderAudit->DuLieuMoi);
        $audit = NhatKyHeThong::query()->where('BangDuLieu', 'Booking')->firstOrFail();
        $this->assertSame(1, NhatKyHeThong::query()->where('BangDuLieu', 'Booking')->count());
        $this->assertSame('Xác nhận Booking', $audit->HanhDong);
        $this->assertCount(2, $audit->DuLieuMoi['ChiTietBooking']);
        $this->assertSame(1, $audit->DuLieuMoi['Booking']['NhanVienXacNhanID']);
        $this->assertNotEmpty($audit->DuLieuMoi['Booking']['ThoiGianXacNhan']);

        $sameOrder = $this->bookingService->confirmAndCreateOrder($booking->fresh());
        $this->assertSame($order->DonHangID, $sameOrder?->DonHangID);
        $this->assertSame(1, DonHang::query()->where('BookingID', $booking->BookingID)->count());

        $this->bookingService->update($booking->fresh(), [
            'status' => BookingStatus::Confirmed->value,
        ]);
        $this->assertSame(1, DonHang::query()->where('BookingID', $booking->BookingID)->count());
        $this->assertSame(1, NhatKyHeThong::query()->where('BangDuLieu', 'Booking')->count());
    }

    public function test_confirm_route_changes_pending_booking_and_creates_one_order(): void
    {
        DB::table('DonViTinh')->insert([
            'DonViTinhID' => 3,
            'TenDonViTinh' => 'Cái',
            'KyHieu' => 'Cái',
            'TrangThai' => 'Hoạt động',
        ]);
        DB::table('BangGia')->insert([
            'BangGiaID' => 1,
            'DichVuID' => 1,
            'LoaiDoGiatID' => 2,
            'DonViTinhID' => 3,
            'DonGia' => 15000,
            'NgayApDung' => '2026-01-01',
            'TrangThai' => 'Hoạt động',
        ]);
        $booking = $this->createBooking();
        $this->bookingService->update($booking, [
            'items' => [
                ['DichVuID' => 1, 'LoaiDoGiatID' => 2, 'DonViTinhID' => 3, 'SoLuong' => 2],
            ],
        ]);
        $this->actingAsBookingEmployee();
        $this->withoutMiddleware([
            EnsureUserHasPermission::class,
            RejectCustomerRole::class,
        ]);

        DB::table('NhanVien')->insert([
            'NhanVienID' => 2,
            'HoTen' => 'Nhân viên được giao',
        ]);
        $response = $this->post(route('bookings.confirm', $booking->BookingID), ['NhanVienID' => 2]);

        $order = DonHang::query()->where('BookingID', $booking->BookingID)->firstOrFail();
        $response->assertRedirect(route('orders.show', $order));
        $this->assertSame(BookingStatus::Confirmed->value, $booking->fresh()->TrangThai);
        $this->assertSame(2, $order->NhanVienID);
        $this->assertSame(2, $booking->fresh()->NhanVienID);
        $this->assertSame(1, $booking->fresh()->NhanVienXacNhanID);
        $this->assertNotNull($booking->fresh()->ThoiGianXacNhan);
        $this->assertSame(1, DonHang::query()->where('BookingID', $booking->BookingID)->count());
        $this->assertSame(1, GiaoNhan::query()->where('DonHangID', $order->DonHangID)->count());

        $this->from(route('bookings.index'))
            ->post(route('bookings.confirm', $booking->BookingID), ['NhanVienID' => 2])
            ->assertRedirect(route('bookings.index'))
            ->assertSessionHasErrors([
                'booking' => 'Booking này đã có đơn hàng '.$order->MaDonHang.'; hệ thống không tạo đơn hàng trùng.',
            ]);
        $this->assertSame(1, DonHang::query()->where('BookingID', $booking->BookingID)->count());
    }

    public function test_booking_edit_cannot_bypass_the_receive_and_create_order_action(): void
    {
        $booking = $this->createBooking();
        $this->actingAsBookingEmployee();

        try {
            $this->bookingService->update($booking, [
                'status' => BookingStatus::Confirmed->value,
            ]);
            $this->fail('Booking confirmation must use the receiving action.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }

        $this->assertSame(BookingStatus::Pending->value, $booking->fresh()->TrangThai);
        $this->assertSame(0, DonHang::query()->count());
    }

    public function test_confirm_route_requires_a_responsible_employee_before_creating_an_order(): void
    {
        DB::table('DonViTinh')->insert([
            'DonViTinhID' => 3,
            'TenDonViTinh' => 'Cái',
            'KyHieu' => 'Cái',
            'TrangThai' => 'Hoạt động',
        ]);
        DB::table('BangGia')->insert([
            'BangGiaID' => 1,
            'DichVuID' => 1,
            'LoaiDoGiatID' => 2,
            'DonViTinhID' => 3,
            'DonGia' => 15000,
            'NgayApDung' => '2026-01-01',
            'TrangThai' => 'Hoạt động',
        ]);
        $booking = $this->createBooking();
        $this->bookingService->update($booking, [
            'items' => [
                ['DichVuID' => 1, 'LoaiDoGiatID' => 2, 'DonViTinhID' => 3, 'SoLuong' => 1],
            ],
        ]);
        $this->actingAsBookingEmployee();
        $this->withoutMiddleware([
            EnsureUserHasPermission::class,
            RejectCustomerRole::class,
        ]);

        $this->from(route('bookings.index'))
            ->post(route('bookings.confirm', $booking->BookingID))
            ->assertRedirect(route('bookings.index'))
            ->assertSessionHasErrors([
                'NhanVienID' => 'Vui lòng chọn nhân viên phụ trách.',
            ]);

        $this->assertSame(BookingStatus::Pending->value, $booking->fresh()->TrangThai);
        $this->assertSame(0, DonHang::query()->where('BookingID', $booking->BookingID)->count());
    }

    public function test_confirm_route_rolls_back_booking_when_postgres_trigger_rejects_order_insert(): void
    {
        DB::table('DonViTinh')->insert([
            'DonViTinhID' => 3,
            'TenDonViTinh' => 'Cái',
            'KyHieu' => 'Cái',
            'TrangThai' => 'Hoạt động',
        ]);
        DB::table('BangGia')->insert([
            'BangGiaID' => 1,
            'DichVuID' => 1,
            'LoaiDoGiatID' => 2,
            'DonViTinhID' => 3,
            'DonGia' => 15000,
            'NgayApDung' => '2026-01-01',
            'TrangThai' => 'Hoạt động',
        ]);
        $booking = $this->createBooking();
        $this->bookingService->update($booking, [
            'items' => [
                ['DichVuID' => 1, 'LoaiDoGiatID' => 2, 'DonViTinhID' => 3, 'SoLuong' => 2],
            ],
        ]);
        $this->actingAsBookingEmployee();
        $this->withoutMiddleware([
            EnsureUserHasPermission::class,
            RejectCustomerRole::class,
        ]);

        $databaseError = new PDOException('SQLSTATE[42703]: Undefined column: 7 ERROR: record "old" has no field "trangthai" CONTEXT: trigger function');
        $databaseError->errorInfo = ['42703', 7, 'record "old" has no field "trangthai"'];
        $queryException = new QueryException(
            'pgsql',
            'insert into "DonHang"',
            [],
            $databaseError,
        );
        $orderService = \Mockery::mock(OrderService::class);
        $orderService->shouldReceive('createFromBooking')
            ->once()
            ->andThrow($queryException);
        $this->app->instance(BookingService::class, new BookingService($orderService));
        Log::shouldReceive('error')
            ->once()
            ->with('Booking operation failed', \Mockery::on(
                fn (array $context): bool => $context['booking_id'] === $booking->BookingID
                    && $context['operation'] === 'confirm'
                    && $context['sql_state'] === '42703'
                    && str_contains($context['database_error'], 'record "old" has no field "trangthai"')
                    && ! str_contains($context['database_error'], 'insert into')
            ));

        $response = $this->post(route('bookings.confirm', $booking->BookingID), ['NhanVienID' => 1]);

        $response->assertRedirect(route('bookings.index'));
        $response->assertSessionHas(
            'error',
            'Không thể tạo đơn hàng vì trigger Supabase đang đọc sai tên cột trạng thái: schema dùng "TrangThai" có phân biệt chữ hoa/thường. Booking chưa được xác nhận; giao dịch đã được hủy.',
        );
        $this->assertSame(BookingStatus::Pending->value, $booking->fresh()->TrangThai);
        $this->assertNull($booking->fresh()->NhanVienXacNhanID);
        $this->assertNull($booking->fresh()->ThoiGianXacNhan);
        $this->assertSame(0, DonHang::query()->count());
        $this->assertSame(0, GiaoNhan::query()->count());
        $this->assertSame(0, NhatKyHeThong::query()->count());
    }

    public function test_confirm_route_explains_that_service_lines_are_required_and_does_not_confirm_empty_booking(): void
    {
        $booking = $this->createBooking();
        $this->actingAsBookingEmployee();
        $this->withoutMiddleware([
            EnsureUserHasPermission::class,
            RejectCustomerRole::class,
        ]);

        $this->from(route('bookings.index'))
            ->post(route('bookings.confirm', $booking->BookingID), ['NhanVienID' => 1])
            ->assertRedirect(route('bookings.edit', $booking))
            ->assertSessionHasErrors([
                'items' => 'Đặt lịch chưa có dòng dịch vụ. Hãy bổ sung ít nhất một dòng trước khi xác nhận.',
            ]);

        $this->assertSame(BookingStatus::Pending->value, $booking->fresh()->TrangThai);
        $this->assertNull($booking->fresh()->NhanVienXacNhanID);
        $this->assertNull($booking->fresh()->ThoiGianXacNhan);
        $this->assertSame(0, DonHang::query()->count());
        $this->assertSame(0, GiaoNhan::query()->count());
    }

    public function test_confirm_route_rejects_cancelled_booking_without_creating_an_order(): void
    {
        $booking = $this->createBooking(['TrangThai' => BookingStatus::Cancelled->value]);
        $this->actingAsBookingEmployee();
        $this->withoutMiddleware([
            EnsureUserHasPermission::class,
            RejectCustomerRole::class,
        ]);

        $this->from(route('bookings.index'))
            ->post(route('bookings.confirm', $booking->BookingID), ['NhanVienID' => 1])
            ->assertRedirect(route('bookings.index'))
            ->assertSessionHasErrors('booking');

        $this->assertSame(BookingStatus::Cancelled->value, $booking->fresh()->TrangThai);
        $this->assertSame(0, DonHang::query()->count());
    }

    public function test_booking_confirmation_rolls_back_when_order_item_creation_fails_mid_transaction(): void
    {
        DB::table('DonViTinh')->insert([
            'DonViTinhID' => 3,
            'TenDonViTinh' => 'Cái',
            'KyHieu' => 'Cái',
            'TrangThai' => 'Hoạt động',
        ]);
        DB::table('BangGia')->insert([
            'BangGiaID' => 1,
            'DichVuID' => 1,
            'LoaiDoGiatID' => 2,
            'DonViTinhID' => 3,
            'DonGia' => 15000,
            'NgayApDung' => '2026-01-01',
            'TrangThai' => 'Hoạt động',
        ]);
        $booking = $this->createBooking();
        $this->bookingService->update($booking, [
            'items' => [
                ['DichVuID' => 1, 'LoaiDoGiatID' => 2, 'DonViTinhID' => 3, 'SoLuong' => 2],
            ],
        ]);
        $this->actingAsBookingEmployee();
        ChiTietDonHang::creating(static function (ChiTietDonHang $item): void {
            throw new \RuntimeException('Simulated order-item insert failure.');
        });

        try {
            $this->bookingService->confirmPendingBooking($booking->fresh(), 1);
            $this->fail('An order-item insert failure must abort booking confirmation.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated order-item insert failure.', $exception->getMessage());
        }

        $this->assertSame(BookingStatus::Pending->value, $booking->fresh()->TrangThai);
        $this->assertNull($booking->fresh()->NhanVienXacNhanID);
        $this->assertNull($booking->fresh()->ThoiGianXacNhan);
        $this->assertSame(0, DonHang::query()->count());
        $this->assertSame(0, ChiTietDonHang::query()->count());
        $this->assertSame(0, GiaoNhan::query()->count());
        $this->assertSame(0, NhatKyHeThong::query()->count());
    }

    public function test_booking_creation_is_written_to_the_system_audit_log(): void
    {
        $this->actingAsBookingEmployee();

        $booking = $this->bookingService->create([
            'customer_id' => 9,
            'method' => 'Tại nhà',
            'address' => '12 Nguyễn Huệ',
            'scheduled_date' => '2026-10-05',
            'scheduled_time' => '14:30',
            'status' => BookingStatus::Pending->value,
        ]);

        $audit = NhatKyHeThong::query()
            ->where('BangDuLieu', 'Booking')
            ->firstOrFail();
        $this->assertSame('Tạo Booking', $audit->HanhDong);
        $this->assertSame($booking->BookingID, $audit->BanGhiID);
        $this->assertSame(1, $audit->TaiKhoanID);
        $this->assertSame(BookingStatus::Pending->value, $audit->DuLieuMoi['Booking']['TrangThai']);
    }

    public function test_weight_booking_applies_the_configured_minimum_to_each_line_and_keeps_actual_weights(): void
    {
        config(['giatui.khoi_luong_toi_thieu' => 3.0]);
        DB::table('DonViTinh')->insert([
            'DonViTinhID' => 1,
            'TenDonViTinh' => 'Kilogram',
            'KyHieu' => 'KG',
            'TrangThai' => 'Hoạt động',
        ]);
        DB::table('BangGia')->insert([
            'BangGiaID' => 1,
            'DichVuID' => 1,
            'LoaiDoGiatID' => 2,
            'DonViTinhID' => 1,
            'DonGia' => 10000,
            'NgayApDung' => '2026-01-01',
            'TrangThai' => 'Hoạt động',
        ]);
        $booking = $this->createBooking();
        $this->actingAsBookingEmployee();

        $this->bookingService->update($booking, [
            'items' => [
                ['DichVuID' => 1, 'LoaiDoGiatID' => 2, 'DonViTinhID' => 1, 'KhoiLuong' => 1.5],
                ['DichVuID' => 1, 'LoaiDoGiatID' => 2, 'DonViTinhID' => 1, 'KhoiLuong' => 4],
            ],
        ]);
        $booking->refresh();
        $this->bookingService->confirmPendingBooking($booking, 1);

        $order = DonHang::query()->where('BookingID', $booking->BookingID)->firstOrFail();
        $items = ChiTietDonHang::query()
            ->where('DonHangID', $order->DonHangID)
            ->orderBy('ChiTietDonHangID')
            ->get();

        $this->assertCount(2, $items);
        $this->assertSame([1.5, 4.0], $items->pluck('KhoiLuong')->map(fn ($weight): float => (float) $weight)->all());
        $this->assertSame([30000.0, 40000.0], $items->pluck('ThanhTien')->map(fn ($amount): float => (float) $amount)->all());
        $this->assertNull($items[0]->SoLuong);
        $this->assertSame(70000.0, (float) $order->ThanhTien);
    }

    public function test_pending_order_cannot_skip_receiving_inspection_to_change_status(): void
    {
        $order = DonHang::query()->create([
            'MaDonHang' => 'DH001',
            'KhachHangID' => 9,
            'TrangThai' => OrderStatus::Pending->value,
            'TongTien' => 0,
            'ThanhTien' => 0,
        ]);
        $this->actingAsBookingEmployee();

        foreach ([OrderStatus::Received, OrderStatus::Washing] as $status) {
            try {
                app(OrderService::class)->updateStatus($order, $status->value);
                $this->fail('A pending order must not skip receiving inspection.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('TrangThai', $exception->errors());
            }
        }

        try {
            app(OrderService::class)->update($order, ['TrangThai' => OrderStatus::Received->value]);
            $this->fail('The order edit form must not bypass receiving inspection.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('TrangThai', $exception->errors());
        }

        $this->assertSame(OrderStatus::Pending->value, $order->fresh()->TrangThai);
    }

    public function test_receiving_inspection_saves_condition_recalculates_amount_and_allows_washing(): void
    {
        $this->createRequestCatalog();
        DB::table('DichVu')->insert(['DichVuID' => 2]);
        DB::table('BangGia')->insert([
            [
                'BangGiaID' => 1,
                'DichVuID' => 1,
                'LoaiDoGiatID' => 2,
                'DonViTinhID' => 2,
                'DonGia' => 15000,
                'NgayApDung' => '2026-01-01',
                'TrangThai' => 'Hoạt động',
            ],
            [
                'BangGiaID' => 2,
                'DichVuID' => 2,
                'LoaiDoGiatID' => 2,
                'DonViTinhID' => 2,
                'DonGia' => 20000,
                'NgayApDung' => '2026-01-01',
                'TrangThai' => 'Hoạt động',
            ],
        ]);

        $order = DonHang::query()->create([
            'MaDonHang' => 'DH003',
            'KhachHangID' => 9,
            'NhanVienID' => 1,
            'TrangThai' => OrderStatus::Pending->value,
            'TongTien' => 15000,
            'ThanhTien' => 15000,
        ]);
        $item = ChiTietDonHang::query()->create([
            'DonHangID' => $order->DonHangID,
            'DichVuID' => 1,
            'LoaiDoGiatID' => 2,
            'DonViTinhID' => 2,
            'SoLuong' => 1,
            'DonGia' => 15000,
            'ThanhTien' => 15000,
        ]);
        $itemToRemove = ChiTietDonHang::query()->create([
            'DonHangID' => $order->DonHangID,
            'DichVuID' => 1,
            'LoaiDoGiatID' => 2,
            'DonViTinhID' => 2,
            'SoLuong' => 1,
            'DonGia' => 15000,
            'ThanhTien' => 15000,
        ]);
        DB::table('BangGia')->where('DichVuID', 1)->update(['TrangThai' => 'Tạm ngưng']);
        $this->actingAsBookingEmployee();

        $receivedOrder = app(OrderService::class)->completeReceivingInspection($order, [
            [
                'ChiTietDonHangID' => $item->ChiTietDonHangID,
                'DichVuID' => 1,
                'LoaiDoGiatID' => 2,
                'DonViTinhID' => 2,
                'SoLuong' => 3,
                'GhiChu' => 'Có vết ố ở tay áo',
            ],
            [
                'DichVuID' => 2,
                'LoaiDoGiatID' => 2,
                'DonViTinhID' => 2,
                'SoLuong' => 1,
                'GhiChu' => 'Không phát hiện hư hỏng',
            ],
        ]);

        $this->assertSame(OrderStatus::Received->value, $receivedOrder->TrangThai);
        $this->assertSame(65000.0, (float) $receivedOrder->TongTien);
        $this->assertSame('Có vết ố ở tay áo', $item->fresh()->GhiChu);
        $this->assertNull($itemToRemove->fresh());
        $this->assertSame(2, $receivedOrder->chiTietDonHangs->count());
        $this->assertSame(
            'Không phát hiện hư hỏng',
            $receivedOrder->chiTietDonHangs->firstWhere('DichVuID', 2)?->GhiChu
        );

        try {
            app(OrderService::class)->update($receivedOrder, ['items' => []]);
            $this->fail('Order details must lock after receiving is complete.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('order', $exception->errors());
        }

        $washingOrder = app(OrderService::class)->updateStatus($receivedOrder, OrderStatus::Washing->value);
        $this->assertSame(OrderStatus::Washing->value, $washingOrder->TrangThai);
    }

    public function test_receiving_completion_route_persists_the_inspection(): void
    {
        $this->createRequestCatalog();
        DB::table('BangGia')->insert([
            'BangGiaID' => 1,
            'DichVuID' => 1,
            'LoaiDoGiatID' => 2,
            'DonViTinhID' => 2,
            'DonGia' => 15000,
            'NgayApDung' => '2026-01-01',
            'TrangThai' => 'Hoạt động',
        ]);
        $order = DonHang::query()->create([
            'MaDonHang' => 'DH004',
            'KhachHangID' => 9,
            'NhanVienID' => 1,
            'TrangThai' => OrderStatus::Pending->value,
            'TongTien' => 15000,
            'ThanhTien' => 15000,
        ]);
        $item = ChiTietDonHang::query()->create([
            'DonHangID' => $order->DonHangID,
            'DichVuID' => 1,
            'LoaiDoGiatID' => 2,
            'DonViTinhID' => 2,
            'SoLuong' => 1,
            'DonGia' => 15000,
            'ThanhTien' => 15000,
        ]);
        $this->actingAsBookingEmployee();
        $this->withoutMiddleware([
            EnsureUserHasPermission::class,
            RejectCustomerRole::class,
        ]);

        $this->post(route('orders.complete-receiving', $order), [
            'items' => [[
                'ChiTietDonHangID' => $item->ChiTietDonHangID,
                'DichVuID' => 1,
                'LoaiDoGiatID' => 2,
                'DonViTinhID' => 2,
                'SoLuong' => 2,
                'GhiChu' => 'Bung chỉ nhẹ',
            ]],
        ])->assertRedirect(route('orders.show', $order));

        $this->assertSame(OrderStatus::Received->value, $order->fresh()->TrangThai);
        $this->assertSame('Bung chỉ nhẹ', $item->fresh()->GhiChu);
    }

    public function test_order_amount_changes_are_written_to_the_system_audit_log(): void
    {
        $order = DonHang::query()->create([
            'MaDonHang' => 'DH002',
            'KhachHangID' => 9,
            'TrangThai' => OrderStatus::Pending->value,
            'TongTien' => 100,
            'ThanhTien' => 100,
        ]);
        $order->refresh();
        $this->actingAsBookingEmployee();

        app(OrderService::class)->update($order, ['items' => []]);

        $audit = NhatKyHeThong::query()
            ->where('BangDuLieu', 'DonHang')
            ->where('HanhDong', 'Thay đổi số tiền đơn hàng')
            ->firstOrFail();
        $this->assertSame(['TongTien', 'ThanhTien'], array_keys($audit->DuLieuCu));
        $this->assertSame(['TongTien' => 100, 'ThanhTien' => 100], $audit->DuLieuCu);
        $this->assertSame(['TongTien' => 0, 'ThanhTien' => 0], $audit->DuLieuMoi);
    }

    public function test_order_creation_uses_the_exact_price_tuple_instead_of_the_submitted_price(): void
    {
        DB::table('DonViTinh')->insert([
            ['DonViTinhID' => 3, 'TenDonViTinh' => 'Cái', 'KyHieu' => 'Cái', 'TrangThai' => 'Hoạt động'],
            ['DonViTinhID' => 4, 'TenDonViTinh' => 'Đôi', 'KyHieu' => 'Đôi', 'TrangThai' => 'Hoạt động'],
        ]);
        DB::table('BangGia')->insert([
            [
                'BangGiaID' => 1,
                'DichVuID' => 1,
                'LoaiDoGiatID' => 2,
                'DonViTinhID' => 3,
                'DonGia' => 15000,
                'NgayApDung' => '2026-01-01',
                'TrangThai' => 'Hoạt động',
            ],
            [
                'BangGiaID' => 2,
                'DichVuID' => 1,
                'LoaiDoGiatID' => 2,
                'DonViTinhID' => 4,
                'DonGia' => 9000,
                'NgayApDung' => '2026-01-01',
                'TrangThai' => 'Hoạt động',
            ],
        ]);

        $order = app(OrderService::class)->create([
            'KhachHangID' => 9,
            'TrangThai' => 'Chờ tiếp nhận',
            'items' => [[
                'DichVuID' => 1,
                'LoaiDoGiatID' => 2,
                'DonViTinhID' => 3,
                'SoLuong' => 2,
                'DonGia' => 1,
            ]],
        ]);

        $item = $order->chiTietDonHangs->firstOrFail();
        $this->assertSame(3, $item->DonViTinhID);
        $this->assertSame(15000.0, (float) $item->DonGia);
        $this->assertSame(30000.0, (float) $item->ThanhTien);
    }

    public function test_order_creation_rejects_a_missing_exact_price_tuple(): void
    {
        DB::table('DonViTinh')->insert([
            ['DonViTinhID' => 3, 'TenDonViTinh' => 'Cái', 'KyHieu' => 'Cái', 'TrangThai' => 'Hoạt động'],
            ['DonViTinhID' => 4, 'TenDonViTinh' => 'Đôi', 'KyHieu' => 'Đôi', 'TrangThai' => 'Hoạt động'],
        ]);
        DB::table('BangGia')->insert([
            'BangGiaID' => 1,
            'DichVuID' => 1,
            'LoaiDoGiatID' => 2,
            'DonViTinhID' => 3,
            'DonGia' => 15000,
            'NgayApDung' => '2026-01-01',
            'TrangThai' => 'Hoạt động',
        ]);

        try {
            app(OrderService::class)->create([
                'KhachHangID' => 9,
                'TrangThai' => 'Chờ tiếp nhận',
                'items' => [[
                    'DichVuID' => 1,
                    'LoaiDoGiatID' => 2,
                    'DonViTinhID' => 4,
                    'SoLuong' => 2,
                    'DonGia' => 1000,
                ]],
            ]);
            $this->fail('An item without an exact effective price tuple must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('items.0.DonViTinhID', $exception->errors());
        }

        $this->assertSame(0, DonHang::query()->count());
    }

    public function test_pricing_service_rejects_overlapping_periods_for_the_same_three_key_tuple(): void
    {
        DB::table('BangGia')->insert([
            'BangGiaID' => 1,
            'DichVuID' => 1,
            'LoaiDoGiatID' => 2,
            'DonViTinhID' => 3,
            'DonGia' => 15000,
            'NgayApDung' => '2026-01-01',
            'NgayKetThuc' => '2026-10-10',
            'TrangThai' => 'Hoạt động',
        ]);

        try {
            app(PricingService::class)->create([
                'DichVuID' => 1,
                'LoaiDoGiatID' => 2,
                'DonViTinhID' => 3,
                'DonGia' => 17000,
                'NgayApDung' => '2026-10-10',
                'NgayKetThuc' => '2026-12-31',
                'TrangThai' => 'Hoạt động',
            ]);
            $this->fail('Overlapping pricing periods must be rejected, including a shared boundary date.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('NgayApDung', $exception->errors());
        }

        $pricing = app(PricingService::class)->create([
            'DichVuID' => 1,
            'LoaiDoGiatID' => 2,
            'DonViTinhID' => 3,
            'DonGia' => 17000,
            'NgayApDung' => '2026-10-11',
            'NgayKetThuc' => null,
            'TrangThai' => 'Hoạt động',
        ]);

        $this->assertSame(2, $pricing->BangGiaID);

        try {
            app(PricingService::class)->update($pricing, [
                'DichVuID' => 1,
                'LoaiDoGiatID' => 2,
                'DonViTinhID' => 3,
                'DonGia' => 18000,
                'NgayApDung' => '2026-10-09',
                'NgayKetThuc' => null,
                'TrangThai' => 'Hoạt động',
            ]);
            $this->fail('Updating a pricing period into an overlap must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('NgayApDung', $exception->errors());
        }

        $updatedPricing = app(PricingService::class)->update($pricing, [
            'DichVuID' => 1,
            'LoaiDoGiatID' => 2,
            'DonViTinhID' => 3,
            'DonGia' => 18000,
            'NgayApDung' => '2026-10-11',
            'NgayKetThuc' => null,
            'TrangThai' => 'Hoạt động',
        ]);
        $this->assertSame(18000.0, (float) $updatedPricing->DonGia);
    }

    public function test_latest_pricing_uses_the_newest_effective_date_and_includes_date_boundaries(): void
    {
        BangGia::query()->create([
            'BangGiaID' => 1,
            'DichVuID' => 1,
            'LoaiDoGiatID' => 2,
            'DonViTinhID' => 1,
            'DonGia' => 10000,
            'NgayApDung' => '2026-01-01',
            'NgayKetThuc' => '2026-10-01',
            'TrangThai' => 'Hoạt động',
        ]);
        BangGia::query()->create([
            'BangGiaID' => 2,
            'DichVuID' => 1,
            'LoaiDoGiatID' => 2,
            'DonViTinhID' => 1,
            'DonGia' => 12000,
            'NgayApDung' => '2026-10-02',
            'NgayKetThuc' => '2026-10-02',
            'TrangThai' => 'Hoạt động',
        ]);
        BangGia::query()->create([
            'BangGiaID' => 3,
            'DichVuID' => 1,
            'LoaiDoGiatID' => 2,
            'DonViTinhID' => 1,
            'DonGia' => 15000,
            'NgayApDung' => '2026-10-03',
            'TrangThai' => 'Hoạt động',
        ]);

        Carbon::setTestNow('2026-10-02 12:00:00');
        try {
            $currentPricing = BangGia::getLatestPricing(1, 2, 1);
            $this->assertSame(2, $currentPricing?->BangGiaID);
            $this->assertSame(12000.0, $currentPricing?->DonGia);

            Carbon::setTestNow('2026-10-03 12:00:00');
            $this->assertSame(3, BangGia::getLatestPricing(1, 2, 1)?->BangGiaID);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_booking_confirmation_rolls_back_when_no_service_snapshot_is_provided(): void
    {
        $booking = $this->createBooking();
        $this->actingAsBookingEmployee();

        try {
            $this->bookingService->confirmPendingBooking($booking, 1);
            $this->fail('A booking without an item snapshot must not become an order.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('items', $exception->errors());
        }

        $this->assertSame(BookingStatus::Pending->value, $booking->fresh()->TrangThai);
        $this->assertSame(0, DonHang::query()->count());
        $this->assertSame(0, GiaoNhan::query()->count());
    }

    public function test_booking_with_an_order_cannot_be_cancelled_or_deleted_independently(): void
    {
        $booking = $this->createBooking(['TrangThai' => BookingStatus::Confirmed->value]);
        $order = DonHang::query()->create([
            'MaDonHang' => 'DH001',
            'BookingID' => $booking->BookingID,
            'KhachHangID' => 9,
            'TrangThai' => 'Chờ tiếp nhận',
        ]);

        try {
            $this->bookingService->update($booking, [
                'status' => BookingStatus::Cancelled->value,
            ]);
            $this->fail('A booking with an order must not be cancelled separately.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }

        $this->assertFalse($this->bookingService->delete($booking));
        $this->assertSame(BookingStatus::Confirmed->value, $booking->fresh()->TrangThai);
        $this->assertSame(1, DonHang::query()->count());

        $order->update(['TrangThai' => 'Đã hủy']);
        $this->bookingService->update($booking->fresh(), [
            'status' => BookingStatus::Cancelled->value,
        ]);

        $this->assertSame(BookingStatus::Cancelled->value, $booking->fresh()->TrangThai);
    }

    public function test_booking_request_rejects_quantity_for_weight_unit_and_both_amount_fields(): void
    {
        $this->createRequestCatalog();

        try {
            $this->validateBookingRequest([
                'items' => [
                    ['DichVuID' => 1, 'LoaiDoGiatID' => 2, 'DonViTinhID' => 1, 'SoLuong' => 2, 'KhoiLuong' => 3],
                ],
            ]);
            $this->fail('Weight-based units must not accept quantity.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('items.0.SoLuong', $exception->errors());
        }
    }

    public function test_booking_request_accepts_weight_for_a_weight_unit(): void
    {
        $this->createRequestCatalog();

        $validated = $this->validateBookingRequest([
            'items' => [
                ['DichVuID' => 1, 'LoaiDoGiatID' => 2, 'DonViTinhID' => 1, 'SoLuong' => null, 'KhoiLuong' => 2.349],
            ],
        ]);

        $this->assertSame(2.35, $validated['items'][0]['KhoiLuong']);
    }

    public function test_booking_request_normalizes_database_decimal_integer_quantities(): void
    {
        $this->createRequestCatalog();

        $validated = $this->validateBookingRequest([
            'items' => [[
                'DichVuID' => 1,
                'LoaiDoGiatID' => 2,
                'DonViTinhID' => 2,
                'SoLuong' => '3.00',
            ]],
        ]);

        $this->assertSame('3', $validated['items'][0]['SoLuong']);
    }

    public function test_booking_request_reports_fractional_piece_quantity_only_once(): void
    {
        $this->createRequestCatalog();

        try {
            $this->validateBookingRequest([
                'items' => [[
                    'DichVuID' => 1,
                    'LoaiDoGiatID' => 2,
                    'DonViTinhID' => 2,
                    'SoLuong' => '3.50',
                ]],
            ]);
            $this->fail('Fractional piece quantities must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                ['Số lượng món phải là số nguyên.'],
                $exception->errors()['items.0.SoLuong'],
            );
        }
    }

    public function test_booking_request_rejects_non_positive_weight_and_piece_quantity(): void
    {
        $this->createRequestCatalog();

        foreach ([
            ['unitId' => 1, 'quantity' => null, 'weight' => -5],
            ['unitId' => 1, 'quantity' => null, 'weight' => 0],
            ['unitId' => 2, 'quantity' => 0, 'weight' => null],
        ] as $measurement) {
            try {
                $this->validateBookingRequest([
                    'items' => [[
                        'DichVuID' => 1,
                        'LoaiDoGiatID' => 2,
                        'DonViTinhID' => $measurement['unitId'],
                        'SoLuong' => $measurement['quantity'],
                        'KhoiLuong' => $measurement['weight'],
                    ]],
                ]);
                $this->fail('Zero and negative required measurements must be rejected.');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
        }
    }

    public function test_booking_service_rounds_excess_weight_precision_before_persisting(): void
    {
        DB::table('DonViTinh')->insert([
            'DonViTinhID' => 1,
            'TenDonViTinh' => 'Kilogram',
            'KyHieu' => 'KG',
            'TrangThai' => 'Hoạt động',
        ]);
        DB::table('BangGia')->insert([
            'BangGiaID' => 1,
            'DichVuID' => 1,
            'LoaiDoGiatID' => 2,
            'DonViTinhID' => 1,
            'DonGia' => 10000,
            'NgayApDung' => '2026-01-01',
            'TrangThai' => 'Hoạt động',
        ]);
        $booking = $this->createBooking();

        $this->bookingService->update($booking, [
            'items' => [
                ['DichVuID' => 1, 'LoaiDoGiatID' => 2, 'DonViTinhID' => 1, 'KhoiLuong' => 4.239],
            ],
        ]);

        $item = $booking->chiTietBookings()->firstOrFail();
        $this->assertSame(4.24, (float) $item->KhoiLuong);
        $this->assertSame(42400.0, (float) $item->ThanhTien);
    }

    public function test_order_service_rounds_excess_weight_precision_before_calculating_and_persisting(): void
    {
        DB::table('DonViTinh')->insert([
            'DonViTinhID' => 1,
            'TenDonViTinh' => 'Kilogram',
            'KyHieu' => 'KG',
            'TrangThai' => 'Hoạt động',
        ]);
        DB::table('BangGia')->insert([
            'BangGiaID' => 1,
            'DichVuID' => 1,
            'LoaiDoGiatID' => 2,
            'DonViTinhID' => 1,
            'DonGia' => 10000,
            'NgayApDung' => '2026-01-01',
            'TrangThai' => 'Hoạt động',
        ]);

        $order = app(OrderService::class)->create([
            'KhachHangID' => 9,
            'TrangThai' => OrderStatus::Pending->value,
            'items' => [[
                'DichVuID' => 1,
                'LoaiDoGiatID' => 2,
                'DonViTinhID' => 1,
                'KhoiLuong' => 1.239,
            ], [
                'DichVuID' => 1,
                'LoaiDoGiatID' => 2,
                'DonViTinhID' => 1,
                'KhoiLuong' => 4.239,
            ]],
        ]);

        $items = $order->chiTietDonHangs;
        $this->assertSame([1.24, 4.24], $items->pluck('KhoiLuong')->map(fn ($weight): float => (float) $weight)->all());
        $this->assertSame([30000.0, 42400.0], $items->pluck('ThanhTien')->map(fn ($amount): float => (float) $amount)->all());
        $this->assertSame(72400.0, (float) $order->ThanhTien);
    }

    public function test_order_request_rounds_excess_weight_precision_before_validation(): void
    {
        $this->createRequestCatalog();

        $validated = $this->validateOrderRequest([
            'items' => [[
                'DichVuID' => 1,
                'LoaiDoGiatID' => 2,
                'DonViTinhID' => 1,
                'SoLuong' => null,
                'KhoiLuong' => 2.349,
            ]],
        ]);

        $this->assertSame(2.35, $validated['items'][0]['KhoiLuong']);
    }

    public function test_order_request_requires_a_valid_responsible_employee_for_create_and_update(): void
    {
        $this->createRequestCatalog();

        foreach (['POST', 'PUT'] as $method) {
            try {
                $this->validateOrderRequest(['NhanVienID' => null], $method);
                $this->fail('The responsible employee is required for order creation and updates.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('NhanVienID', $exception->errors());
                $this->assertSame('Vui lòng chọn nhân viên phụ trách.', $exception->errors()['NhanVienID'][0]);
            }

            $validated = $this->validateOrderRequest(['NhanVienID' => 1], $method);
            $this->assertSame(1, $validated['NhanVienID']);
        }
    }

    public function test_booking_edit_requires_a_responsible_employee_when_converting_to_an_order(): void
    {
        $this->createRequestCatalog();

        try {
            $this->validateBookingRequest([
                'staff_id' => null,
                'status' => BookingStatus::Confirmed->value,
            ]);
            $this->fail('A responsible employee is required when confirming a booking.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('staff_id', $exception->errors());
            $this->assertSame('Vui lòng chọn nhân viên phụ trách.', $exception->errors()['staff_id'][0]);
        }

        $validated = $this->validateBookingRequest([
            'staff_id' => 1,
            'status' => BookingStatus::Confirmed->value,
        ]);
        $this->assertSame(1, $validated['staff_id']);
    }

    public function test_booking_request_requires_the_amount_matching_the_selected_unit(): void
    {
        $this->createRequestCatalog();

        try {
            $this->validateBookingRequest([
                'items' => [
                    ['DichVuID' => 1, 'LoaiDoGiatID' => 2, 'DonViTinhID' => 2, 'SoLuong' => null, 'KhoiLuong' => 1],
                ],
            ]);
            $this->fail('Piece-based units must require quantity and reject weight.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('items.0.SoLuong', $exception->errors());
        }
    }

    public function test_order_conversion_rejects_snapshot_amount_that_conflicts_with_its_unit(): void
    {
        DB::table('DonViTinh')->insert([
            'DonViTinhID' => 2,
            'TenDonViTinh' => 'Cái',
            'KyHieu' => 'Cái',
            'TrangThai' => 'Hoạt động',
        ]);
        $booking = $this->createBooking([
        ]);
        DB::table('ChiTietBooking')->insert([
            'BookingID' => $booking->BookingID,
            'DichVuID' => 1,
            'LoaiDoGiatID' => 2,
            'DonViTinhID' => 2,
            'KhoiLuong' => 1,
            'DonGia' => 10000,
            'ThanhTien' => 10000,
        ]);
        $this->actingAsBookingEmployee();

        try {
            $this->bookingService->confirmPendingBooking($booking, 1);
            $this->fail('A piece-based unit must not convert a weight snapshot.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('items', $exception->errors());
        }

        $this->assertSame(BookingStatus::Pending->value, $booking->fresh()->TrangThai);
        $this->assertSame(0, DonHang::query()->count());
    }

    private function createBooking(array $overrides = []): Booking
    {
        return Booking::query()->create(array_merge([
            'KhachHangID' => 9,
            'NhanVienID' => 1,
            'HinhThucNhanDo' => 'Tại nhà',
            'DiaChiNhan' => '12 Nguyễn Huệ',
            'NgayHen' => '2026-10-05',
            'GioHen' => '14:30:00',
            'TrangThai' => BookingStatus::Pending->value,
        ], $overrides));
    }

    private function createRequestCatalog(): void
    {
        DB::table('KhachHang')->insert(['KhachHangID' => 9]);
        DB::table('DichVu')->insert(['DichVuID' => 1]);
        DB::table('LoaiDoGiat')->insert(['LoaiDoGiatID' => 2]);
        DB::table('DonViTinh')->insert([
            ['DonViTinhID' => 1, 'TenDonViTinh' => 'Kilogram', 'KyHieu' => 'KG', 'TrangThai' => 'Hoạt động'],
            ['DonViTinhID' => 2, 'TenDonViTinh' => 'Cái', 'KyHieu' => 'Cái', 'TrangThai' => 'Hoạt động'],
        ]);
    }

    private function validateBookingRequest(array $snapshot): array
    {
        $request = LuuBookingRequest::create('/admin/bookings/1', 'PUT', array_merge([
            'customer_id' => 9,
            'staff_id' => null,
            'method' => 'Tại nhà',
            'address' => '12 Nguyễn Huệ',
            'scheduled_date' => '2026-10-05',
            'scheduled_time' => '14:30',
            'notes' => null,
            'status' => BookingStatus::Pending->value,
        ], $snapshot));
        $request->setContainer($this->app);
        $request->setRedirector($this->app['redirect']);
        $request->validateResolved();

        return $request->validated();
    }

    private function validateOrderRequest(array $snapshot, string $method = 'POST'): array
    {
        $request = LuuDonHangRequest::create('/admin/orders/1', $method, array_merge([
            'KhachHangID' => 9,
            'NhanVienID' => 1,
            'TrangThai' => OrderStatus::Pending->value,
        ], $snapshot));
        $request->setContainer($this->app);
        $request->setRedirector($this->app['redirect']);
        $request->validateResolved();

        return $request->validated();
    }

    private function actingAsBookingEmployee(): void
    {
        $user = new User;
        $user->forceFill([
            'TaiKhoanID' => 1,
            'NhanVienID' => 1,
            'TrangThai' => 'Hoạt động',
        ]);
        $this->actingAs($user);
    }

    private function createSchema(): void
    {
        Schema::create('KhachHang', function (Blueprint $table): void {
            $table->increments('KhachHangID');
        });

        Schema::create('DichVu', function (Blueprint $table): void {
            $table->increments('DichVuID');
        });

        Schema::create('LoaiDoGiat', function (Blueprint $table): void {
            $table->increments('LoaiDoGiatID');
        });

        Schema::create('NhanVien', function (Blueprint $table): void {
            $table->increments('NhanVienID');
            $table->string('HoTen')->nullable();
        });
        DB::table('NhanVien')->insert([
            'NhanVienID' => 1,
            'HoTen' => 'Nhân viên kiểm thử',
        ]);

        Schema::create('Booking', function (Blueprint $table): void {
            $table->increments('BookingID');
            $table->string('MaBooking')->unique();
            $table->unsignedInteger('KhachHangID');
            $table->string('HinhThucNhanDo');
            $table->string('DiaChiNhan')->nullable();
            $table->date('NgayHen');
            $table->time('GioHen');
            $table->string('GhiChu', 500)->nullable();
            $table->string('TrangThai');
            $table->dateTime('NgayTao')->useCurrent();
            $table->dateTime('NgayCapNhat')->nullable();
            $table->unsignedInteger('NhanVienID')->nullable();
            $table->unsignedInteger('NhanVienXacNhanID')->nullable();
            $table->dateTime('ThoiGianXacNhan')->nullable();
        });

        Schema::create('ChiTietBooking', function (Blueprint $table): void {
            $table->increments('ChiTietBookingID');
            $table->unsignedInteger('BookingID');
            $table->unsignedInteger('DichVuID');
            $table->unsignedInteger('LoaiDoGiatID');
            $table->unsignedInteger('DonViTinhID');
            $table->decimal('SoLuong', 10, 2)->nullable();
            $table->decimal('KhoiLuong', 10, 2)->nullable();
            $table->decimal('DonGia', 18, 2)->default(0);
            $table->decimal('ThanhTien', 18, 2)->default(0);
            $table->string('GhiChu', 500)->nullable();
        });

        Schema::create('NhatKyHeThong', function (Blueprint $table): void {
            $table->increments('NhatKyID');
            $table->unsignedInteger('TaiKhoanID')->nullable();
            $table->string('HanhDong');
            $table->string('BangDuLieu');
            $table->unsignedBigInteger('BanGhiID')->nullable();
            $table->json('DuLieuCu')->nullable();
            $table->json('DuLieuMoi')->nullable();
            $table->string('LyDo')->nullable();
            $table->dateTime('ThoiGian')->useCurrent();
            $table->string('IPAddress')->nullable();
            $table->text('UserAgent')->nullable();
        });

        Schema::create('DonHang', function (Blueprint $table): void {
            $table->increments('DonHangID');
            $table->string('MaDonHang')->unique();
            $table->unsignedInteger('BookingID')->nullable()->unique();
            $table->unsignedInteger('KhachHangID');
            $table->unsignedInteger('NhanVienID')->nullable();
            $table->string('TrangThai');
            $table->decimal('TongTien', 18, 2)->default(0);
            $table->integer('DiemSuDung')->default(0);
            $table->decimal('TienGiamDoDiem', 18, 2)->default(0);
            $table->unsignedInteger('KhuyenMaiID')->nullable();
            $table->decimal('TienGiamKhuyenMai', 18, 2)->default(0);
            $table->decimal('PhiGiaoHang', 18, 2)->default(0);
            $table->decimal('ThanhTien', 18, 2)->default(0);
            $table->string('GhiChu', 500)->nullable();
            $table->dateTime('NgayTao')->useCurrent();
            $table->dateTime('NgayCapNhat')->nullable();
        });

        Schema::create('ChiTietDonHang', function (Blueprint $table): void {
            $table->increments('ChiTietDonHangID');
            $table->unsignedInteger('DonHangID');
            $table->unsignedInteger('DichVuID');
            $table->unsignedInteger('LoaiDoGiatID');
            $table->unsignedInteger('DonViTinhID');
            $table->decimal('SoLuong', 10, 2)->nullable();
            $table->decimal('KhoiLuong', 10, 2)->nullable();
            $table->decimal('DonGia', 18, 2);
            $table->decimal('ThanhTien', 18, 2);
            $table->string('GhiChu', 500)->nullable();
        });

        Schema::create('GiaoNhan', function (Blueprint $table): void {
            $table->increments('GiaoNhanID');
            $table->unsignedInteger('DonHangID');
            $table->unsignedInteger('NhanVienID')->nullable();
            $table->string('LoaiGiaoNhan');
            $table->string('HinhThuc');
            $table->string('DiaChi', 255)->nullable();
            $table->dateTime('ThoiGianDuKien')->nullable();
            $table->dateTime('ThoiGianThucTe')->nullable();
            $table->decimal('PhiGiaoNhan', 18, 2)->default(0);
            $table->string('TrangThai');
            $table->string('GhiChu', 500)->nullable();
        });

        Schema::create('HoaDon', function (Blueprint $table): void {
            $table->increments('HoaDonID');
            $table->unsignedInteger('DonHangID');
        });

        Schema::create('ThanhToan', function (Blueprint $table): void {
            $table->increments('ThanhToanID');
            $table->unsignedInteger('DonHangID');
        });

        Schema::create('DiemTichLuy', function (Blueprint $table): void {
            $table->increments('DiemTichLuyID');
            $table->unsignedInteger('KhachHangID')->unique();
            $table->integer('DiemHienTai')->default(0);
            $table->dateTime('NgayCapNhat')->nullable();
        });

        Schema::create('DonViTinh', function (Blueprint $table): void {
            $table->increments('DonViTinhID');
            $table->string('TenDonViTinh');
            $table->string('KyHieu')->nullable();
            $table->string('TrangThai');
        });

        Schema::create('BangGia', function (Blueprint $table): void {
            $table->increments('BangGiaID');
            $table->unsignedInteger('DichVuID');
            $table->unsignedInteger('LoaiDoGiatID');
            $table->unsignedInteger('DonViTinhID');
            $table->decimal('DonGia', 18, 2);
            $table->date('NgayApDung')->nullable();
            $table->date('NgayKetThuc')->nullable();
            $table->string('TrangThai');
        });
    }
}
