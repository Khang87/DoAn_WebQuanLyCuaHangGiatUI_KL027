<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\OrderStatus;
use App\Http\Middleware\EnsureUserHasPermission;
use App\Http\Middleware\RejectCustomerRole;
use App\Http\Middleware\RestoreRememberedLogin;
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
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\ValidationException;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
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
        foreach (['NhatKyHeThong', 'ChiTietBooking', 'ChiTietDonHang', 'GiaoNhan', 'HoaDon', 'ThanhToan', 'DiemTichLuy', 'DonHang', 'Booking', 'BangGia', 'DonViTinh', 'LoaiDoGiat', 'DanhMucLoaiDoGiat', 'DichVu', 'LoaiDichVu', 'NhanVien', 'TaiKhoan', 'KhachHang'] as $table) {
            Schema::dropIfExists($table);
        }

        parent::tearDown();
    }

    public function test_manual_order_store_rejects_completed_status_and_booking_link(): void
    {
        $this->createRequestCatalog();
        $this->withoutMiddleware([Authenticate::class, EnsureUserHasPermission::class, RejectCustomerRole::class, RestoreRememberedLogin::class]);
        $payload = [
            'KhachHangID' => 9,
            'NhanVienID' => 1,
            'TrangThai' => OrderStatus::Paid->value,
            'items' => [['DichVuID' => 1, 'LoaiDoGiatID' => 2, 'DonViTinhID' => 2, 'SoLuong' => 1, 'TinhTrangTruocKhiGiat' => 'Bình thường']],
        ];
        $this->post(route('orders.store'), $payload)->assertSessionHasErrors('TrangThai');
        $payload['TrangThai'] = OrderStatus::Received->value;
        $payload['BookingID'] = 99;
        $this->post(route('orders.store'), $payload)->assertSessionHasErrors('BookingID');
        $this->assertSame(0, DonHang::query()->count());
        $this->assertSame(0, ChiTietDonHang::query()->count());
    }

    public function test_manual_order_store_requires_inspected_items_and_condition(): void
    {
        $this->createRequestCatalog();
        $this->withoutMiddleware([Authenticate::class, EnsureUserHasPermission::class, RejectCustomerRole::class, RestoreRememberedLogin::class]);
        $payload = ['KhachHangID' => 9, 'NhanVienID' => 1, 'TrangThai' => OrderStatus::Received->value];
        $this->post(route('orders.store'), $payload)->assertSessionHasErrors('items');
        $payload['items'] = [['DichVuID' => 1, 'LoaiDoGiatID' => 2, 'DonViTinhID' => 2, 'SoLuong' => 1]];
        $this->post(route('orders.store'), $payload)->assertSessionHasErrors('items.0.TinhTrangTruocKhiGiat');
        $payload['items'][0]['TinhTrangTruocKhiGiat'] = '   ';
        $this->post(route('orders.store'), $payload)->assertSessionHasErrors('items.0.TinhTrangTruocKhiGiat');
        $this->assertSame(0, DonHang::query()->count());
        $this->assertSame(0, ChiTietDonHang::query()->count());
    }

    public function test_manual_order_creation_form_collects_condition_and_fixes_initial_status(): void
    {
        $this->createRequestCatalog();
        Schema::create('KhuyenMai', function (Blueprint $table): void {
            $table->increments('KhuyenMaiID');
            $table->string('TrangThai');
        });
        try {
            $this->withoutMiddleware([Authenticate::class, EnsureUserHasPermission::class, RejectCustomerRole::class, RestoreRememberedLogin::class]);
            $this->get(route('orders.create'))
                ->assertOk()
                ->assertSee('name="items[0][TinhTrangTruocKhiGiat]"', false)
                ->assertSee('name="items[${index}][TinhTrangTruocKhiGiat]"', false)
                ->assertSee('name="TrangThai" value="Đã tiếp nhận"', false)
                ->assertDontSee('name="TrangThai" id="status"', false);
        } finally {
            Schema::dropIfExists('KhuyenMai');
        }
    }

    public function test_manual_order_store_persists_actual_condition_before_washing(): void
    {
        $this->createRequestCatalog();
        DB::table('BangGia')->insert(['DichVuID' => 1, 'LoaiDoGiatID' => 2, 'DonViTinhID' => 2, 'DonGia' => 10000, 'NgayApDung' => '2026-01-01', 'TrangThai' => 'Hoạt động']);
        $this->actingAsBookingEmployee();
        $this->withoutMiddleware([Authenticate::class, EnsureUserHasPermission::class, RejectCustomerRole::class, RestoreRememberedLogin::class]);
        $this->post(route('orders.store'), [
            'KhachHangID' => 9,
            'NhanVienID' => 1,
            'TrangThai' => OrderStatus::Received->value,
            'items' => [['DichVuID' => 1, 'LoaiDoGiatID' => 2, 'DonViTinhID' => 2, 'SoLuong' => 2, 'TinhTrangTruocKhiGiat' => '  Ố nhẹ ở cổ áo  ']],
        ])->assertSessionHasNoErrors();
        $order = DonHang::query()->sole();
        $this->assertNull($order->BookingID);
        $this->assertSame(OrderStatus::Received->value, $order->TrangThai);
        $this->assertSame('Ố nhẹ ở cổ áo', $order->chiTietDonHangs()->sole()->TinhTrangTruocKhiGiat);
        $this->assertSame(20000.0, $order->ThanhTien);
        app(OrderService::class)->updateStatus($order, OrderStatus::Washing->value);
        $this->assertSame(OrderStatus::Washing->value, $order->fresh()->TrangThai);
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
            'return_method' => 'Tại cửa hàng',
            'scheduled_date' => '2026-10-05',
            'scheduled_time' => '14:30',
            'items' => [
                ['DichVuID' => 1, 'LoaiDoGiatID' => 2, 'DonViTinhID' => 3, 'SoLuong' => 2],
                ['DichVuID' => 1, 'LoaiDoGiatID' => 2, 'DonViTinhID' => 3, 'SoLuong' => 1],
            ],
        ]);
        $this->bookingService->inspectBookingAndCreateOrder($booking->fresh(), 1, $this->inspectionItems($booking));

        $order = DonHang::query()->where('BookingID', $booking->BookingID)->firstOrFail();
        $items = ChiTietDonHang::query()->where('DonHangID', $order->DonHangID)->orderBy('ChiTietDonHangID')->get();
        $item = $items->firstOrFail();
        $delivery = GiaoNhan::query()->where('DonHangID', $order->DonHangID)->firstOrFail();

        $this->assertCount(2, $items);
        $this->assertSame('DH'.str_pad((string) $order->DonHangID, 4, '0', STR_PAD_LEFT), $order->MaDonHang);
        $this->assertSame(OrderStatus::Received->value, $order->TrangThai);
        $this->assertSame(45000.0, (float) $order->TongTien);
        $this->assertSame(45000.0, (float) $order->ThanhTien);
        $this->assertSame(1, $item->DichVuID);
        $this->assertSame(2, $item->LoaiDoGiatID);
        $this->assertSame(3, $item->DonViTinhID);
        $this->assertSame(2.0, (float) $item->SoLuong);
        $this->assertSame(15000.0, (float) $item->DonGia);
        $this->assertSame(30000.0, (float) $item->ThanhTien);
        $this->assertSame('NHAN_DO', $delivery->LoaiGiaoNhan);
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
        $this->assertSame(['TrangThai' => OrderStatus::Received->value], $orderAudit->DuLieuMoi);
        $audit = NhatKyHeThong::query()->where('BangDuLieu', 'Booking')->where('HanhDong', 'Xác nhận Booking')->firstOrFail();
        $this->assertSame(1, NhatKyHeThong::query()->where('BangDuLieu', 'Booking')->where('HanhDong', 'Xác nhận Booking')->count());
        $this->assertSame('Xác nhận Booking', $audit->HanhDong);
        $this->assertCount(2, $audit->DuLieuMoi['ChiTietBooking']);
        $this->assertSame(1, $audit->DuLieuMoi['Booking']['NhanVienXacNhanID']);
        $this->assertNotEmpty($audit->DuLieuMoi['Booking']['ThoiGianXacNhan']);

        $sameOrder = $this->bookingService->inspectBookingAndCreateOrder($booking->fresh(), 1, $this->inspectionItems($booking));
        $this->assertSame($order->DonHangID, $sameOrder?->DonHangID);
        $this->assertSame(1, DonHang::query()->where('BookingID', $booking->BookingID)->count());

        $this->bookingService->update($booking->fresh(), [
            'status' => BookingStatus::Confirmed->value,
        ]);
        $this->assertSame(1, DonHang::query()->where('BookingID', $booking->BookingID)->count());
        $this->assertSame(1, NhatKyHeThong::query()->where('BangDuLieu', 'Booking')->where('HanhDong', 'Xác nhận Booking')->count());
    }

    public function test_booking_edit_shows_customer_details_from_the_linked_account_when_customer_fields_are_missing(): void
    {
        DB::table('KhachHang')->insertOrIgnore(['KhachHangID' => 9]);
        DB::table('TaiKhoan')->insert([
            'TaiKhoanID' => 4,
            'KhachHangID' => 9,
            'TenDangNhap' => 'Khách hàng liên kết',
            'SoDienThoai' => '0901234567',
            'Email' => 'customer@example.test',
        ]);
        $booking = $this->createBooking();

        $booking = $this->bookingService->find($booking->BookingID);
        $content = view('admin.bookings.edit', [
            'booking' => $booking,
            'responsibleEmployeeLocked' => false,
            'employees' => collect(),
            'services' => collect(),
            'serviceOptions' => collect(),
            'serviceCategories' => collect(),
            'garments' => collect(),
            'units' => collect(),
            'pricingUnitOptions' => collect(),
            'errors' => new ViewErrorBag,
        ])->render();

        $this->assertStringContainsString('value="Khách hàng liên kết" disabled', $content);
        $this->assertStringContainsString('value="0901234567" disabled', $content);
        $this->assertStringContainsString('value="customer@example.test" disabled', $content);
        $this->assertStringContainsString('bg-light text-muted', $content);
        $this->assertStringNotContainsString('name="HoTen"', $content);
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

        $this->from(route('bookings.show', $booking->BookingID))
            ->post(route('bookings.confirm', $booking->BookingID))
            ->assertRedirect(route('bookings.inspection', $booking))
            ->assertSessionHasErrors('staff_id');
        $this->assertSame(0, DonHang::query()->count());

        DB::table('NhanVien')->insert([
            'NhanVienID' => 2,
            'HoTen' => 'Nhân viên được giao',
        ]);
        $response = $this->post(route('bookings.confirm', $booking->BookingID), $this->inspectionPayload($booking, 2));

        $order = DonHang::query()->where('BookingID', $booking->BookingID)->firstOrFail();
        $response->assertRedirect(route('orders.show', $order));
        $this->assertSame(BookingStatus::Confirmed->value, $booking->fresh()->TrangThai);
        $this->assertSame(2, $order->NhanVienID);
        $this->assertSame(2, $booking->fresh()->NhanVienID);
        $this->assertSame(1, $booking->fresh()->NhanVienXacNhanID);
        $this->assertNotNull($booking->fresh()->ThoiGianXacNhan);
        $this->assertSame(1, DonHang::query()->where('BookingID', $booking->BookingID)->count());
        $this->assertSame(1, GiaoNhan::query()->where('DonHangID', $order->DonHangID)->count());

        $this->post(route('bookings.confirm', $booking->BookingID), $this->inspectionPayload($booking, 2))
            ->assertRedirect(route('orders.show', $order));
        $this->assertSame(1, DonHang::query()->where('BookingID', $booking->BookingID)->count());
    }

    public function test_booking_employee_cannot_be_changed_after_an_order_is_created(): void
    {
        $booking = $this->createBooking([
            'TrangThai' => BookingStatus::Confirmed->value,
            'NhanVienID' => 1,
        ]);
        DonHang::query()->create([
            'MaDonHang' => 'DH002',
            'BookingID' => $booking->BookingID,
            'KhachHangID' => 9,
            'TrangThai' => OrderStatus::Pending->value,
        ]);
        DB::table('NhanVien')->insert([
            'NhanVienID' => 2,
            'HoTen' => 'Nhân viên khác',
        ]);

        try {
            $this->bookingService->update($booking, ['staff_id' => 2]);
            $this->fail('The responsible employee must be immutable after order conversion.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('staff_id', $exception->errors());
        }

        $this->assertSame(1, $booking->fresh()->NhanVienID);
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

    public function test_booking_update_does_not_allow_changing_its_customer(): void
    {
        $booking = $this->createBooking();
        $this->actingAsBookingEmployee();

        $updatedBooking = $this->bookingService->update($booking, [
            'customer_id' => 999,
            'address' => 'Địa chỉ mới',
        ]);

        $this->assertSame(9, $updatedBooking->KhachHangID);
        $this->assertSame('Địa chỉ mới', $updatedBooking->DiaChiNhan);
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
            ->assertRedirect(route('bookings.inspection', $booking))
            ->assertSessionHasErrors([
                'staff_id' => 'Vui lòng chọn nhân viên phụ trách.',
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
        $this->app->instance(BookingService::class, new BookingService($orderService, app(PricingService::class)));
        Log::shouldReceive('error')
            ->once()
            ->with('Booking operation failed', \Mockery::on(
                fn (array $context): bool => $context['booking_id'] === $booking->BookingID
                    && $context['operation'] === 'confirm'
                    && $context['sql_state'] === '42703'
                    && str_contains($context['database_error'], 'record "old" has no field "trangthai"')
                    && ! str_contains($context['database_error'], 'insert into')
            ));

        $response = $this->post(route('bookings.confirm', $booking->BookingID), $this->inspectionPayload($booking, 1));

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
        $this->assertSame(1, NhatKyHeThong::query()->count());
        $this->assertSame(0, NhatKyHeThong::query()->where('HanhDong', 'Xác nhận Booking')->count());
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
            ->post(route('bookings.confirm', $booking->BookingID), $this->inspectionPayload($booking, 1))
            ->assertRedirect(route('bookings.inspection', $booking))
            ->assertSessionHasErrors([
                'items' => 'Vui lòng nhập ít nhất một dòng kiểm tra thực tế trước khi tạo đơn.',
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
            ->post(route('bookings.confirm', $booking->BookingID), $this->inspectionPayload($booking, 1))
            ->assertRedirect(route('bookings.inspection', $booking))
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
            $this->bookingService->inspectBookingAndCreateOrder($booking->fresh(), 1, $this->inspectionItems($booking));
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
        $this->assertSame(1, NhatKyHeThong::query()->count());
        $this->assertSame(0, NhatKyHeThong::query()->where('HanhDong', 'Xác nhận Booking')->count());
    }

    public function test_booking_creation_is_written_to_the_system_audit_log(): void
    {
        $this->actingAsBookingEmployee();

        $booking = $this->bookingService->create([
            'customer_id' => 9,
            'method' => 'Tại nhà',
            'address' => '12 Nguyễn Huệ',
            'return_method' => 'Tại cửa hàng',
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
        $this->bookingService->inspectBookingAndCreateOrder($booking, 1, $this->inspectionItems($booking));

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
                'DonGia' => 16000,
                'TinhTrangTruocKhiGiat' => 'Có vết ố ở tay áo',
                'GhiChu' => 'Khách báo vết bẩn từ trước',
            ],
            [
                'DichVuID' => 2,
                'LoaiDoGiatID' => 2,
                'DonViTinhID' => 2,
                'SoLuong' => 1,
                'TinhTrangTruocKhiGiat' => 'Không phát hiện hư hỏng',
            ],
        ]);

        $this->assertSame(OrderStatus::Received->value, $receivedOrder->TrangThai);
        $this->assertSame(68000.0, (float) $receivedOrder->TongTien);
        $this->assertSame(16000.0, (float) $item->fresh()->DonGia);
        $this->assertSame('Có vết ố ở tay áo', $item->fresh()->TinhTrangTruocKhiGiat);
        $this->assertSame('[Tình trạng: Có vết ố ở tay áo; Loại: Áo sơ mi] Khách báo vết bẩn từ trước', $item->fresh()->GhiChu);
        $this->assertNull($itemToRemove->fresh());
        $this->assertSame(2, $receivedOrder->chiTietDonHangs->count());
        $this->assertSame(
            '[Tình trạng: Không phát hiện hư hỏng; Loại: Áo sơ mi]',
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
                'DonGia' => 15000,
                'TinhTrangTruocKhiGiat' => 'Bung chỉ nhẹ',
                'GhiChu' => 'Khách hẹn lấy gấp',
            ]],
        ])->assertRedirect(route('orders.show', $order));

        $this->assertSame(OrderStatus::Received->value, $order->fresh()->TrangThai);
        $this->assertSame('Bung chỉ nhẹ', $item->fresh()->TinhTrangTruocKhiGiat);
        $this->assertSame('[Tình trạng: Bung chỉ nhẹ; Loại: Áo sơ mi] Khách hẹn lấy gấp', $item->fresh()->GhiChu);

        try {
            app(OrderService::class)->updateStatus($order->fresh(), OrderStatus::Washed->value);
            $this->fail('A received order must enter washing before advancing.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('TrangThai', $exception->errors());
        }

        $washingOrder = app(OrderService::class)->updateStatus($order->fresh(), OrderStatus::Washing->value);
        $this->assertSame(OrderStatus::Washing->value, $washingOrder->TrangThai);
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
            'TrangThai' => OrderStatus::Received->value,
            'items' => [[
                'TinhTrangTruocKhiGiat' => 'Bình thường',
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
                'TrangThai' => OrderStatus::Received->value,
                'items' => [[
                    'TinhTrangTruocKhiGiat' => 'Bình thường',
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
            $this->bookingService->inspectBookingAndCreateOrder($booking, 1, $this->inspectionItems($booking));
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
            'TrangThai' => OrderStatus::Received->value,
            'items' => [[
                'TinhTrangTruocKhiGiat' => 'Bình thường',
                'DichVuID' => 1,
                'LoaiDoGiatID' => 2,
                'DonViTinhID' => 1,
                'KhoiLuong' => 1.239,
            ], [
                'TinhTrangTruocKhiGiat' => 'Bình thường',
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
        DB::table('BangGia')->insert(['DichVuID' => 1, 'LoaiDoGiatID' => 2, 'DonViTinhID' => 2, 'DonGia' => 10000, 'TrangThai' => 'Hoạt động']);
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
            $this->bookingService->inspectBookingAndCreateOrder($booking, 1, $this->inspectionItems($booking));
            $this->fail('A piece-based unit must not convert a weight snapshot.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('items.0.SoLuong', $exception->errors());
        }

        $this->assertSame(BookingStatus::Pending->value, $booking->fresh()->TrangThai);
        $this->assertSame(0, DonHang::query()->count());
    }

    #[DataProvider('deliveryCombinations')]
    public function test_inspection_creates_only_the_home_delivery_legs(string $receive, string $return, array $types): void
    {
        $this->createInspectionCatalog();
        $booking = $this->createBooking([
            'HinhThucNhanDo' => $receive,
            'HinhThucTraDo' => $return,
            'DiaChiTra' => '88 Lê Lợi',
        ]);
        $this->actingAsBookingEmployee();
        $items = $this->actualItems();
        $order = $this->bookingService->inspectBookingAndCreateOrder($booking, 1, $items);
        $deliveries = $order->giaoNhans()->orderBy('GiaoNhanID')->get();

        $this->assertSame($types, $deliveries->pluck('LoaiGiaoNhan')->all());
        $this->assertSame(OrderStatus::Received->value, $order->TrangThai);
        $this->assertSame('Ố nhẹ ở cổ áo', $order->chiTietDonHangs->first()->TinhTrangTruocKhiGiat);
        foreach ($deliveries as $delivery) {
            $this->assertSame($delivery->LoaiGiaoNhan === 'NHAN_DO' ? '12 Nguyễn Huệ' : '88 Lê Lợi', $delivery->DiaChi);
            if ($delivery->LoaiGiaoNhan === 'GIAO_DO') {
                $this->assertNull($delivery->ThoiGianDuKien);
            }
        }
        $sameOrder = $this->bookingService->inspectBookingAndCreateOrder($booking->fresh(), 1, $items);
        $this->assertSame($order->DonHangID, $sameOrder->DonHangID);
        $this->assertSame(count($types), GiaoNhan::query()->count());
        $this->assertSame(1, DonHang::query()->count());
    }

    public static function deliveryCombinations(): array
    {
        return [
            'TC-BK-RETURN-01' => ['Tại cửa hàng', 'Tại cửa hàng', []],
            'TC-BK-RETURN-02' => ['Tại cửa hàng', 'Tại nhà', ['GIAO_DO']],
            'TC-BK-RETURN-03' => ['Tại nhà', 'Tại cửa hàng', ['NHAN_DO']],
            'TC-BK-RETURN-04' => ['Tại nhà', 'Tại nhà', ['NHAN_DO', 'GIAO_DO']],
        ];
    }

    public function test_order_uses_actual_inspection_data_and_server_price_without_replacing_booking_estimates(): void
    {
        $this->createInspectionCatalog();
        $booking = $this->createBooking();
        $this->bookingService->update($booking, ['items' => $this->actualItems()]);
        $this->assertSame(0, DonHang::query()->count());
        $this->actingAsBookingEmployee();
        $actual = $this->actualItems();
        $actual[0]['SoLuong'] = 5;
        $actual[0]['DonGia'] = 1;
        $actual[0]['ThanhTien'] = 1;
        $actual[0]['GhiChu'] = 'Giặt riêng';
        $order = $this->bookingService->inspectBookingAndCreateOrder($booking, 1, $actual);

        $this->assertSame(75000.0, (float) $order->ThanhTien);
        $this->assertSame(5.0, (float) $order->chiTietDonHangs->first()->SoLuong);
        $this->assertSame(15000.0, (float) $order->chiTietDonHangs->first()->DonGia);
        $this->assertSame('Giặt riêng', $order->chiTietDonHangs->first()->GhiChu);
        $this->assertSame(2.0, (float) $booking->chiTietBookings()->first()->SoLuong);
        app(OrderService::class)->updateStatus($order, OrderStatus::Washing->value);
        $this->assertSame(OrderStatus::Washing->value, $order->fresh()->TrangThai);
    }

    #[DataProvider('invalidInspectionInputs')]
    public function test_invalid_inspection_rolls_back_every_created_record(array $bookingData, array $itemData, string $errorKey): void
    {
        $this->createInspectionCatalog();
        $booking = $this->createBooking($bookingData);
        $this->actingAsBookingEmployee();
        $actual = [array_merge($this->actualItems()[0], $itemData)];
        try {
            $this->bookingService->inspectBookingAndCreateOrder($booking, 1, $actual);
            $this->fail('Invalid receiving data must not create an order.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($errorKey, $exception->errors());
        }
        $this->assertSame(BookingStatus::Pending->value, $booking->fresh()->TrangThai);
        $this->assertNull($booking->fresh()->ThoiGianXacNhan);
        $this->assertSame(0, DonHang::query()->count());
        $this->assertSame(0, ChiTietDonHang::query()->count());
        $this->assertSame(0, GiaoNhan::query()->count());
        $this->assertSame(0, NhatKyHeThong::query()->count());
    }

    public static function invalidInspectionInputs(): array
    {
        return [
            'missing legacy return method' => [['HinhThucTraDo' => null], [], 'HinhThucTraDo'],
            'invalid return method' => [['HinhThucTraDo' => 'GIAO_DO'], [], 'HinhThucTraDo'],
            'missing home return address' => [['HinhThucTraDo' => 'Tại nhà', 'DiaChiTra' => null], [], 'DiaChiTra'],
            'missing home receive address' => [['DiaChiNhan' => null], [], 'DiaChiNhan'],
            'missing condition' => [[], ['TinhTrangTruocKhiGiat' => null], 'items.0.TinhTrangTruocKhiGiat'],
            'blank condition' => [[], ['TinhTrangTruocKhiGiat' => '   '], 'items.0.TinhTrangTruocKhiGiat'],
            'overlong condition' => [[], ['TinhTrangTruocKhiGiat' => str_repeat('a', 321)], 'items.0.TinhTrangTruocKhiGiat'],
            'fractional pieces' => [[], ['SoLuong' => 1.5], 'items.0.SoLuong'],
            'both measurements' => [[], ['KhoiLuong' => 2], 'items.0.SoLuong'],
        ];
    }

    public function test_return_changes_are_audited_and_searchable_without_changing_receive_fields(): void
    {
        $booking = $this->createBooking();
        $this->actingAsBookingEmployee();
        $this->bookingService->update($booking, ['return_method' => 'Tại nhà', 'return_address' => '88 Lê Lợi']);
        $audit = NhatKyHeThong::query()->where('HanhDong', 'Cập nhật Booking')->firstOrFail();
        $this->assertSame('Tại cửa hàng', $audit->DuLieuCu['Booking']['HinhThucTraDo']);
        $this->assertSame('Tại nhà', $audit->DuLieuMoi['Booking']['HinhThucTraDo']);
        $this->assertSame('88 Lê Lợi', $audit->DuLieuMoi['Booking']['DiaChiTra']);
        $this->assertSame('12 Nguyễn Huệ', $booking->fresh()->DiaChiNhan);
        $this->assertSame(1, $this->bookingService->getAll(['search' => 'Lê Lợi'])->total());
        $this->assertSame(1, $this->bookingService->getAll(['return_method' => 'Tại nhà'])->total());
        $this->assertSame(0, $this->bookingService->getAll(['return_method' => 'Tại cửa hàng'])->total());
    }

    public function test_request_requires_explicit_return_method_and_home_return_address(): void
    {
        $this->createRequestCatalog();
        foreach ([['return_method' => null], ['return_method' => 'Tại nhà', 'return_address' => null]] as $invalid) {
            try {
                $this->validateBookingRequest($invalid);
                $this->fail('Missing return information must be rejected.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey(isset($invalid['return_method']) ? 'return_address' : 'return_method', $exception->errors());
            }
        }
    }

    public function test_opening_inspection_shows_required_condition_and_does_not_create_an_order(): void
    {
        $this->createInspectionCatalog();
        $booking = $this->createBooking(['HinhThucTraDo' => null]);
        $this->bookingService->update($booking, ['items' => $this->actualItems()]);
        $this->withoutMiddleware([Authenticate::class, EnsureUserHasPermission::class, RejectCustomerRole::class, RestoreRememberedLogin::class]);
        $this->get(route('bookings.inspection', $booking))
            ->assertOk()
            ->assertSee('Kiểm tra thực tế trước khi tạo đơn')
            ->assertSee('TinhTrangTruocKhiGiat')
            ->assertSee('name="return_method"', false)
            ->assertSee('Xác nhận &amp; tạo đơn', false);
        $this->assertSame(BookingStatus::Pending->value, $booking->fresh()->TrangThai);
        $this->assertSame(0, DonHang::query()->count());
        $this->assertSame(0, ChiTietDonHang::query()->count());
        $this->assertSame(0, GiaoNhan::query()->count());
    }

    public function test_failure_of_the_second_delivery_rolls_back_order_booking_points_and_audit(): void
    {
        $this->createInspectionCatalog();
        $booking = $this->createBooking(['HinhThucTraDo' => 'Tại nhà', 'DiaChiTra' => '88 Lê Lợi']);
        DB::table('DiemTichLuy')->insert(['KhachHangID' => 9, 'DiemHienTai' => 500]);
        DB::statement("CREATE TRIGGER reject_return BEFORE INSERT ON GiaoNhan WHEN NEW.LoaiGiaoNhan = 'GIAO_DO' BEGIN SELECT RAISE(ABORT, 'simulated return failure'); END");
        $this->actingAsBookingEmployee();
        try {
            $this->bookingService->inspectBookingAndCreateOrder($booking, 1, $this->actualItems(), 100);
            $this->fail('A failed return slip must roll back the entire conversion.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('simulated return failure', $exception->getMessage());
        }
        $this->assertSame(BookingStatus::Pending->value, $booking->fresh()->TrangThai);
        $this->assertNull($booking->fresh()->ThoiGianXacNhan);
        $this->assertSame(500, (int) DB::table('DiemTichLuy')->value('DiemHienTai'));
        $this->assertSame(0, DonHang::query()->count());
        $this->assertSame(0, ChiTietDonHang::query()->count());
        $this->assertSame(0, GiaoNhan::query()->count());
        $this->assertSame(0, NhatKyHeThong::query()->count());
    }

    private function createInspectionCatalog(): void
    {
        DB::table('DonViTinh')->insert(['DonViTinhID' => 3, 'TenDonViTinh' => 'Cái', 'KyHieu' => 'Cái', 'TrangThai' => 'Hoạt động']);
        DB::table('BangGia')->insert(['DichVuID' => 1, 'LoaiDoGiatID' => 2, 'DonViTinhID' => 3, 'DonGia' => 15000, 'NgayApDung' => '2026-01-01', 'TrangThai' => 'Hoạt động']);
    }

    private function actualItems(): array
    {
        return [['DichVuID' => 1, 'LoaiDoGiatID' => 2, 'DonViTinhID' => 3, 'SoLuong' => 2, 'TinhTrangTruocKhiGiat' => 'Ố nhẹ ở cổ áo']];
    }

    private function createBooking(array $overrides = []): Booking
    {
        DB::table('KhachHang')->insertOrIgnore(['KhachHangID' => 9]);
        DB::table('DichVu')->insertOrIgnore(['DichVuID' => 1]);
        DB::table('LoaiDoGiat')->insertOrIgnore(['LoaiDoGiatID' => 2, 'TenLoaiDoGiat' => 'Áo sơ mi']);

        return Booking::query()->create(array_merge([
            'KhachHangID' => 9,
            'NhanVienID' => 1,
            'HinhThucNhanDo' => 'Tại nhà',
            'DiaChiNhan' => '12 Nguyễn Huệ',
            'HinhThucTraDo' => 'Tại cửa hàng',
            'NgayHen' => '2026-10-05',
            'GioHen' => '14:30:00',
            'TrangThai' => BookingStatus::Pending->value,
        ], $overrides));
    }

    private function inspectionItems(Booking $booking): array
    {
        return $booking->chiTietBookings()->get()->map(fn ($item): array => array_merge(
            $item->only(['DichVuID', 'LoaiDoGiatID', 'DonViTinhID', 'SoLuong', 'KhoiLuong']),
            ['TinhTrangTruocKhiGiat' => 'Ố nhẹ ở cổ áo'],
        ))->all();
    }

    private function inspectionPayload(Booking $booking, int $employeeId): array
    {
        return [
            'customer_id' => $booking->KhachHangID,
            'staff_id' => $employeeId,
            'method' => $booking->HinhThucNhanDo,
            'address' => $booking->DiaChiNhan,
            'return_method' => $booking->HinhThucTraDo,
            'return_address' => $booking->DiaChiTra,
            'scheduled_date' => $booking->NgayHen->format('Y-m-d'),
            'scheduled_time' => $booking->GioHen->format('H:i'),
            'items' => $this->inspectionItems($booking),
        ];
    }

    private function createRequestCatalog(): void
    {
        DB::table('KhachHang')->insertOrIgnore(['KhachHangID' => 9]);
        DB::table('DichVu')->insert(['DichVuID' => 1]);
        DB::table('DanhMucLoaiDoGiat')->insert([
            'DanhMucID' => 1,
            'TenDanhMuc' => 'Quần áo',
        ]);
        DB::table('LoaiDoGiat')->insert([
            'LoaiDoGiatID' => 2,
            'TenLoaiDoGiat' => 'Áo sơ mi',
            'DanhMucID' => 1,
        ]);
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
            'return_method' => 'Tại cửa hàng',
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
        if ($method === 'POST') {
            $snapshot['items'] = array_map(
                fn (array $item): array => array_merge(['TinhTrangTruocKhiGiat' => 'Bình thường'], $item),
                $snapshot['items'] ?? [['DichVuID' => 1, 'LoaiDoGiatID' => 2, 'DonViTinhID' => 2, 'SoLuong' => 1]],
            );
        }
        $request = LuuDonHangRequest::create('/admin/orders/1', $method, array_merge([
            'KhachHangID' => 9,
            'NhanVienID' => 1,
            'TrangThai' => $method === 'POST' ? OrderStatus::Received->value : OrderStatus::Pending->value,
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
            $table->string('HoTen')->nullable();
            $table->string('SoDienThoai')->nullable();
            $table->string('Email')->nullable();
        });

        Schema::create('TaiKhoan', function (Blueprint $table): void {
            $table->increments('TaiKhoanID');
            $table->unsignedInteger('KhachHangID')->nullable();
            $table->string('TenDangNhap');
            $table->string('SoDienThoai')->nullable();
            $table->string('Email')->nullable();
        });

        Schema::create('DichVu', function (Blueprint $table): void {
            $table->increments('DichVuID');
            $table->string('TenDichVu')->default('Giặt');
            $table->unsignedInteger('LoaiDichVuID')->nullable();
            $table->string('TrangThai')->default('Hoạt động');
        });

        Schema::create('LoaiDichVu', function (Blueprint $table): void {
            $table->increments('LoaiDichVuID');
            $table->string('TenLoaiDichVu');
        });

        Schema::create('DanhMucLoaiDoGiat', function (Blueprint $table): void {
            $table->bigIncrements('DanhMucID');
            $table->string('TenDanhMuc');
            $table->text('MoTa')->nullable();
            $table->string('TrangThai')->default('Hoạt động');
            $table->dateTime('NgayTao')->useCurrent();
        });

        Schema::create('LoaiDoGiat', function (Blueprint $table): void {
            $table->increments('LoaiDoGiatID');
            $table->string('TenLoaiDoGiat')->default('Áo sơ mi');
            $table->string('MoTa')->nullable();
            $table->string('TrangThai')->default('Hoạt động');
            $table->unsignedBigInteger('DanhMucID')->default(1);
        });

        Schema::create('NhanVien', function (Blueprint $table): void {
            $table->increments('NhanVienID');
            $table->string('TrangThai')->default('Hoạt động');
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
            $table->string('HinhThucTraDo')->nullable();
            $table->string('DiaChiTra')->nullable();
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
            $table->text('TinhTrangTruocKhiGiat')->nullable();
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
            $table->string('TrangThai')->nullable();
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
