<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\OrderStatus;
use App\Http\Middleware\EnsureUserHasPermission;
use App\Http\Middleware\RejectCustomerRole;
use App\Http\Middleware\RestoreRememberedLogin;
use App\Http\Requests\Admin\XacNhanBookingRequest;
use App\Models\BangGia;
use App\Models\Booking;
use App\Models\BookingGiaoNhan;
use App\Models\ChiTietDonHang;
use App\Models\DonHang;
use App\Models\GiaoNhan;
use App\Models\NhatKyHeThong;
use App\Models\User;
use App\Services\BookingService;
use App\Services\NotificationService;
use App\Services\OrderService;
use App\Services\PricingService;
use Carbon\Carbon;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\ValidationException;
use Mockery;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\BookingInspectionFixture;
use Tests\TestCase;

class BookingOrderConversionTest extends TestCase
{
    use BookingInspectionFixture;

    private BookingService $bookingService;

    private bool $deliveryRouteFailure = false;

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        $this->createSchema();
        (require database_path('migrations/2026_10_16_000001_create_booking_intake_documents.php'))->up();
        (require database_path('migrations/2026_10_22_000001_create_staff_api_tokens_and_booking_drafts.php'))->up();
        config([
            'cache.default' => 'array',
            'services.osm.store_address' => 'Laundry test store',
        ]);
        $this->fakeDeliveryApis();
        $this->bookingService = app(BookingService::class);
    }

    protected function tearDown(): void
    {
        foreach (['StaffBookingDraft', 'StaffApiToken', 'BookingKiemKe', 'BookingGiaoNhan', 'KhuyenMai', 'NhatKyHeThong', 'ChiTietBooking', 'ChiTietDonHang', 'GiaoNhan', 'HoaDon', 'ThanhToan', 'DiemTichLuy', 'DonHang', 'Booking', 'BangGia', 'DonViTinh', 'LoaiDoGiat', 'DanhMucLoaiDoGiat', 'DichVu', 'LoaiDichVu', 'NhanVien', 'ThongBao', 'TaiKhoan_VaiTro', 'VaiTro_Quyen', 'Quyen', 'VaiTro', 'TaiKhoan', 'KhachHang'] as $table) {
            Schema::dropIfExists($table);
        }

        parent::tearDown();
    }

    private function prepareBookingForInspection(Booking $booking): void
    {
        $this->bookingService->approve($booking);
        $this->startAssignedIntake($booking);
    }

    private function startAssignedIntake(Booking $booking): void
    {
        DB::table('BookingGiaoNhan')
            ->where('BookingID', $booking->BookingID)
            ->where('LoaiGiaoNhan', 'NHAN_DO')
            ->update(['NhanVienID' => 1]);
        $this->bookingService->markReceived($booking);
    }

    private function createPermissionTables(): void
    {
        if (! Schema::hasTable('Quyen')) {
            Schema::create('Quyen', function (Blueprint $table): void {
                $table->increments('QuyenID');
                $table->string('MaQuyen');
                $table->string('TrangThai')->default('Hoạt động');
            });
        }

        if (! Schema::hasTable('VaiTro_Quyen')) {
            Schema::create('VaiTro_Quyen', function (Blueprint $table): void {
                $table->unsignedInteger('VaiTroID');
                $table->unsignedInteger('QuyenID');
            });
        }
    }

    private function createOrderThroughInspection(Booking $booking, array $items, array $overrides = []): DonHang
    {
        $this->prepareBookingForInspection($booking);
        $data = array_replace($this->inspectionPayload($booking, 1), $overrides, ['items' => $items]);

        return $this->bookingService->submitInspection($booking, $data);
    }

    private function createConvertedOrderForBooking(Booking $booking): DonHang
    {
        return DonHang::query()->create([
            'MaDonHang' => 'DH-BOOKING-'.$booking->BookingID,
            'BookingID' => $booking->BookingID,
            'KhachHangID' => $booking->KhachHangID,
            'TrangThai' => OrderStatus::Received->value,
        ]);
    }

    public function test_two_step_booking_approval_creates_two_booking_documents_without_an_order(): void
    {
        config()->set('giatui.booking_two_step', false);
        $booking = $this->createBooking();
        $this->actingAsBookingEmployee();

        $this->bookingService->approve($booking);
        $this->bookingService->approve($booking);

        $this->assertSame(BookingStatus::Confirmed->value, $booking->fresh()->TrangThai);
        $this->assertSame(1, (int) $booking->fresh()->NhanVienXacNhanID);
        $this->assertNull(DB::table('BookingGiaoNhan')
            ->where('BookingID', $booking->BookingID)
            ->where('LoaiGiaoNhan', 'NHAN_DO')
            ->value('NhanVienID'));
        $this->assertSame(['GIAO_DO', 'NHAN_DO'], DB::table('BookingGiaoNhan')->where('BookingID', $booking->BookingID)->orderBy('LoaiGiaoNhan')->pluck('LoaiGiaoNhan')->all());
        $this->assertSame(2, DB::table('BookingGiaoNhan')->where('BookingID', $booking->BookingID)->count());
        $this->assertDatabaseCount('DonHang', 0);
        $this->assertDatabaseCount('GiaoNhan', 0);
    }

    public function test_delivery_booking_document_guides_intake_through_inspection_and_stops_after_conversion(): void
    {
        $this->createInspectionCatalog();
        $booking = $this->createBooking();
        $user = Mockery::mock(User::class)->makePartial();
        $user->forceFill([
            'TaiKhoanID' => 1,
            'NhanVienID' => 1,
            'TrangThai' => 'Hoạt động',
        ]);
        $user->shouldReceive('isManager')->andReturn(true);
        $user->shouldReceive('isOwner')->andReturn(true);
        $user->shouldReceive('isActive')->andReturn(true);
        $user->shouldReceive('canPermission')->andReturn(true);
        $this->actingAs($user);
        Gate::before(fn () => true);
        $this->withoutMiddleware();
        $this->bookingService->approve($booking);
        $this->createPermissionTables();
        DB::table('BookingGiaoNhan')
            ->where('BookingID', $booking->BookingID)
            ->where('LoaiGiaoNhan', 'NHAN_DO')
            ->update(['NhanVienID' => 1]);
        $intake = BookingGiaoNhan::query()
            ->where('BookingID', $booking->BookingID)
            ->where('LoaiGiaoNhan', 'NHAN_DO')
            ->firstOrFail();

        $this->get(route('deliveries.booking-documents.show', $intake))
            ->assertOk()
            ->assertDontSee('Xác nhận đã nhận đồ')
            ->assertSee('Kiểm tra và tạo đơn');

        $this->assertNull($intake->fresh()->ThoiGianThucTe);
        $this->assertDatabaseCount('DonHang', 0);
        $this->assertDatabaseCount('BookingKiemKe', 0);

        view()->share('errors', new ViewErrorBag);
        $this->get(route('bookings.inspection', $booking))->assertOk();
        $payload = $this->inspectionPayload($booking, 1);
        $payload['items'] = $this->actualItems();
        $this->bookingService->submitInspection($booking, $payload);

        $this->assertSame(1, DonHang::where('BookingID', $booking->BookingID)->count());
        $this->assertSame('Hoàn thành', $intake->fresh()->TrangThai);
        $this->assertNotNull($intake->fresh()->ThoiGianThucTe);
        $bookingResponse = $this->get(route('bookings.show', $booking))
            ->assertOk()
            ->assertDontSee('Kiểm kê thực tế')
            ->assertDontSee('Đối soát kiểm kê phiên bản')
            ->assertSee('Đơn hàng liên kết')
            ->assertSee('Phiếu nhận và kế hoạch trả từ Booking');
        $bookingResponse->assertSeeInOrder([
            'Thông tin lịch hẹn',
            'Ước tính từ lịch đặt',
            'data-booking-mobile-summary',
            'Khách hàng',
            'Thao tác',
            'Phiếu nhận và kế hoạch trả từ Booking',
            'Đơn hàng liên kết',
        ], false);
        $this->get(route('deliveries.booking-documents.show', $intake))
            ->assertOk()
            ->assertSee('Đơn hàng đã được tạo từ kiểm kê')
            ->assertDontSee('Kiểm tra và tạo đơn');
    }

    public function test_unconfirmed_booking_cannot_open_inspection_and_has_no_separate_receipt_route(): void
    {
        $booking = $this->createBooking();
        $user = Mockery::mock(User::class)->makePartial();
        $user->forceFill([
            'TaiKhoanID' => 1,
            'NhanVienID' => 1,
            'TrangThai' => 'Hoạt động',
        ]);
        $user->shouldReceive('isManager')->andReturn(true);
        $user->shouldReceive('isOwner')->andReturn(true);
        $user->shouldReceive('isActive')->andReturn(true);
        $user->shouldReceive('canPermission')->andReturn(true);
        $this->actingAs($user);
        $this->withoutMiddleware([
            EnsureUserHasPermission::class,
            RejectCustomerRole::class,
            RestoreRememberedLogin::class,
        ]);

        $this->get(route('bookings.inspection', $booking))
            ->assertRedirect(route('bookings.show', $booking))
            ->assertSessionHas('error');

        $this->assertFalse(Route::has('bookings.receive'));
        $this->assertSame(BookingStatus::Pending->value, $booking->fresh()->TrangThai);
        $this->assertDatabaseCount('BookingKiemKe', 0);
        $this->assertDatabaseCount('DonHang', 0);
    }

    public function test_inspection_requires_an_approved_booking_and_assigned_intake_employee(): void
    {
        $booking = $this->createBooking();
        $this->actingAsBookingEmployee();
        $this->withoutMiddleware([Authenticate::class, EnsureUserHasPermission::class, RejectCustomerRole::class, RestoreRememberedLogin::class]);

        $this->get(route('bookings.inspection', $booking))
            ->assertRedirect(route('bookings.show', $booking))
            ->assertSessionHas('error', 'Booking không còn ở trạng thái cho phép kiểm kê.');

        try {
            $this->bookingService->submitInspection($booking, $this->inspectionPayload($booking, 1));
            $this->fail('A pending Booking must not create an inspection or order.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('booking', $exception->errors());
        }

        $this->bookingService->approve($booking);
        $this->get(route('bookings.inspection', $booking))
            ->assertRedirect(route('bookings.show', $booking))
            ->assertSessionHas('error', 'Chỉ nhân viên được phân công mới có thể kiểm tra và xử lý phiếu nhận đồ.');

        try {
            $this->bookingService->submitInspection($booking, $this->inspectionPayload($booking, 1));
            $this->fail('An approved but unassigned Booking must not create an inspection or order.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('booking', $exception->errors());
        }

        $this->assertDatabaseCount('BookingKiemKe', 0);
        $this->assertDatabaseCount('DonHang', 0);
        $this->assertDatabaseCount('GiaoNhan', 0);
    }

    public function test_confirm_http_route_rejects_booking_before_actual_receipt(): void
    {
        $this->createInspectionCatalog();
        $booking = $this->createBooking();
        $this->actingAsBookingEmployee();
        $this->withoutMiddleware([EnsureUserHasPermission::class, RejectCustomerRole::class]);
        $this->bookingService->approve($booking);
        $payload = $this->inspectionPayload($booking, 1);
        $payload['items'] = $this->actualItems();

        $this->post(route('bookings.confirm', $booking->BookingID), $payload)
            ->assertRedirect(route('bookings.inspection', $booking))
            ->assertSessionHasErrors('booking');

        $this->assertDatabaseCount('BookingKiemKe', 0);
        $this->assertDatabaseCount('DonHang', 0);
        $this->assertDatabaseCount('GiaoNhan', 0);
        $this->assertDatabaseCount('BookingGiaoNhan', 2);
        $this->assertDatabaseHas('BookingGiaoNhan', [
            'BookingID' => $booking->BookingID,
            'LoaiGiaoNhan' => 'NHAN_DO',
            'TrangThai' => 'Chờ thực hiện',
        ]);
    }

    public function test_inspection_requires_customer_acceptance_and_completes_intake_atomically(): void
    {
        $this->createInspectionCatalog();
        DB::table('NhanVien')->insert([
            'NhanVienID' => 2,
            'HoTen' => 'Nhân viên phụ trách Booking',
            'TrangThai' => 'Hoạt động',
        ]);
        $booking = $this->createBooking(['NhanVienID' => 2]);
        $booking->chiTietBookings()->create([
            'DichVuID' => 1,
            'LoaiDoGiatID' => 2,
            'DonViTinhID' => 3,
            'SoLuong' => 2,
            'DonGia' => 15000,
            'ThanhTien' => 30000,
            'GhiChu' => 'Ghi chú lịch sử của mặt hàng',
        ]);
        $this->actingAsBookingEmployee();
        $this->bookingService->approve($booking);
        $this->startAssignedIntake($booking);
        $this->createPermissionTables();
        $this->withoutMiddleware([EnsureUserHasPermission::class, RejectCustomerRole::class]);
        $intake = BookingGiaoNhan::query()
            ->where('BookingID', $booking->BookingID)
            ->where('LoaiGiaoNhan', 'NHAN_DO')
            ->firstOrFail();
        $payload = $this->inspectionPayload($booking, 1);
        $payload['items'] = $this->actualItems();
        unset($payload['customer_accepted']);
        $payload['quality_difference'] = '1';

        $this->post(route('bookings.confirm', $booking), $payload)
            ->assertRedirect(route('bookings.inspection', $booking))
            ->assertSessionHasErrors('customer_accepted');
        $this->assertSame('Đang thực hiện', $intake->fresh()->TrangThai);
        $this->assertNull($intake->fresh()->ThoiGianThucTe);
        $this->assertDatabaseCount('DonHang', 0);
        $this->assertDatabaseCount('BookingKiemKe', 0);

        $payload['customer_accepted'] = '1';
        $this->post(route('bookings.confirm', $booking), $payload)
            ->assertRedirect();
        $order = DonHang::query()->where('BookingID', $booking->BookingID)->firstOrFail();
        $intake->refresh();
        $inspection = $booking->fresh()->kiemKe;
        $decision = $inspection->DuLieuThucTe['_decision'];

        $this->assertFalse($inspection->CoChenhLech);
        $this->assertArrayNotHasKey('quality_difference', $inspection->DuLieuThucTe);
        $this->assertSame('Hoàn thành', $intake->TrangThai);
        $this->assertNotNull($intake->ThoiGianThucTe);
        $this->assertSame($order->DonHangID, $intake->DonHangID);
        $this->assertSame($inspection->BookingKiemKeID, $intake->BookingKiemKeID);
        $this->assertSame(
            'Ghi chú lịch sử của mặt hàng',
            DB::table('ChiTietDonHang')->where('DonHangID', $order->DonHangID)->value('GhiChu'),
        );
        $this->assertSame(1, $decision['employee_id']);
        $this->assertTrue($decision['customer_accepted']);
        $this->assertStringEndsWith('Z', $decision['confirmed_at']);
        $this->assertSame((float) $order->ThanhTien, (float) $decision['agreed_total']);
        $this->assertNotEmpty($decision['confirmed_at']);
        $this->assertSame(2, (int) $booking->fresh()->NhanVienID);
        $expectedDecisionTime = Carbon::parse($decision['confirmed_at'])
            ->timezone('Asia/Ho_Chi_Minh')
            ->format('d/m/Y H:i:s');
        $this->get(route('deliveries.booking-documents.show', $intake))
            ->assertOk()
            ->assertSee('Nhân viên phụ trách')
            ->assertSee('Nhân viên chốt kiểm kê')
            ->assertSee($decision['employee_name'])
            ->assertSee($expectedDecisionTime)
            ->assertDontSee($decision['confirmed_at'])
            ->assertSee('Khách đồng ý báo giá')
            ->assertSee('Tổng tiền khách đồng ý');
        $decision['confirmed_at'] = null;
        DB::table('BookingKiemKe')->where('BookingKiemKeID', $inspection->BookingKiemKeID)
            ->update(['DuLieuThucTe' => json_encode([...$inspection->DuLieuThucTe, '_decision' => $decision])]);
        $this->get(route('deliveries.booking-documents.show', $intake))
            ->assertOk()
            ->assertSee('Chưa chốt kiểm kê');

        $this->post(route('bookings.confirm', $booking), $payload)->assertRedirect();
        $this->assertSame(1, DonHang::query()->where('BookingID', $booking->BookingID)->count());
    }

    public function test_unassigned_employee_cannot_inspect_another_employees_intake(): void
    {
        $this->createInspectionCatalog();
        DB::table('NhanVien')->insert([
            'NhanVienID' => 2,
            'HoTen' => 'Nhân viên B',
            'TrangThai' => 'Hoạt động',
        ]);
        $booking = $this->createBooking();
        $this->actingAsBookingEmployee();
        $this->bookingService->approve($booking);
        DB::table('BookingGiaoNhan')
            ->where('BookingID', $booking->BookingID)
            ->where('LoaiGiaoNhan', 'NHAN_DO')
            ->update(['NhanVienID' => 2]);
        $this->createPermissionTables();
        $this->withoutMiddleware([EnsureUserHasPermission::class, RejectCustomerRole::class]);
        $payload = $this->inspectionPayload($booking, 1);
        $payload['items'] = $this->actualItems();

        $this->post(route('bookings.confirm', $booking), $payload)
            ->assertRedirect(route('bookings.inspection', $booking))
            ->assertSessionHasErrors('booking');

        try {
            $this->bookingService->submitInspection($booking, $payload);
            $this->fail('A different employee must not inspect the assigned intake.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('booking', $exception->errors());
        }

        $this->assertDatabaseCount('BookingKiemKe', 0);
        $this->assertDatabaseCount('DonHang', 0);
    }

    public function test_assigned_employee_without_booking_confirmation_permission_can_inspect_and_convert_booking(): void
    {
        $this->createInspectionCatalog();
        DB::table('NhanVien')->insert([
            'NhanVienID' => 2,
            'HoTen' => 'Nhân viên B được phân công',
            'TrangThai' => 'Hoạt động',
        ]);
        $booking = $this->createBooking();
        $this->actingAsBookingEmployee();
        $this->bookingService->approve($booking);
        DB::table('BookingGiaoNhan')
            ->where('BookingID', $booking->BookingID)
            ->where('LoaiGiaoNhan', 'NHAN_DO')
            ->update(['NhanVienID' => 2]);

        $employee = Mockery::mock(User::class)->makePartial();
        $employee->forceFill([
            'TaiKhoanID' => 2,
            'NhanVienID' => 2,
            'TrangThai' => 'Hoạt động',
        ]);
        $employee->shouldReceive('isActive')->andReturn(true);
        $employee->shouldReceive('isManager')->andReturn(false);
        $employee->shouldReceive('isOwner')->andReturn(false);
        $employee->shouldReceive('canPermission')->andReturnUsing(
            fn (string $permission): bool => in_array($permission, ['bookings.view', 'deliveries.view'], true),
        );
        $this->actingAs($employee);
        $this->withoutMiddleware([RejectCustomerRole::class, RestoreRememberedLogin::class]);
        $this->bookingService->markReceived($booking);

        $intake = BookingGiaoNhan::query()
            ->where('BookingID', $booking->BookingID)
            ->where('LoaiGiaoNhan', 'NHAN_DO')
            ->firstOrFail();
        $this->assertFalse($employee->canPermission('bookings.confirm'));

        $this->get(route('deliveries.booking-documents.show', $intake))
            ->assertOk()
            ->assertSee('Kiểm tra và tạo đơn');
        $this->get(route('bookings.show', $booking))
            ->assertOk()
            ->assertSee('Kiểm tra và tạo đơn');
        $this->get(route('bookings.inspection', $booking))->assertOk();

        $payload = $this->inspectionPayload($booking, 2);
        $payload['items'] = $this->actualItems();
        $this->post(route('bookings.confirm', $booking), $payload)->assertRedirect();

        $this->assertDatabaseCount('DonHang', 1);
        $this->assertSame('Hoàn thành', $intake->fresh()->TrangThai);
        $this->assertSame(2, (int) $booking->fresh()->kiemKe->DuLieuThucTe['_decision']['employee_id']);
        $this->get(route('deliveries.booking-documents.show', $intake))
            ->assertOk()
            ->assertSee('Nhân viên phụ trách')
            ->assertSee('Nhân viên chốt kiểm kê')
            ->assertSee('Nhân viên B được phân công');
    }

    public function test_customer_quote_refusal_cancels_without_order_or_pickup_charge_and_keeps_snapshot(): void
    {
        $this->createInspectionCatalog();
        $this->createPromotionTable();
        $booking = $this->createBooking();
        DB::table('DiemTichLuy')->insert([
            'KhachHangID' => $booking->KhachHangID,
            'DiemHienTai' => 400,
        ]);
        DB::table('KhuyenMai')->insert([
            'KhuyenMaiID' => 1,
            'MaKhuyenMai' => 'HELD',
            'TenKhuyenMai' => 'Ưu đãi đã giữ',
            'LoaiKhuyenMai' => 'Tiền mặt',
            'GiaTriGiam' => 1000,
            'NgayBatDau' => today()->subDay()->toDateString(),
            'NgayKetThuc' => today()->addDay()->toDateString(),
            'SoLuongSuDung' => 0,
            'TrangThai' => 'Hoạt động',
        ]);
        $booking->forceFill([
            'DiemSuDung' => 100,
            'TienGiamDoDiem' => 100,
            'DiemDaTru' => true,
            'KhuyenMaiID' => 1,
            'KhuyenMaiDaTru' => true,
        ])->saveQuietly();
        $this->actingAsBookingEmployee();
        $this->bookingService->approve($booking);
        $this->startAssignedIntake($booking);
        $this->createPermissionTables();
        $this->withoutMiddleware([
            Authenticate::class,
            EnsureUserHasPermission::class,
            RejectCustomerRole::class,
            RestoreRememberedLogin::class,
        ]);
        $intake = BookingGiaoNhan::query()
            ->where('BookingID', $booking->BookingID)
            ->where('LoaiGiaoNhan', 'NHAN_DO')
            ->firstOrFail();
        $payload = $this->inspectionPayload($booking, 1);
        $payload['items'] = $this->actualItems();
        unset($payload['customer_accepted']);
        $payload['rejection_reason'] = 'Khách không đồng ý với tổng tiền thực tế.';

        $this->post(route('bookings.refuse-quote', $booking), $payload)
            ->assertRedirect(route('bookings.show', $booking))
            ->assertSessionHas('success');

        $inspection = $booking->fresh()->kiemKe;
        $decision = $inspection->DuLieuThucTe['_decision'];
        $this->assertSame(BookingStatus::Cancelled->value, $booking->fresh()->TrangThai);
        $this->assertSame('Đã hủy', $intake->fresh()->TrangThai);
        $this->assertNull($intake->fresh()->ThoiGianThucTe);
        $this->assertSame(10000.0, (float) $intake->fresh()->PhiGiaoNhan);
        $this->assertFalse($decision['customer_accepted']);
        $this->assertSame(0, $decision['charged_total']);
        $this->assertSame('Khách không đồng ý với tổng tiền thực tế.', $decision['reason']);
        $this->assertSame(1, $decision['employee_id']);
        $this->assertSame('2', (string) $inspection->DuLieuThucTe['items'][0]['SoLuong']);
        $this->assertSame(500, $booking->khachHang->fresh()->points());
        $this->assertFalse($booking->fresh()->DiemDaTru);
        $this->assertSame(0, $booking->fresh()->DiemSuDung);
        $this->assertFalse($booking->fresh()->KhuyenMaiDaTru);
        $this->assertSame(1, (int) DB::table('KhuyenMai')->value('SoLuongSuDung'));
        $this->assertDatabaseCount('DonHang', 0);
        $this->assertDatabaseCount('ThanhToan', 0);
        $this->assertDatabaseHas('NhatKyHeThong', [
            'BangDuLieu' => 'Booking',
            'BanGhiID' => $booking->BookingID,
            'LyDo' => 'Khách không đồng ý với tổng tiền thực tế.',
        ]);
        $this->get(route('deliveries.booking-documents.show', $intake))
            ->assertOk()
            ->assertSee('Nhân viên chốt kiểm kê')
            ->assertSee($decision['employee_name'])
            ->assertSee('Khách đồng ý báo giá')
            ->assertSee('Lý do khách từ chối')
            ->assertSee('Số tiền đã thu');

        $this->post(route('bookings.refuse-quote', $booking), $payload)
            ->assertRedirect(route('bookings.inspection', $booking))
            ->assertSessionHasErrors('booking');
        $this->assertSame(1, DB::table('BookingKiemKe')->where('BookingID', $booking->BookingID)->count());
        $this->assertSame(500, $booking->khachHang->fresh()->points());
        $this->assertSame(1, (int) DB::table('KhuyenMai')->value('SoLuongSuDung'));
        $this->assertDatabaseCount('DonHang', 0);
    }

    public function test_two_step_inspection_creates_order_and_notifies_customer_without_mobile_approval(): void
    {
        $this->createInspectionCatalog();
        $booking = $this->createBooking();
        $booking->chiTietBookings()->create([
            'DichVuID' => 1, 'LoaiDoGiatID' => 2, 'DonViTinhID' => 3,
            'SoLuong' => 2, 'DonGia' => 15000, 'ThanhTien' => 30000,
        ]);
        DB::table('TaiKhoan')->insert(['TaiKhoanID' => 90, 'KhachHangID' => 9, 'TenDangNhap' => 'mobile-customer']);
        $this->actingAsBookingEmployee();
        $this->bookingService->approve($booking);
        $this->startAssignedIntake($booking);
        $payload = $this->inspectionPayload($booking, 1);
        $payload['items'][0]['SoLuong'] = 3;

        $order = $this->bookingService->submitInspection($booking, $payload);
        $this->assertSame($booking->BookingID, $order->BookingID);
        $this->assertSame(1, DonHang::where('BookingID', $booking->BookingID)->count());
        $this->assertDatabaseHas('BookingKiemKe', [
            'BookingID' => $booking->BookingID,
            'CoChenhLech' => true,
        ]);
        $this->assertDatabaseHas('ThongBao', [
            'TaiKhoanID' => 90,
            'BookingID' => $booking->BookingID,
            'DonHangID' => $order->DonHangID,
            'LoaiThongBao' => 'booking_inspection_updated',
        ]);
        $this->assertSame(
            $order->DonHangID,
            $this->bookingService->submitInspection($booking, $payload)->DonHangID,
        );
        $this->assertSame(1, DB::table('ThongBao')->where('BookingID', $booking->BookingID)->count());
    }

    public function test_staff_saves_customer_visible_draft_before_verbal_consent_creates_one_order(): void
    {
        $this->createInspectionCatalog();
        $booking = $this->createBooking();
        $booking->chiTietBookings()->create([
            'DichVuID' => 1, 'LoaiDoGiatID' => 2, 'DonViTinhID' => 3,
            'SoLuong' => 2, 'DonGia' => 15000, 'ThanhTien' => 30000,
        ]);
        $this->actingAsBookingEmployee();
        $this->prepareBookingForInspection($booking);
        $draft = $this->inspectionPayload($booking, 1);
        $draft['items'][0]['SoLuong'] = 3;

        $this->bookingService->saveInspectionDraft($booking, $draft);
        $this->assertDatabaseHas('StaffBookingDraft', ['BookingID' => $booking->BookingID, 'CoChenhLech' => true]);
        $this->assertSame(3.0, (float) $booking->chiTietBookings()->value('SoLuong'));
        $this->assertDatabaseCount('DonHang', 0);

        $order = $this->bookingService->submitInspection($booking, [...$draft, 'customer_accepted' => true]);

        $this->assertSame(3.0, (float) $order->chiTietDonHangs()->value('SoLuong'));
        $this->assertDatabaseCount('DonHang', 1);
        $this->assertDatabaseHas('BookingKiemKe', ['BookingID' => $booking->BookingID, 'CoChenhLech' => true]);
        $this->assertDatabaseMissing('StaffBookingDraft', ['BookingID' => $booking->BookingID]);
    }

    public function test_two_step_item_note_adjustments_notify_customer(): void
    {
        $this->createInspectionCatalog();
        $booking = $this->createBooking(['GhiChu' => 'Ghi chú đã đăng ký']);
        $booking->chiTietBookings()->create([
            'DichVuID' => 1, 'LoaiDoGiatID' => 2, 'DonViTinhID' => 3,
            'SoLuong' => 2, 'DonGia' => 15000, 'ThanhTien' => 30000,
            'GhiChu' => 'Ghi chú dòng đã đăng ký',
        ]);
        DB::table('TaiKhoan')->insert(['TaiKhoanID' => 90, 'KhachHangID' => 9, 'TenDangNhap' => 'mobile-customer']);
        $this->actingAsBookingEmployee();
        $this->bookingService->approve($booking);
        $this->startAssignedIntake($booking);
        $payload = $this->inspectionPayload($booking, 1);
        $payload['notes'] = $booking->GhiChu;
        $payload['items'][0]['GhiChu'] = 'Ghi chú dòng đã đăng ký';
        $payload['items'][0]['GhiChu'] = 'Ghi chú dòng thực tế';

        $order = $this->bookingService->submitInspection($booking, $payload);

        $this->assertSame($booking->BookingID, $order->BookingID);
        $this->assertDatabaseHas('ThongBao', [
            'TaiKhoanID' => 90,
            'BookingID' => $booking->BookingID,
            'DonHangID' => $order->DonHangID,
            'LoaiThongBao' => 'booking_inspection_updated',
        ]);
    }

    public function test_two_step_omitted_booking_note_does_not_create_a_false_difference(): void
    {
        $this->createInspectionCatalog();
        $booking = $this->createBooking(['GhiChu' => 'Ghi chú đã đăng ký']);
        $booking->chiTietBookings()->create([
            'DichVuID' => 1, 'LoaiDoGiatID' => 2, 'DonViTinhID' => 3,
            'SoLuong' => 2, 'DonGia' => 15000, 'ThanhTien' => 30000,
            'GhiChu' => 'Ghi chú dòng phải được giữ lại',
        ]);
        $this->actingAsBookingEmployee();
        $this->bookingService->approve($booking);
        $this->startAssignedIntake($booking);
        $payload = $this->inspectionPayload($booking, 1);

        $order = $this->bookingService->submitInspection($booking, $payload);

        $this->assertSame('Ghi chú đã đăng ký', $booking->fresh()->GhiChu);
        $this->assertSame($booking->BookingID, $order->BookingID);
        $this->assertSame(
            'Ghi chú dòng phải được giữ lại',
            DB::table('ChiTietDonHang')->where('DonHangID', $order->DonHangID)->value('GhiChu'),
        );
        $this->assertDatabaseCount('ThongBao', 0);
    }

    public function test_inspection_detects_current_price_difference_and_uses_server_price(): void
    {
        $this->createInspectionCatalog();
        $booking = $this->createBooking();
        $booking->chiTietBookings()->create([
            'DichVuID' => 1, 'LoaiDoGiatID' => 2, 'DonViTinhID' => 3,
            'SoLuong' => 2, 'DonGia' => 15000, 'ThanhTien' => 30000,
        ]);
        DB::table('TaiKhoan')->insert([
            'TaiKhoanID' => 90,
            'KhachHangID' => 9,
            'TenDangNhap' => 'mobile-customer',
        ]);
        DB::table('BangGia')->where('DichVuID', 1)->where('LoaiDoGiatID', 2)
            ->where('DonViTinhID', 3)->update(['DonGia' => 18000]);
        $this->actingAsBookingEmployee();
        $this->bookingService->approve($booking);
        $this->startAssignedIntake($booking);

        $order = $this->bookingService->submitInspection(
            $booking,
            $this->inspectionPayload($booking, 1),
        );

        $this->assertTrue($booking->fresh()->kiemKe->CoChenhLech);
        $this->assertSame(
            18000.0,
            (float) DB::table('ChiTietDonHang')->where('DonHangID', $order->DonHangID)->value('DonGia'),
        );
    }

    public function test_two_step_confirmation_route_creates_order_and_customer_notification(): void
    {
        $this->createInspectionCatalog();
        $booking = $this->createBooking();
        $booking->chiTietBookings()->create([
            'DichVuID' => 1, 'LoaiDoGiatID' => 2, 'DonViTinhID' => 3,
            'SoLuong' => 2, 'DonGia' => 15000, 'ThanhTien' => 30000,
        ]);
        DB::table('TaiKhoan')->insert(['TaiKhoanID' => 90, 'KhachHangID' => 9, 'TenDangNhap' => 'mobile-customer']);
        $this->actingAsBookingEmployee();
        $this->withoutMiddleware([EnsureUserHasPermission::class, RejectCustomerRole::class]);
        $this->bookingService->approve($booking);
        $this->startAssignedIntake($booking);
        $payload = $this->inspectionPayload($booking, 1);
        $payload['items'][0]['SoLuong'] = 3;

        $response = $this->post(route('bookings.confirm', $booking), $payload);
        $order = DonHang::query()->where('BookingID', $booking->BookingID)->firstOrFail();

        $response->assertRedirect(route('orders.show', $order));
        $response->assertSessionHas('success', fn (string $message): bool => str_contains($message, $order->MaDonHang));
        $this->assertSame(1, DonHang::query()->where('BookingID', $booking->BookingID)->count());
        $this->assertDatabaseHas('ThongBao', [
            'TaiKhoanID' => 90,
            'BookingID' => $booking->BookingID,
            'DonHangID' => $order->DonHangID,
            'LoaiThongBao' => 'booking_inspection_updated',
        ]);
    }

    public function test_repeated_confirmation_http_request_reuses_existing_order_and_inspection(): void
    {
        $this->createInspectionCatalog();
        $booking = $this->createBooking();
        DB::table('TaiKhoan')->insert(['TaiKhoanID' => 90, 'KhachHangID' => 9, 'TenDangNhap' => 'mobile-customer']);
        $this->actingAsBookingEmployee();
        $this->withoutMiddleware([EnsureUserHasPermission::class, RejectCustomerRole::class]);
        $this->prepareBookingForInspection($booking);
        $payload = $this->inspectionPayload($booking, 1);
        $payload['items'] = $this->actualItems();

        $firstResponse = $this->post(route('bookings.confirm', $booking->BookingID), $payload);
        $order = DonHang::query()->where('BookingID', $booking->BookingID)->sole();
        $firstResponse->assertRedirect(route('orders.show', $order));
        $inspectionVersion = DB::table('BookingKiemKe')
            ->where('BookingID', $booking->BookingID)
            ->value('PhienBan');
        $deliveryCount = GiaoNhan::query()->where('DonHangID', $order->DonHangID)->count();

        $this->post(route('bookings.confirm', $booking->BookingID), $payload)
            ->assertRedirect(route('orders.show', $order));

        $this->assertSame(1, DonHang::query()->where('BookingID', $booking->BookingID)->count());
        $this->assertSame(1, DB::table('BookingKiemKe')->where('BookingID', $booking->BookingID)->count());
        $this->assertSame($inspectionVersion, DB::table('BookingKiemKe')
            ->where('BookingID', $booking->BookingID)
            ->value('PhienBan'));
        $this->assertSame($deliveryCount, GiaoNhan::query()->where('DonHangID', $order->DonHangID)->count());
        $this->assertSame(2, DB::table('BookingGiaoNhan')->where('BookingID', $booking->BookingID)->count());
    }

    public function test_two_step_notification_failure_rolls_back_inspection_and_order(): void
    {
        $this->createInspectionCatalog();
        $booking = $this->createBooking();
        $booking->chiTietBookings()->create([
            'DichVuID' => 1, 'LoaiDoGiatID' => 2, 'DonViTinhID' => 3,
            'SoLuong' => 2, 'DonGia' => 15000, 'ThanhTien' => 30000,
        ]);
        DB::table('TaiKhoan')->insert(['TaiKhoanID' => 90, 'KhachHangID' => 9, 'TenDangNhap' => 'mobile-customer']);
        DB::statement("CREATE TRIGGER reject_inspection_notification BEFORE INSERT ON ThongBao BEGIN SELECT RAISE(ABORT, 'simulated notification failure'); END");
        $this->actingAsBookingEmployee();
        $this->bookingService->approve($booking);
        $this->startAssignedIntake($booking);
        $payload = $this->inspectionPayload($booking, 1);
        $payload['items'][0]['SoLuong'] = 3;

        try {
            $this->bookingService->submitInspection($booking, $payload);
            $this->fail('A failed customer notification must roll back inspection and order creation.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('simulated notification failure', $exception->getMessage());
        }

        $this->assertDatabaseCount('BookingKiemKe', 0);
        $this->assertDatabaseCount('DonHang', 0);
        $this->assertDatabaseCount('ThongBao', 0);
    }

    public function test_two_step_changed_inspection_requires_a_mobile_account_to_notify(): void
    {
        $this->createInspectionCatalog();
        $booking = $this->createBooking();
        $booking->chiTietBookings()->create([
            'DichVuID' => 1, 'LoaiDoGiatID' => 2, 'DonViTinhID' => 3,
            'SoLuong' => 2, 'DonGia' => 15000, 'ThanhTien' => 30000,
        ]);
        $this->actingAsBookingEmployee();
        $this->bookingService->approve($booking);
        $this->startAssignedIntake($booking);
        $payload = $this->inspectionPayload($booking, 1);
        $payload['items'][0]['SoLuong'] = 3;

        $this->rejectBookingAction(fn () => $this->bookingService->submitInspection($booking, $payload), 'booking');

        $this->assertDatabaseCount('BookingKiemKe', 0);
        $this->assertDatabaseCount('DonHang', 0);
        $this->assertDatabaseCount('ThongBao', 0);
    }

    public function test_received_two_step_booking_shows_one_step_inspection_and_order_action(): void
    {
        $booking = $this->createBooking();
        $this->actingAsBookingEmployee();
        $this->bookingService->approve($booking);
        $this->startAssignedIntake($booking);
        Schema::create('Quyen', function (Blueprint $table): void {
            $table->increments('QuyenID');
            $table->string('MaQuyen');
            $table->string('TrangThai')->default('Hoạt động');
        });
        Schema::create('VaiTro_Quyen', function (Blueprint $table): void {
            $table->unsignedInteger('VaiTroID');
            $table->unsignedInteger('QuyenID');
        });
        Gate::before(fn (): bool => true);
        $this->withoutMiddleware([Authenticate::class, EnsureUserHasPermission::class, RejectCustomerRole::class, RestoreRememberedLogin::class]);

        $this->get(route('bookings.show', $booking))
            ->assertOk()
            ->assertSee('Kiểm tra và tạo đơn')
            ->assertDontSee('Xóa đặt lịch')
            ->assertDontSee('finalize-inspection')
            ->assertDontSee('Đang chờ khách xác nhận');
    }

    public function test_pending_booking_detail_shows_confirmed_post_cancel_action_without_physical_delete(): void
    {
        $booking = $this->createBooking();
        $this->actingAsBookingEmployee();
        Schema::create('Quyen', function (Blueprint $table): void {
            $table->increments('QuyenID');
            $table->string('MaQuyen');
            $table->string('TrangThai')->default('Hoạt động');
        });
        Schema::create('VaiTro_Quyen', function (Blueprint $table): void {
            $table->unsignedInteger('VaiTroID');
            $table->unsignedInteger('QuyenID');
        });
        Gate::before(fn (): bool => true);
        $this->withoutMiddleware([
            Authenticate::class,
            EnsureUserHasPermission::class,
            RejectCustomerRole::class,
            RestoreRememberedLogin::class,
        ]);

        $response = $this->get(route('bookings.show', $booking));

        $response->assertOk()
            ->assertSee('Hủy đặt lịch')
            ->assertDontSee('Hủy Booking')
            ->assertDontSee('Xóa đặt lịch')
            ->assertSee('Xác nhận Booking')
            ->assertSee('btn-success w-100 py-2', false)
            ->assertSee('btn btn-outline-danger py-2 w-100', false)
            ->assertSee(route('bookings.cancel', $booking), false)
            ->assertSee('data-confirm-form', false)
            ->assertSee('method="POST"', false)
            ->assertDontSee('name="_method" value="DELETE"', false);

        $this->post(route('bookings.cancel', $booking))
            ->assertRedirect(route('bookings.show', $booking))
            ->assertSessionHas('success', 'Đã hủy Booking.');

        $this->assertDatabaseHas('Booking', [
            'BookingID' => $booking->BookingID,
            'TrangThai' => BookingStatus::Cancelled->value,
        ]);
        $this->assertDatabaseCount('DonHang', 0);
        $this->assertDatabaseCount('BookingKiemKe', 0);
        $this->assertSame(1, NhatKyHeThong::query()
            ->where('BangDuLieu', 'Booking')
            ->where('BanGhiID', $booking->BookingID)
            ->count());
    }

    public function test_booking_delete_endpoint_is_unavailable_and_keeps_booking_documents_and_inspection(): void
    {
        $booking = $this->createBooking();
        $this->actingAsBookingEmployee();
        $this->bookingService->approve($booking);
        $inspectionId = DB::table('BookingKiemKe')->insertGetId([
            'BookingID' => $booking->BookingID,
            'DuLieuThucTe' => json_encode(['items' => []]),
            'CoChenhLech' => false,
            'PhienBan' => 1,
            'CapNhatLuc' => now(),
        ], 'BookingKiemKeID');
        Gate::before(fn (): bool => true);

        $this->delete('/bookings/'.$booking->BookingID)->assertMethodNotAllowed();

        $this->assertDatabaseHas('Booking', ['BookingID' => $booking->BookingID]);
        $this->assertDatabaseCount('BookingGiaoNhan', 2);
        $this->assertDatabaseHas('BookingKiemKe', [
            'BookingKiemKeID' => $inspectionId,
            'BookingID' => $booking->BookingID,
        ]);
        $this->assertDatabaseCount('DonHang', 0);
    }

    public function test_confirmed_unreceived_booking_shows_store_intake_action_without_order_actions(): void
    {
        $booking = $this->createBooking(['HinhThucNhanDo' => 'Tại cửa hàng']);
        $this->actingAsBookingEmployee();
        $this->bookingService->approve($booking);
        Schema::create('Quyen', function (Blueprint $table): void {
            $table->increments('QuyenID');
            $table->string('MaQuyen');
            $table->string('TrangThai')->default('Hoạt động');
        });
        Schema::create('VaiTro_Quyen', function (Blueprint $table): void {
            $table->unsignedInteger('VaiTroID');
            $table->unsignedInteger('QuyenID');
        });
        Gate::before(fn (): bool => true);
        $this->withoutMiddleware([Authenticate::class, EnsureUserHasPermission::class, RejectCustomerRole::class, RestoreRememberedLogin::class]);

        $this->get(route('bookings.show', $booking))
            ->assertOk()
            ->assertSee('Khách mang đồ đến cửa hàng để nhân viên tiếp nhận.')
            ->assertSee('Chưa phân công nhân viên nhận và kiểm kê.')
            ->assertDontSee('Xác nhận đã nhận đồ')
            ->assertSee('Kế hoạch trả đồ')
            ->assertSee('đây chưa phải phiếu giao vận hành')
            ->assertDontSee('Kiểm tra và tạo đơn')
            ->assertDontSee('Đơn hàng liên kết')
            ->assertDontSee('Hủy đặt lịch')
            ->assertDontSee('Xóa đặt lịch');

        $this->assertDatabaseCount('DonHang', 0);
        $this->assertDatabaseCount('GiaoNhan', 0);
    }

    public function test_two_step_booking_without_declared_items_uses_staff_inspection_directly(): void
    {
        $this->createInspectionCatalog();
        $booking = $this->createBooking();
        $this->actingAsBookingEmployee();
        $this->bookingService->approve($booking);
        $this->startAssignedIntake($booking);
        $payload = $this->inspectionPayload($booking, 1);
        $payload['items'] = $this->actualItems();

        $order = $this->bookingService->submitInspection($booking, $payload);

        $this->assertNotNull($order);
        $this->assertSame($booking->BookingID, $order->BookingID);
        $this->assertDatabaseHas('BookingKiemKe', ['BookingID' => $booking->BookingID, 'CoChenhLech' => false]);
        $this->assertDatabaseCount('DonHang', 1);
        $this->assertDatabaseCount('ThongBao', 0);
    }

    public function test_two_step_return_note_tracks_the_received_booking_and_created_order(): void
    {
        $this->createInspectionCatalog();
        $booking = $this->createBooking();
        $booking->chiTietBookings()->create([
            'DichVuID' => 1, 'LoaiDoGiatID' => 2, 'DonViTinhID' => 3,
            'SoLuong' => 2, 'DonGia' => 15000, 'ThanhTien' => 30000,
        ]);
        DB::table('TaiKhoan')->insert(['TaiKhoanID' => 90, 'KhachHangID' => 9, 'TenDangNhap' => 'mobile-customer']);
        $this->actingAsBookingEmployee();
        $this->bookingService->approve($booking);
        $this->startAssignedIntake($booking);
        $payload = $this->inspectionPayload($booking, 1);
        $payload['items'][0]['SoLuong'] = 3;
        $payload['return_method'] = 'Tại nhà';
        $payload['return_address'] = '88 Lê Lợi';
        $payload['recalculate_delivery_fee'] = true;

        $order = $this->bookingService->submitInspection($booking, $payload);
        $inspection = DB::table('BookingKiemKe')->where('BookingID', $booking->BookingID)->first();
        $legs = DB::table('BookingGiaoNhan')->where('BookingID', $booking->BookingID)->get()->keyBy('LoaiGiaoNhan');
        $this->assertSame($order->DonHangID, $legs['NHAN_DO']->DonHangID);
        $this->assertSame($order->DonHangID, $legs['GIAO_DO']->DonHangID);
        $this->assertSame($inspection->BookingKiemKeID, $legs['GIAO_DO']->BookingKiemKeID);
        $this->assertSame(3.0, (float) DB::table('ChiTietDonHang')->where('DonHangID', $order->DonHangID)->value('SoLuong'));
        $this->assertSame('Tại cửa hàng', $legs['GIAO_DO']->HinhThuc);
        $this->assertNull($legs['GIAO_DO']->DiaChi);
        $this->assertSame(0, $order->giaoNhans()->where('LoaiGiaoNhan', 'GIAO_DO')->count());
        $this->assertSame((float) $booking->fresh()->DeliveryFee, (float) $legs['GIAO_DO']->PhiGiaoNhan);
        $this->assertSame('Chờ thực hiện', $legs['GIAO_DO']->TrangThai);

    }

    public function test_two_step_conversion_rejects_a_missing_return_note(): void
    {
        $this->createInspectionCatalog();
        $booking = $this->createBooking();
        $this->actingAsBookingEmployee();
        $this->bookingService->approve($booking);
        $this->startAssignedIntake($booking);
        DB::table('BookingGiaoNhan')->where('BookingID', $booking->BookingID)->where('LoaiGiaoNhan', 'GIAO_DO')->delete();
        $payload = $this->inspectionPayload($booking, 1);
        $payload['items'] = $this->actualItems();

        $this->rejectBookingAction(
            fn () => $this->bookingService->submitInspection($booking, $payload),
            'booking',
        );
        $this->assertDatabaseCount('DonHang', 0);
    }

    private function rejectBookingAction(callable $action, string $field): void
    {
        try {
            $action();
            $this->fail('Expected the Booking transition to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
    }

    private function fakeDeliveryApis(): void
    {
        Cache::store('array')->flush();

        $coordinates = [
            'Laundry test store' => ['lat' => '10', 'lon' => '106'],
            '12 Nguyễn Huệ' => ['lat' => '11', 'lon' => '107'],
            '88 Lê Lợi' => ['lat' => '12', 'lon' => '108'],
            'Địa chỉ nhận mới' => ['lat' => '13', 'lon' => '109'],
            'Địa chỉ trả mới' => ['lat' => '14', 'lon' => '110'],
            'Địa chỉ kiểm tra mới' => ['lat' => '15', 'lon' => '111'],
        ];
        $distances = [
            '107,11' => 5000,
            '108,12' => 6000,
            '109,13' => 10000,
            '110,14' => 4000,
            '111,15' => 8000,
        ];

        Http::fake(function (ClientRequest $request) use ($coordinates, $distances) {
            if (str_starts_with($request->url(), 'https://nominatim.openstreetmap.org/search')) {
                parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
                $coordinate = $coordinates[$query['q'] ?? ''] ?? ['lat' => '16', 'lon' => '112'];

                return Http::response([$coordinate], 200);
            }

            if (str_starts_with($request->url(), 'https://router.project-osrm.org/route/v1/driving/')) {
                if ($this->deliveryRouteFailure) {
                    return Http::response(['message' => 'Route service unavailable'], 503);
                }

                $distance = 5000;
                foreach ($distances as $destination => $meters) {
                    if (str_contains($request->url(), ';'.$destination)) {
                        $distance = $meters;
                        break;
                    }
                }

                return Http::response([
                    'code' => 'Ok',
                    'routes' => [['distance' => $distance]],
                ], 200);
            }

            throw new \RuntimeException('Unexpected outbound HTTP request in BookingOrderConversionTest.');
        });
    }

    private function createPromotionTable(): void
    {
        Schema::create('KhuyenMai', function (Blueprint $table): void {
            $table->increments('KhuyenMaiID');
            $table->string('MaKhuyenMai')->unique();
            $table->string('TenKhuyenMai')->nullable();
            $table->string('LoaiKhuyenMai');
            $table->decimal('GiaTriGiam', 12, 2);
            $table->decimal('GiaTriDonToiThieu', 12, 2)->nullable();
            $table->decimal('MucGiamToiDa', 12, 2)->nullable();
            $table->integer('SoLuongSuDung')->nullable();
            $table->text('DieuKienApDung')->nullable();
            $table->date('NgayBatDau');
            $table->date('NgayKetThuc');
            $table->string('TrangThai');
        });
    }

    public function test_booking_detail_shows_saved_estimate_without_spending_points(): void
    {
        $this->createInspectionCatalog();
        $booking = $this->createBooking();
        $booking->chiTietBookings()->create(['DichVuID' => 1, 'LoaiDoGiatID' => 2, 'DonViTinhID' => 3, 'SoLuong' => 1, 'DonGia' => 15000, 'ThanhTien' => 15000]);
        $this->withoutMiddleware([Authenticate::class, EnsureUserHasPermission::class, RejectCustomerRole::class, RestoreRememberedLogin::class]);
        $this->get(route('bookings.show', $booking))->assertOk()
            ->assertSee('Ước tính từ lịch đặt')
            ->assertSee('data-booking-amount="TienGiamKhuyenMai"', false)
            ->assertSee('data-booking-amount="ThanhTien"', false)
            ->assertViewHas('bookingAmounts', fn (array $amounts): bool => $amounts['TongTien'] === 15000.0 && $amounts['ThanhTien'] === 25000.0 && $amounts['DiemSuDung'] === 0);
        $this->assertDatabaseCount('DonHang', 0);
    }

    public function test_booking_detail_without_items_does_not_invent_a_zero_payment(): void
    {
        $booking = $this->createBooking();
        $this->withoutMiddleware([Authenticate::class, EnsureUserHasPermission::class, RejectCustomerRole::class, RestoreRememberedLogin::class]);
        $this->get(route('bookings.show', $booking))->assertOk()
            ->assertSee('Chưa có dịch vụ dự kiến để tính tổng tiền')
            ->assertDontSee('data-booking-money-summary', false)
            ->assertViewHas('bookingAmounts', fn ($amounts): bool => $amounts === null);
    }

    public function test_booking_estimate_uses_saved_points_and_returns_each_priced_service_line(): void
    {
        $this->createInspectionCatalog();
        $this->createPromotionTable();
        DB::table('KhuyenMai')->insert([
            'KhuyenMaiID' => 3,
            'MaKhuyenMai' => 'BOOKING10',
            'LoaiKhuyenMai' => 'Phần trăm',
            'GiaTriGiam' => 10,
            'NgayBatDau' => today()->subDay()->toDateString(),
            'NgayKetThuc' => today()->addDay()->toDateString(),
            'TrangThai' => 'Hoạt động',
        ]);
        DB::table('DonViTinh')->insert([
            'DonViTinhID' => 4,
            'TenDonViTinh' => 'Kilogram',
            'KyHieu' => 'KG',
            'TrangThai' => 'Hoạt động',
        ]);
        DB::table('BangGia')->insert([
            'DichVuID' => 1,
            'LoaiDoGiatID' => 2,
            'DonViTinhID' => 4,
            'DonGia' => 10000,
            'NgayApDung' => '2026-01-01',
            'TrangThai' => 'Hoạt động',
        ]);
        DB::table('DiemTichLuy')->insert([
            'KhachHangID' => 9,
            'DiemHienTai' => 500,
        ]);
        $booking = $this->createBooking([
            'DiemSuDung' => 25,
            'TienGiamDoDiem' => 25,
        ]);
        $booking->forceFill(['KhuyenMaiID' => 3])->saveQuietly();
        $this->withoutMiddleware([Authenticate::class, EnsureUserHasPermission::class, RejectCustomerRole::class, RestoreRememberedLogin::class]);

        $this->postJson(route('bookings.estimate', $booking), [
            'method' => 'Tại nhà',
            'return_method' => 'Tại cửa hàng',
            'items' => [
                ['DichVuID' => 1, 'LoaiDoGiatID' => 2, 'DonViTinhID' => 3, 'SoLuong' => 2],
                ['DichVuID' => 1, 'LoaiDoGiatID' => 2, 'DonViTinhID' => 4, 'KhoiLuong' => 4.25],
            ],
        ])->assertOk()
            ->assertJsonPath('DiemSuDung', 25)
            ->assertJsonPath('TienGiamDoDiem', 25)
            ->assertJsonPath('items.0.service_name', 'Giặt')
            ->assertJsonPath('items.0.garment_name', 'Áo sơ mi')
            ->assertJsonPath('items.0.unit_name', 'Cái')
            ->assertJsonPath('items.0.amount', '2 Cái')
            ->assertJsonPath('items.0.quantity', 2)
            ->assertJsonPath('items.0.unit_price', 15000)
            ->assertJsonPath('items.0.subtotal', 30000)
            ->assertJsonPath('items.1.unit_name', 'KG')
            ->assertJsonPath('items.1.weight', 4.25)
            ->assertJsonPath('items.1.amount', '4.25 kg')
            ->assertJsonPath('items.1.unit_price', 10000)
            ->assertJsonPath('items.1.subtotal', 42500)
            ->assertJsonPath('requested_points', 25)
            ->assertJsonPath('available_points', 500)
            ->assertJsonPath('point_value', 1)
            ->assertJsonPath('promotion_context.eligible', true)
            ->assertJsonPath('promotion_context.type', 'Phần trăm')
            ->assertJsonPath('promotion_context.value', 10);
    }

    public function test_booking_inspection_shows_saved_rewards_as_read_only(): void
    {
        $this->createInspectionCatalog();
        $this->createPromotionTable();
        DB::table('KhuyenMai')->insert([
            'KhuyenMaiID' => 3,
            'MaKhuyenMai' => 'BOOKING10',
            'LoaiKhuyenMai' => 'Phần trăm',
            'GiaTriGiam' => 10,
            'NgayBatDau' => today()->subDay()->toDateString(),
            'NgayKetThuc' => today()->addDay()->toDateString(),
            'TrangThai' => 'Hoạt động',
        ]);
        $booking = $this->createBooking([
            'DiemSuDung' => 50,
            'TienGiamDoDiem' => 50,
        ]);
        $booking->forceFill(['KhuyenMaiID' => 3])->saveQuietly();
        $this->actingAsBookingEmployee();
        $this->prepareBookingForInspection($booking);
        $this->createPermissionTables();
        $this->withoutMiddleware([Authenticate::class, EnsureUserHasPermission::class, RejectCustomerRole::class, RestoreRememberedLogin::class]);

        $this->get(route('bookings.inspection', $booking))->assertOk()
            ->assertSee('BOOKING10')
            ->assertSee('50 điểm (50 VNĐ)')
            ->assertDontSee('Tính lại phí giao nhận')
            ->assertSee('booking-items-table', false)
            ->assertSee('Tình trạng trước khi giặt')
            ->assertSee('data-pricing-options', false)
            ->assertSee('data-estimate-items', false)
            ->assertSeeInOrder([
                'Tổng kết đơn hàng',
                'data-estimate-items',
                'data-estimate-details',
                'Tổng thanh toán',
                'data-estimate-total',
            ], false)
            ->assertDontSee('name="use_points"', false)
            ->assertDontSee('name="DiemSuDung"', false);
    }

    #[DataProvider('orderPaymentSummaryCases')]
    public function test_order_show_displays_saved_delivery_fee_in_payment_summary(int $fee, int $promotionDiscount, int $pointsDiscount, int $total): void
    {
        DB::table('KhachHang')->insert(['KhachHangID' => 9]);
        $order = DonHang::query()->create([
            'MaDonHang' => 'DH-PAYMENT-SUMMARY',
            'KhachHangID' => 9,
            'TrangThai' => OrderStatus::Received->value,
            'TongTien' => 700000,
            'PhiGiaoHang' => $fee,
            'TienGiamKhuyenMai' => $promotionDiscount,
            'TienGiamDoDiem' => $pointsDiscount,
            'DiemSuDung' => $pointsDiscount,
            'ThanhTien' => $total,
        ]);
        $user = Mockery::mock(User::class)->makePartial();
        $user->forceFill(['TaiKhoanID' => 1, 'NhanVienID' => 1, 'TrangThai' => 'Hoạt động']);
        $user->shouldReceive('isManager')->andReturn(true);
        $user->shouldReceive('isOwner')->andReturn(true);
        $user->shouldReceive('isActive')->andReturn(true);
        $user->shouldReceive('canPermission')->andReturn(true);
        $this->actingAs($user);
        Gate::before(fn () => true);
        $this->withoutMiddleware();

        $response = $this->get(route('orders.show', $order))->assertOk();
        $response->assertSeeTextInOrder([
            'Tạm tính',
            '700,000 VNĐ',
            'Phí giao nhận',
            number_format($fee).' VNĐ',
            'Tổng thanh toán',
            number_format($total).' VNĐ',
        ]);

        if ($promotionDiscount > 0) {
            $response->assertSeeText('Voucher giảm giá')->assertSeeText('-'.number_format($promotionDiscount).' VNĐ');
        } else {
            $response->assertDontSeeText('Voucher giảm giá');
        }

        if ($pointsDiscount > 0) {
            $response->assertSeeText('Giảm giá từ điểm tích lũy')->assertSeeText('-'.number_format($pointsDiscount).' VNĐ');
        } else {
            $response->assertDontSeeText('Giảm giá từ điểm tích lũy');
        }
    }

    public static function orderPaymentSummaryCases(): array
    {
        return [
            'delivery fee without discounts' => [14000, 0, 0, 714000],
            'no delivery fee' => [0, 0, 0, 700000],
            'delivery fee with discounts' => [14000, 70000, 10000, 634000],
        ];
    }

    public function test_order_edit_displays_saved_rewards_without_edit_controls(): void
    {
        $this->createInspectionCatalog();
        $this->createPromotionTable();
        DB::table('KhuyenMai')->insert([
            'KhuyenMaiID' => 3,
            'MaKhuyenMai' => 'BOOKING10',
            'LoaiKhuyenMai' => 'Phần trăm',
            'GiaTriGiam' => 10,
            'NgayBatDau' => today()->subDay()->toDateString(),
            'NgayKetThuc' => today()->addDay()->toDateString(),
            'TrangThai' => 'Hoạt động',
        ]);
        $order = DonHang::query()->create([
            'MaDonHang' => 'DH0001',
            'KhachHangID' => 9,
            'NhanVienID' => 1,
            'TrangThai' => OrderStatus::Pending->value,
            'DiemSuDung' => 50,
            'TienGiamDoDiem' => 50,
            'KhuyenMaiID' => 3,
            'TongTien' => 30000,
            'TienGiamKhuyenMai' => 3000,
            'ThanhTien' => 26950,
        ]);
        $order->chiTietDonHangs()->create([
            'DichVuID' => 1,
            'LoaiDoGiatID' => 2,
            'DonViTinhID' => 3,
            'SoLuong' => 2,
            'DonGia' => 15000,
            'ThanhTien' => 30000,
            'TinhTrangTruocKhiGiat' => 'Bình thường',
        ]);
        $this->withoutMiddleware([Authenticate::class, EnsureUserHasPermission::class, RejectCustomerRole::class, RestoreRememberedLogin::class]);

        $this->get(route('orders.edit', $order))->assertOk()
            ->assertSee('BOOKING10')
            ->assertSee('50 điểm (50 VNĐ)')
            ->assertDontSee('name="use_points"', false)
            ->assertDontSee('name="DiemSuDung"', false);
    }

    public function test_booking_confirmation_preserves_saved_coupon_and_points_without_a_reward_control(): void
    {
        $this->createInspectionCatalog();
        $this->createPromotionTable();
        DB::table('KhuyenMai')->insert([
            'KhuyenMaiID' => 3,
            'MaKhuyenMai' => 'BOOKING10',
            'LoaiKhuyenMai' => 'Phần trăm',
            'GiaTriGiam' => 10,
            'NgayBatDau' => today()->subDay()->toDateString(),
            'NgayKetThuc' => today()->addDay()->toDateString(),
            'TrangThai' => 'Hoạt động',
        ]);
        DB::table('DiemTichLuy')->insert([
            'KhachHangID' => 9,
            'DiemHienTai' => 500,
        ]);
        $booking = $this->createBooking([
            'DiemSuDung' => 50,
            'TienGiamDoDiem' => 50,
        ]);
        $booking->forceFill(['KhuyenMaiID' => 3])->saveQuietly();
        $this->actingAsBookingEmployee();
        $this->withoutMiddleware([Authenticate::class, EnsureUserHasPermission::class, RejectCustomerRole::class, RestoreRememberedLogin::class]);

        $order = $this->createOrderThroughInspection($booking, $this->actualItems());
        $this->assertSame(3, $order->KhuyenMaiID);
        $this->assertSame(50, $order->DiemSuDung);
        $this->assertSame(3000.0, $order->TienGiamKhuyenMai);
        $this->assertSame(50.0, $order->TienGiamDoDiem);
        $this->assertSame(36950.0, $order->ThanhTien);
        $this->assertSame(450, DB::table('DiemTichLuy')->where('KhachHangID', 9)->value('DiemHienTai'));
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
                ->assertSee('id="status" name="TrangThai" required', false)
                ->assertSee('value="Đã tiếp nhận" selected', false)
                ->assertSee('value="Đang giặt"  disabled', false)
                ->assertSee('id="employee_name"', false)
                ->assertSee('Chưa có hồ sơ nhân viên đang hoạt động')
                ->assertDontSee('name="NhanVienID"', false)
                ->assertDontSee('<input type="hidden" name="TrangThai"', false);
        } finally {
            Schema::dropIfExists('KhuyenMai');
        }
    }

    public function test_order_creation_from_customer_detail_prefills_and_locks_customer(): void
    {
        $this->createRequestCatalog();
        DB::table('KhachHang')->where('KhachHangID', 9)->update([
            'HoTen' => 'Nguyễn An',
            'SoDienThoai' => '0900000000',
        ]);
        Schema::create('KhuyenMai', function (Blueprint $table): void {
            $table->increments('KhuyenMaiID');
            $table->string('TrangThai');
        });
        try {
            $this->withoutMiddleware([Authenticate::class, EnsureUserHasPermission::class, RejectCustomerRole::class, RestoreRememberedLogin::class]);
            $response = $this->get(route('orders.create', ['customer_id' => 9]))->assertOk();
            $response->assertSee('value="Nguyễn An"', false)
                ->assertSee('data-name="Nguyễn An"', false);
            $this->assertMatchesRegularExpression('/<input type="search"[^>]*id="customer_search"[^>]*disabled/', $response->getContent());
            $this->assertMatchesRegularExpression('/<option value="9" data-name="Nguyễn An" data-phone="0900000000" selected/', $response->getContent());
            $this->assertMatchesRegularExpression('/<form action="[^"]*customer_id=9" method="POST"/', $response->getContent());
        } finally {
            Schema::dropIfExists('KhuyenMai');
        }
    }

    public function test_manual_order_store_persists_actual_condition_before_washing(): void
    {
        $this->createRequestCatalog();
        DB::table('NhanVien')->insert([
            'NhanVienID' => 2,
            'HoTen' => 'Nhân viên giả mạo từ request',
        ]);
        DB::table('BangGia')->insert(['DichVuID' => 1, 'LoaiDoGiatID' => 2, 'DonViTinhID' => 2, 'DonGia' => 10000, 'NgayApDung' => '2026-01-01', 'TrangThai' => 'Hoạt động']);
        $this->actingAsBookingEmployee();
        $this->withoutMiddleware([Authenticate::class, EnsureUserHasPermission::class, RejectCustomerRole::class, RestoreRememberedLogin::class]);
        $this->post(route('orders.store'), [
            'KhachHangID' => 9,
            'NhanVienID' => 2,
            'staff_id' => 2,
            'TrangThai' => OrderStatus::Received->value,
            'items' => [['DichVuID' => 1, 'LoaiDoGiatID' => 2, 'DonViTinhID' => 2, 'SoLuong' => 2, 'TinhTrangTruocKhiGiat' => '  Ố nhẹ ở cổ áo  ']],
        ])->assertSessionHasNoErrors();
        $order = DonHang::query()->sole();
        $this->assertNull($order->BookingID);
        $this->assertSame(1, $order->NhanVienID);
        $this->assertSame(OrderStatus::Received->value, $order->TrangThai);
        $this->assertSame('Ố nhẹ ở cổ áo', $order->chiTietDonHangs()->sole()->TinhTrangTruocKhiGiat);
        $this->assertSame(20000.0, $order->ThanhTien);
        app(OrderService::class)->updateStatus($order, OrderStatus::Washing->value);
        $this->assertSame(OrderStatus::Washing->value, $order->fresh()->TrangThai);
    }

    public function test_manual_order_store_rejects_account_without_employee_profile_despite_client_employee_id(): void
    {
        $this->createRequestCatalog();
        $this->withoutMiddleware([
            Authenticate::class,
            EnsureUserHasPermission::class,
            RejectCustomerRole::class,
            RestoreRememberedLogin::class,
        ]);
        $user = new User;
        $user->forceFill([
            'TaiKhoanID' => 2,
            'NhanVienID' => null,
            'TrangThai' => 'Hoạt động',
        ]);
        $this->actingAs($user);

        $this->post(route('orders.store'), [
            'KhachHangID' => 9,
            'NhanVienID' => 1,
            'TrangThai' => OrderStatus::Received->value,
            'items' => [['DichVuID' => 1, 'LoaiDoGiatID' => 2, 'DonViTinhID' => 2, 'SoLuong' => 1, 'TinhTrangTruocKhiGiat' => 'Bình thường']],
        ])->assertSessionHasErrors('NhanVienID');

        $this->assertSame(0, DonHang::query()->count());
        $this->assertSame(0, ChiTietDonHang::query()->count());
        $this->assertSame(0, GiaoNhan::query()->count());
    }

    public function test_manual_order_store_rejects_inactive_authenticated_employee_without_side_effects(): void
    {
        $this->createRequestCatalog();
        DB::table('NhanVien')->where('NhanVienID', 1)->update(['TrangThai' => 'Ngừng hoạt động']);
        $this->withoutMiddleware([
            Authenticate::class,
            EnsureUserHasPermission::class,
            RejectCustomerRole::class,
            RestoreRememberedLogin::class,
        ]);
        $this->actingAsBookingEmployee();

        $this->post(route('orders.store'), [
            'KhachHangID' => 9,
            'NhanVienID' => 1,
            'TrangThai' => OrderStatus::Received->value,
            'items' => [['DichVuID' => 1, 'LoaiDoGiatID' => 2, 'DonViTinhID' => 2, 'SoLuong' => 1, 'TinhTrangTruocKhiGiat' => 'Bình thường']],
        ])->assertSessionHasErrors('NhanVienID');

        $this->assertSame(0, DonHang::query()->count());
        $this->assertSame(0, ChiTietDonHang::query()->count());
        $this->assertSame(0, GiaoNhan::query()->count());
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
        DB::table('NhanVien')->insert([
            'NhanVienID' => 2,
            'HoTen' => 'Nhân viên tiếp nhận trước đó',
            'TrangThai' => 'Hoạt động',
        ]);
        $booking = $this->createBooking(['NhanVienID' => 2]);
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
        $this->createOrderThroughInspection($booking->fresh(), $this->inspectionItems($booking));

        $order = DonHang::query()->where('BookingID', $booking->BookingID)->firstOrFail();
        $items = ChiTietDonHang::query()->where('DonHangID', $order->DonHangID)->orderBy('ChiTietDonHangID')->get();
        $item = $items->firstOrFail();
        $delivery = BookingGiaoNhan::query()->where('BookingID', $booking->BookingID)
            ->where('LoaiGiaoNhan', 'NHAN_DO')->firstOrFail();

        $this->assertCount(2, $items);
        $this->assertSame('DH'.str_pad((string) $order->DonHangID, 8, '0', STR_PAD_LEFT), $order->MaDonHang);
        $this->assertSame(OrderStatus::Received->value, $order->TrangThai);
        $this->assertSame(45000.0, (float) $order->TongTien);
        $this->assertSame(55000.0, (float) $order->ThanhTien);
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
        $this->assertSame(0, GiaoNhan::query()->where('DonHangID', $order->DonHangID)->count());
        $this->assertNull($order->NhanVienID);
        $this->assertSame(2, $booking->fresh()->NhanVienID);
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
            'garmentOptions' => collect(),
            'units' => collect(),
            'pricingUnitOptions' => collect(),
            'errors' => new ViewErrorBag,
        ])->render();

        $this->assertStringContainsString('value="Khách hàng liên kết" disabled', $content);
        $this->assertStringContainsString('value="0901234567" disabled', $content);
        $this->assertStringContainsString('value="customer@example.test" disabled', $content);
        $this->assertStringContainsString('bg-light text-muted', $content);
        $this->assertStringNotContainsString('name="HoTen"', $content);
        $this->assertStringNotContainsString('Khôi phục dữ liệu ban đầu', $content);
        $this->assertStringNotContainsString('type="reset"', $content);
        $this->assertStringContainsString('type="submit"', $content);
    }

    public function test_booking_edit_only_lists_garments_with_effective_prices_for_the_selected_service(): void
    {
        $this->createInspectionCatalog();
        DB::table('LoaiDichVu')->insert(['LoaiDichVuID' => 1, 'TenLoaiDichVu' => 'Giặt']);
        DB::table('DichVu')->where('DichVuID', 1)->update(['LoaiDichVuID' => 1]);
        DB::table('LoaiDoGiat')->insert([
            'LoaiDoGiatID' => 3,
            'TenLoaiDoGiat' => 'Áo khoác',
        ]);
        $booking = $this->createBooking();
        $booking->chiTietBookings()->create([
            'DichVuID' => 1,
            'LoaiDoGiatID' => 2,
            'DonViTinhID' => 3,
            'SoLuong' => 1,
            'DonGia' => 15000,
            'ThanhTien' => 15000,
        ]);
        $this->withoutMiddleware([
            Authenticate::class,
            EnsureUserHasPermission::class,
            RejectCustomerRole::class,
            RestoreRememberedLogin::class,
        ]);

        $response = $this->get(route('bookings.edit', $booking));

        $response->assertOk();
        preg_match('/<select class="form-select booking-garment"[^>]*>(.*?)<\\/select>/s', $response->getContent(), $matches);
        $this->assertNotEmpty($matches);
        $this->assertStringContainsString('value="2"', $matches[1]);
        $this->assertStringNotContainsString('value="3"', $matches[1]);
        $this->assertStringNotContainsString('name="staff_id"', $response->getContent());

        $this->actingAsBookingEmployee();
        $this->prepareBookingForInspection($booking);
        DB::table('NhanVien')->where('NhanVienID', 1)->update(['TrangThai' => 'Khóa']);
        $this->get(route('bookings.inspection', $booking))
            ->assertRedirect(route('bookings.show', $booking))
            ->assertSessionHas('error');
    }

    public function test_confirm_route_uses_authenticated_employee_instead_of_client_staff_selection(): void
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
        $this->prepareBookingForInspection($booking);
        $this->from(route('bookings.show', $booking->BookingID))
            ->post(route('bookings.confirm', $booking->BookingID))
            ->assertRedirect(route('bookings.inspection', $booking))
            ->assertSessionHasErrors('items');
        $this->assertSame(0, DonHang::query()->count());

        $response = $this->post(route('bookings.confirm', $booking->BookingID), $this->inspectionPayload($booking, 999));

        $order = DonHang::query()->where('BookingID', $booking->BookingID)->firstOrFail();
        $response->assertRedirect(route('orders.show', $order));
        $this->assertSame(BookingStatus::Confirmed->value, $booking->fresh()->TrangThai);
        $this->assertNull($order->NhanVienID);
        $this->assertSame(1, $booking->fresh()->NhanVienID);
        $this->assertSame(1, $booking->fresh()->NhanVienXacNhanID);
        $this->assertNotNull($booking->fresh()->ThoiGianXacNhan);
        $this->assertSame(1, DonHang::query()->where('BookingID', $booking->BookingID)->count());
        $this->assertSame(0, GiaoNhan::query()->where('DonHangID', $order->DonHangID)->count());

        $this->post(route('bookings.confirm', $booking->BookingID), $this->inspectionPayload($booking, 999))
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

    #[DataProvider('bookingCompletionUpdateRequests')]
    public function test_booking_update_http_rejects_direct_completion_without_delivery_handover(
        string $method,
        bool $createPendingReturnSlip,
    ): void {
        $booking = $this->createBooking(['TrangThai' => BookingStatus::Confirmed->value]);
        $order = $this->createConvertedOrderForBooking($booking);
        if ($createPendingReturnSlip) {
            GiaoNhan::query()->create([
                'DonHangID' => $order->DonHangID,
                'NhanVienID' => 1,
                'LoaiGiaoNhan' => 'GIAO_DO',
                'HinhThuc' => 'Tại cửa hàng',
                'TrangThai' => 'Chờ thực hiện',
            ]);
        }

        $this->actingAsBookingEmployee();
        $this->withoutMiddleware([
            EnsureUserHasPermission::class,
            RejectCustomerRole::class,
        ]);
        $bookingSnapshot = $booking->fresh()->getAttributes();
        $orderSnapshot = $order->fresh()->getAttributes();
        $auditCount = NhatKyHeThong::query()->count();
        $payload = [
            'customer_id' => $booking->KhachHangID,
            'method' => $booking->HinhThucNhanDo,
            'address' => $booking->DiaChiNhan,
            'return_method' => $booking->HinhThucTraDo,
            'scheduled_date' => $booking->NgayHen->format('Y-m-d'),
            'scheduled_time' => $booking->GioHen->format('H:i'),
            'status' => BookingStatus::Completed->value,
        ];

        $this->from(route('bookings.edit', $booking))
            ->{$method}(route('bookings.update', $booking), $payload)
            ->assertRedirect(route('bookings.edit', $booking))
            ->assertSessionHasErrors('status');

        $this->assertSame($bookingSnapshot, $booking->fresh()->getAttributes());
        $this->assertSame($orderSnapshot, $order->fresh()->getAttributes());
        $this->assertSame($auditCount, NhatKyHeThong::query()->count());
        $this->assertSame($createPendingReturnSlip ? 1 : 0, GiaoNhan::query()
            ->where('DonHangID', $order->DonHangID)
            ->where('LoaiGiaoNhan', 'GIAO_DO')
            ->count());
        $this->assertSame(0, DB::table('BookingKiemKe')->where('BookingID', $booking->BookingID)->count());
    }

    public static function bookingCompletionUpdateRequests(): array
    {
        return [
            'PUT with no return slip' => ['put', false],
            'PATCH with no return slip' => ['patch', false],
            'PUT with pending return slip' => ['put', true],
            'PATCH with pending return slip' => ['patch', true],
        ];
    }

    public function test_booking_service_rejects_direct_completion_and_preserves_existing_data(): void
    {
        $booking = $this->createBooking(['TrangThai' => BookingStatus::Confirmed->value]);
        $order = $this->createConvertedOrderForBooking($booking);
        $bookingSnapshot = $booking->fresh()->getAttributes();
        $orderSnapshot = $order->fresh()->getAttributes();
        $auditCount = NhatKyHeThong::query()->count();

        try {
            $this->bookingService->update($booking, [
                'status' => BookingStatus::Completed->value,
            ]);
            $this->fail('Booking completion must only be synchronized from successful return handover.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }

        $this->assertSame($bookingSnapshot, $booking->fresh()->getAttributes());
        $this->assertSame($orderSnapshot, $order->fresh()->getAttributes());
        $this->assertSame($auditCount, NhatKyHeThong::query()->count());
        $this->assertSame(0, GiaoNhan::query()->count());
    }

    public function test_booking_information_can_still_be_updated_after_order_conversion(): void
    {
        $booking = $this->createBooking(['TrangThai' => BookingStatus::Confirmed->value]);
        $this->createConvertedOrderForBooking($booking);
        $this->actingAsBookingEmployee();
        $this->withoutMiddleware([
            EnsureUserHasPermission::class,
            RejectCustomerRole::class,
        ]);

        $this->put(route('bookings.update', $booking), [
            'customer_id' => $booking->KhachHangID,
            'method' => $booking->HinhThucNhanDo,
            'address' => 'Địa chỉ nhận đã cập nhật',
            'return_method' => $booking->HinhThucTraDo,
            'scheduled_date' => $booking->NgayHen->format('Y-m-d'),
            'scheduled_time' => $booking->GioHen->format('H:i'),
            'notes' => 'Ghi chú đã cập nhật',
        ])->assertRedirect(route('bookings.index'));

        $this->assertSame(BookingStatus::Confirmed->value, $booking->fresh()->TrangThai);
        $this->assertSame('Địa chỉ nhận đã cập nhật', $booking->fresh()->DiaChiNhan);
        $this->assertSame('Ghi chú đã cập nhật', $booking->fresh()->GhiChu);
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

    public function test_booking_update_uses_authenticated_employee_instead_of_client_staff_selection(): void
    {
        DB::table('NhanVien')->insert([
            'NhanVienID' => 2,
            'HoTen' => 'Nhân viên được gửi từ client',
        ]);
        $booking = $this->createBooking(['NhanVienID' => 2]);
        $this->actingAsBookingEmployee();

        $updatedBooking = $this->bookingService->update($booking, [
            'staff_id' => 999,
            'address' => 'Địa chỉ mới',
        ]);

        $this->assertSame(1, $updatedBooking->NhanVienID);
        $this->assertSame(1, $booking->fresh()->NhanVienID);
    }

    public function test_booking_update_rejects_client_staff_selection_without_authenticated_employee_profile(): void
    {
        $booking = $this->createBooking();
        $user = new User;
        $user->forceFill([
            'TaiKhoanID' => 2,
            'NhanVienID' => null,
            'TrangThai' => 'Hoạt động',
        ]);
        $this->actingAs($user);

        try {
            $this->bookingService->update($booking, ['staff_id' => 1]);
            $this->fail('A client-provided staff ID must not be accepted without an authenticated employee profile.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('staff_id', $exception->errors());
        }

        $this->assertSame(1, $booking->fresh()->NhanVienID);
    }

    public function test_booking_update_rejects_inactive_authenticated_employee_without_partial_changes(): void
    {
        $booking = $this->createBooking();
        $this->actingAsBookingEmployee();
        DB::table('NhanVien')->where('NhanVienID', 1)->update(['TrangThai' => 'Khóa']);
        $auditCount = NhatKyHeThong::query()->count();

        try {
            $this->bookingService->update($booking, [
                'staff_id' => 1,
                'address' => 'Không được lưu',
            ]);
            $this->fail('An inactive authenticated employee must not update a booking.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('staff_id', $exception->errors());
        }

        $this->assertSame('12 Nguyễn Huệ', $booking->fresh()->DiaChiNhan);
        $this->assertSame(1, $booking->fresh()->NhanVienID);
        $this->assertSame($auditCount, NhatKyHeThong::query()->count());
        $this->assertSame(0, DonHang::query()->count());
    }

    public function test_booking_confirmation_rejects_inactive_authenticated_employee_without_partial_changes(): void
    {
        $this->createInspectionCatalog();
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
        DB::table('NhanVien')->where('NhanVienID', 1)->update(['TrangThai' => 'Khóa']);
        $auditCount = NhatKyHeThong::query()->count();

        $this->post(route('bookings.approve', $booking->BookingID))
            ->assertRedirect(route('bookings.show', $booking))
            ->assertSessionHasErrors('booking');

        $booking->refresh();
        $this->assertSame(BookingStatus::Pending->value, $booking->TrangThai);
        $this->assertSame(1, $booking->NhanVienID);
        $this->assertNull($booking->NhanVienXacNhanID);
        $this->assertNull($booking->ThoiGianXacNhan);
        $this->assertSame($auditCount, NhatKyHeThong::query()->count());
        $this->assertSame(0, DonHang::query()->count());
        $this->assertSame(0, ChiTietDonHang::query()->count());
        $this->assertSame(0, GiaoNhan::query()->count());
    }

    public function test_booking_confirmation_retry_rejects_inactive_employee_without_changing_existing_order(): void
    {
        $this->createInspectionCatalog();
        $booking = $this->createBooking();
        $this->actingAsBookingEmployee();
        $order = $this->createOrderThroughInspection($booking, $this->actualItems());
        $bookingSnapshot = $booking->fresh()->getAttributes();
        $orderSnapshot = $order->fresh()->getAttributes();
        $auditCount = NhatKyHeThong::query()->count();
        DB::table('NhanVien')->where('NhanVienID', 1)->update(['TrangThai' => 'Khóa']);

        try {
            $this->bookingService->inspectBookingAndCreateOrder(
                $booking->fresh(),
                999,
                $this->actualItems(),
            );
            $this->fail('An inactive authenticated employee must not receive a successful confirmation retry.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('booking', $exception->errors());
        }

        $this->assertSame($bookingSnapshot, $booking->fresh()->getAttributes());
        $this->assertSame($orderSnapshot, $order->fresh()->getAttributes());
        $this->assertSame($auditCount, NhatKyHeThong::query()->count());
        $this->assertSame(1, DonHang::query()->where('BookingID', $booking->BookingID)->count());
        $this->assertSame(1, ChiTietDonHang::query()->where('DonHangID', $order->DonHangID)->count());
    }

    public function test_confirm_route_rejects_missing_authenticated_employee_even_when_client_submits_one(): void
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
        $user = new User;
        $user->forceFill([
            'TaiKhoanID' => 2,
            'NhanVienID' => null,
            'TrangThai' => 'Hoạt động',
        ]);
        $this->actingAs($user);
        $this->withoutMiddleware([
            EnsureUserHasPermission::class,
            RejectCustomerRole::class,
        ]);

        $this->from(route('bookings.index'))
            ->post(route('bookings.approve', $booking->BookingID))
            ->assertRedirect(route('bookings.show', $booking))
            ->assertSessionHasErrors('booking');

        $this->assertSame(BookingStatus::Pending->value, $booking->fresh()->TrangThai);
        $this->assertSame(1, $booking->fresh()->NhanVienID);
        $this->assertNull($booking->fresh()->NhanVienXacNhanID);
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
        $this->prepareBookingForInspection($booking);

        $databaseError = new PDOException('SQLSTATE[42703]: Undefined column: 7 ERROR: record "old" has no field "trangthai" CONTEXT: trigger function');
        $databaseError->errorInfo = ['42703', 7, 'record "old" has no field "trangthai"'];
        $queryException = new QueryException(
            'pgsql',
            'insert into "DonHang"',
            [],
            $databaseError,
        );
        $orderService = Mockery::mock(OrderService::class);
        $orderService->shouldReceive('createFromBooking')
            ->once()
            ->andThrow($queryException);
        $this->app->instance(BookingService::class, new BookingService(
            $orderService,
            app(PricingService::class),
            app(NotificationService::class),
        ));
        Log::shouldReceive('error')
            ->once()
            ->with('Booking operation failed', Mockery::on(
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
            'Không thể tạo đơn hàng vì trigger Supabase đang đọc sai tên cột trạng thái: schema dùng "TrangThai" có phân biệt chữ hoa/thường. Giao dịch tạo đơn đã bị hủy; trạng thái và chứng từ đã lưu trước đó được giữ nguyên.',
        );
        $this->assertSame(BookingStatus::Confirmed->value, $booking->fresh()->TrangThai);
        $this->assertSame(1, $booking->fresh()->NhanVienXacNhanID);
        $this->assertNotNull($booking->fresh()->ThoiGianXacNhan);
        $this->assertSame(0, DonHang::query()->count());
        $this->assertSame(0, GiaoNhan::query()->count());
        $this->assertSame(1, NhatKyHeThong::query()->where('HanhDong', 'Xác nhận Booking')->count());
    }

    public function test_confirm_route_explains_that_service_lines_are_required_and_does_not_confirm_empty_booking(): void
    {
        $booking = $this->createBooking();
        $this->actingAsBookingEmployee();
        $this->withoutMiddleware([
            EnsureUserHasPermission::class,
            RejectCustomerRole::class,
        ]);
        $this->prepareBookingForInspection($booking);

        $this->from(route('bookings.index'))
            ->post(route('bookings.confirm', $booking->BookingID), $this->inspectionPayload($booking, 1))
            ->assertRedirect(route('bookings.inspection', $booking))
            ->assertSessionHasErrors([
                'items' => 'Vui lòng nhập ít nhất một dòng kiểm tra thực tế trước khi tạo đơn.',
            ]);

        $this->assertSame(BookingStatus::Confirmed->value, $booking->fresh()->TrangThai);
        $this->assertSame(1, $booking->fresh()->NhanVienXacNhanID);
        $this->assertNotNull($booking->fresh()->ThoiGianXacNhan);
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

    public function test_inspection_rolls_back_when_order_item_creation_fails_mid_transaction(): void
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
            $this->createOrderThroughInspection($booking->fresh(), $this->inspectionItems($booking));
            $this->fail('An order-item insert failure must abort booking confirmation.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated order-item insert failure.', $exception->getMessage());
        }

        $this->assertSame(BookingStatus::Confirmed->value, $booking->fresh()->TrangThai);
        $this->assertSame(1, $booking->fresh()->NhanVienXacNhanID);
        $this->assertNotNull($booking->fresh()->ThoiGianXacNhan);
        $this->assertSame(0, DonHang::query()->count());
        $this->assertSame(0, ChiTietDonHang::query()->count());
        $this->assertSame(0, GiaoNhan::query()->count());
        $this->assertSame(1, NhatKyHeThong::query()->where('HanhDong', 'Xác nhận Booking')->count());
    }

    public function test_booking_creation_is_written_to_the_system_audit_log(): void
    {
        $this->actingAsBookingEmployee();
        $managerId = $this->createManagementAccount();

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
        $this->assertDatabaseHas('ThongBao', [
            'TaiKhoanID' => $managerId,
            'BookingID' => $booking->BookingID,
            'LoaiThongBao' => 'new_booking',
        ]);
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
        $this->createOrderThroughInspection($booking, $this->inspectionItems($booking));

        $order = DonHang::query()->where('BookingID', $booking->BookingID)->firstOrFail();
        $items = ChiTietDonHang::query()
            ->where('DonHangID', $order->DonHangID)
            ->orderBy('ChiTietDonHangID')
            ->get();

        $this->assertCount(2, $items);
        $this->assertSame([1.5, 4.0], $items->pluck('KhoiLuong')->map(fn ($weight): float => (float) $weight)->all());
        $this->assertSame([30000.0, 40000.0], $items->pluck('ThanhTien')->map(fn ($amount): float => (float) $amount)->all());
        $this->assertNull($items[0]->SoLuong);
        $this->assertSame(80000.0, (float) $order->ThanhTien);
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
        $this->actingAsBookingEmployee();
        $managerId = $this->createManagementAccount();
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
            'NhanVienID' => 1,
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
        $this->assertDatabaseHas('ThongBao', [
            'TaiKhoanID' => $managerId,
            'DonHangID' => $order->DonHangID,
            'LoaiThongBao' => 'new_order',
        ]);
    }

    public function test_direct_counter_home_return_keeps_its_existing_delivery_creation_behavior(): void
    {
        $this->actingAsBookingEmployee();
        $this->createRequestCatalog();
        DB::table('BangGia')->insert([
            'DichVuID' => 1,
            'LoaiDoGiatID' => 2,
            'DonViTinhID' => 2,
            'DonGia' => 15000,
            'NgayApDung' => '2026-01-01',
            'TrangThai' => 'Hoạt động',
        ]);

        $order = app(OrderService::class)->create([
            'KhachHangID' => 9,
            'NhanVienID' => 1,
            'TrangThai' => OrderStatus::Received->value,
            'HinhThucNhanDo' => 'Tại cửa hàng',
            'HinhThucTraDo' => 'Tại nhà',
            'DiaChiTra' => '88 Lê Lợi',
            'items' => [[
                'DichVuID' => 1,
                'LoaiDoGiatID' => 2,
                'DonViTinhID' => 2,
                'SoLuong' => 1,
                'TinhTrangTruocKhiGiat' => 'Bình thường',
            ]],
        ]);

        $returnDelivery = $order->giaoNhans()->where('LoaiGiaoNhan', 'GIAO_DO')->firstOrFail();
        $this->assertSame('Tại nhà', $returnDelivery->HinhThuc);
        $this->assertSame('88 Lê Lợi', $returnDelivery->DiaChi);
        $this->assertSame('Chờ thực hiện', $returnDelivery->TrangThai);
    }

    public function test_order_creation_rejects_a_missing_exact_price_tuple(): void
    {
        $this->actingAsBookingEmployee();
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
                'NhanVienID' => 1,
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

    public function test_order_conversion_rejects_a_booking_without_a_saved_inspection(): void
    {
        $booking = $this->createBooking();
        $this->actingAsBookingEmployee();
        $this->prepareBookingForInspection($booking);

        try {
            $this->bookingService->inspectBookingAndCreateOrder($booking, 1, $this->inspectionItems($booking));
            $this->fail('A booking without a saved inspection must not become an order.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('booking', $exception->errors());
        }

        $this->assertSame(BookingStatus::Confirmed->value, $booking->fresh()->TrangThai);
        $this->assertSame('Đang thực hiện', $booking->fresh()->giaoNhanDuKiens->firstWhere('LoaiGiaoNhan', 'NHAN_DO')->TrangThai);
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
        $this->actingAsBookingEmployee();
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
            'NhanVienID' => 1,
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

    public function test_order_request_does_not_require_client_employee_id_for_creation_but_requires_it_for_update(): void
    {
        $this->createRequestCatalog();

        $createData = $this->validateOrderRequest([], 'POST');
        $this->assertArrayNotHasKey('NhanVienID', $createData);

        try {
            $this->validateOrderRequest(['NhanVienID' => null], 'PUT');
            $this->fail('An employee remains required when updating an existing order.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('NhanVienID', $exception->errors());
        }

        $updatedData = $this->validateOrderRequest(['NhanVienID' => 1], 'PUT');
        $this->assertSame(1, $updatedData['NhanVienID']);
    }

    public function test_booking_confirmation_request_does_not_validate_client_employee_id(): void
    {
        $this->createRequestCatalog();
        $request = new XacNhanBookingRequest;
        $this->assertArrayNotHasKey('staff_id', $request->rules());
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
        $booking = $this->createBooking();
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
            $this->createOrderThroughInspection($booking, [[
                'DichVuID' => 1,
                'LoaiDoGiatID' => 2,
                'DonViTinhID' => 2,
                'KhoiLuong' => 1,
                'TinhTrangTruocKhiGiat' => 'Ố nhẹ ở cổ áo',
            ]]);
            $this->fail('A piece-based unit must not convert a weight snapshot.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('items.0.SoLuong', $exception->errors());
        }

        $this->assertSame(BookingStatus::Confirmed->value, $booking->fresh()->TrangThai);
        $this->assertSame(0, DonHang::query()->count());
    }

    #[DataProvider('deliveryCombinations')]
    public function test_inspection_keeps_delivery_legs_on_booking_without_duplicate_order_deliveries(string $receive, string $return, array $types): void
    {
        $this->createInspectionCatalog();
        $booking = $this->createBooking([
            'HinhThucNhanDo' => $receive,
            'HinhThucTraDo' => $return,
            'DiaChiTra' => '88 Lê Lợi',
        ]);
        $this->actingAsBookingEmployee();
        $items = $this->actualItems();
        $order = $this->createOrderThroughInspection($booking, $items);
        $intake = DB::table('BookingGiaoNhan')->where('BookingID', $booking->BookingID)->where('LoaiGiaoNhan', 'NHAN_DO')->first();
        $this->assertSame($receive, $intake->HinhThuc);
        $this->assertSame($receive === 'Tại nhà' ? '12 Nguyễn Huệ' : null, $intake->DiaChi);
        $this->assertSame('Hoàn thành', $intake->TrangThai);
        $this->assertSame(1, $intake->NhanVienID);
        $this->assertNotNull($intake->ThoiGianThucTe);
        $deliveries = $order->giaoNhans()->orderBy('GiaoNhanID')->get();

        $this->assertSame($types, $deliveries->pluck('LoaiGiaoNhan')->all());
        $this->assertSame(OrderStatus::Received->value, $order->TrangThai);
        $this->assertSame('Ố nhẹ ở cổ áo', $order->chiTietDonHangs->first()->TinhTrangTruocKhiGiat);
        $expectedPickupFee = $receive === 'Tại nhà' ? 10000.0 : 0.0;
        $expectedReturnFee = $return === 'Tại nhà' ? 15000.0 : 0.0;
        $this->assertSame($expectedPickupFee + $expectedReturnFee, (float) $order->PhiGiaoHang);
        $this->assertSame($expectedPickupFee, (float) $booking->fresh()->PickupDeliveryFee);
        $this->assertSame($expectedReturnFee, (float) $booking->fresh()->DeliveryFee);
        $this->assertSame([], $deliveries->pluck('LoaiGiaoNhan')->all());
        $this->assertSame(2, BookingGiaoNhan::query()->where('BookingID', $booking->BookingID)
            ->where('DonHangID', $order->DonHangID)->count());
        $sameOrder = $this->bookingService->inspectBookingAndCreateOrder($booking->fresh(), 1, $items);
        $this->assertSame($order->DonHangID, $sameOrder->DonHangID);
        $this->assertSame(count($types), GiaoNhan::query()->count());
        $this->assertSame(1, DonHang::query()->count());
    }

    public static function deliveryCombinations(): array
    {
        return [
            'TC-BK-RETURN-01' => ['Tại cửa hàng', 'Tại cửa hàng', []],
            'TC-BK-RETURN-02' => ['Tại cửa hàng', 'Tại nhà', []],
            'TC-BK-RETURN-03' => ['Tại nhà', 'Tại cửa hàng', []],
            'TC-BK-RETURN-04' => ['Tại nhà', 'Tại nhà', []],
        ];
    }

    public function test_booking_confirmation_preserves_mobile_fees_when_addresses_are_edited(): void
    {
        $this->createInspectionCatalog();
        DB::table('TaiKhoan')->insert(['TaiKhoanID' => 90, 'KhachHangID' => 9, 'TenDangNhap' => 'mobile-customer']);
        $booking = $this->createBooking([
            'HinhThucTraDo' => 'Tại nhà',
            'DiaChiTra' => '88 Lê Lợi',
            'PickupDeliveryFee' => 1000,
            'DeliveryFee' => 2000,
        ]);
        $this->actingAsBookingEmployee();

        $order = $this->createOrderThroughInspection($booking, $this->actualItems(), [
            'method' => 'Tại nhà',
            'address' => 'Địa chỉ nhận mới',
            'return_method' => 'Tại nhà',
            'return_address' => 'Địa chỉ trả mới',
        ]);
        $deliveries = $order->giaoNhans()->orderBy('LoaiGiaoNhan')->get()->keyBy('LoaiGiaoNhan');

        $this->assertSame(3000.0, (float) $order->PhiGiaoHang);
        $this->assertSame(1000.0, (float) $deliveries->sum('PhiGiaoNhan'));
        $this->assertSame(1000.0, (float) $deliveries['NHAN_DO']->PhiGiaoNhan);
        $this->assertSame('12 Nguyễn Huệ', $deliveries['NHAN_DO']->DiaChi);
        $this->assertDatabaseHas('BookingGiaoNhan', [
            'BookingID' => $booking->BookingID,
            'LoaiGiaoNhan' => 'GIAO_DO',
            'DiaChi' => '88 Lê Lợi',
            'PhiGiaoNhan' => 2000,
        ]);
        Http::assertNothingSent();
        $this->assertSame(1000.0, (float) $booking->fresh()->PickupDeliveryFee);
        $this->assertSame(2000.0, (float) $booking->fresh()->DeliveryFee);
    }

    public function test_delivery_quote_does_not_replace_saved_booking_fees_during_conversion(): void
    {
        $this->createInspectionCatalog();
        $this->createPromotionTable();
        $booking = $this->createBooking([
            'HinhThucTraDo' => 'Tại nhà',
            'DiaChiTra' => '88 Lê Lợi',
            'PickupDeliveryFee' => 1000,
            'DeliveryFee' => 2000,
        ]);
        DB::table('TaiKhoan')->insert(['TaiKhoanID' => 90, 'KhachHangID' => 9, 'TenDangNhap' => 'mobile-customer']);
        $this->actingAsBookingEmployee();
        $this->withoutMiddleware([Authenticate::class, EnsureUserHasPermission::class, RejectCustomerRole::class, RestoreRememberedLogin::class]);
        $this->prepareBookingForInspection($booking);

        $quoteResponse = $this->postJson(route('bookings.delivery-fee-quote', $booking), [
            'method' => 'Tại nhà',
            'address' => 'Địa chỉ nhận mới',
            'return_method' => 'Tại nhà',
            'return_address' => 'Địa chỉ trả mới',
        ])->assertOk();
        $quote = $quoteResponse->json();
        $this->assertGreaterThan(0, $quote['total_fee']);
        $this->assertSame(1000.0, (float) $booking->fresh()->PickupDeliveryFee);
        $this->assertSame(2000.0, (float) $booking->fresh()->DeliveryFee);

        $response = $this->post(route('bookings.confirm', $booking), [
            ...$this->inspectionPayload($booking, 1),
            'address' => 'Địa chỉ nhận mới',
            'return_method' => 'Tại nhà',
            'return_address' => 'Địa chỉ trả mới',
            'items' => $this->actualItems(),
        ]);

        $order = DonHang::query()->where('BookingID', $booking->BookingID)->firstOrFail();
        $response->assertRedirect(route('orders.show', $order));
        $savedBooking = $booking->fresh();
        $this->assertSame(3000.0, (float) $order->PhiGiaoHang);
        $this->assertSame(1000.0, (float) $savedBooking->PickupDeliveryFee);
        $this->assertSame(2000.0, (float) $savedBooking->DeliveryFee);
    }

    public function test_booking_delivery_quote_reports_missing_addresses_and_route_service_errors(): void
    {
        $booking = $this->createBooking();
        $this->actingAsBookingEmployee();
        $this->withoutMiddleware([Authenticate::class, EnsureUserHasPermission::class, RejectCustomerRole::class, RestoreRememberedLogin::class]);
        $this->prepareBookingForInspection($booking);

        $this->postJson(route('bookings.delivery-fee-quote', $booking), [
            'method' => 'Tại nhà',
            'return_method' => 'Tại nhà',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['address', 'return_address']);

        $this->deliveryRouteFailure = true;
        $this->postJson(route('bookings.delivery-fee-quote', $booking), [
            'method' => 'Tại nhà',
            'address' => 'Địa chỉ nhận mới',
            'return_method' => 'Tại cửa hàng',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('delivery');
    }

    public function test_inspection_rejects_negative_saved_delivery_fee_without_creating_an_order(): void
    {
        $this->createInspectionCatalog();
        $booking = $this->createBooking([
        ]);
        $booking->forceFill([
            'PickupDeliveryFee' => 10000,
            'DeliveryFee' => -1,
        ])->saveQuietly();
        $this->actingAsBookingEmployee();
        $this->deliveryRouteFailure = true;

        try {
            $this->createOrderThroughInspection($booking, $this->actualItems(), [
                'method' => 'Tại cửa hàng',
                'address' => null,
                'return_method' => 'Tại nhà',
                'return_address' => 'Địa chỉ trả mới',
            ]);
            $this->fail('Booking confirmation must fail when its delivery fee is negative.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('return_fee', $exception->errors());
        }

        $savedBooking = $booking->fresh();
        $this->assertSame(BookingStatus::Confirmed->value, $savedBooking->TrangThai);
        $this->assertNotNull($savedBooking->ThoiGianXacNhan);
        $this->assertSame('Tại nhà', $savedBooking->HinhThucNhanDo);
        $this->assertSame('12 Nguyễn Huệ', $savedBooking->DiaChiNhan);
        $this->assertSame(10000.0, (float) $savedBooking->PickupDeliveryFee);
        $this->assertSame(-1.0, (float) $savedBooking->DeliveryFee);
        $this->assertSame(0, DonHang::query()->count());
        $this->assertSame(0, ChiTietDonHang::query()->count());
        $this->assertSame(0, GiaoNhan::query()->count());
    }

    public function test_order_uses_actual_inspection_data_and_server_price_without_replacing_booking_estimates(): void
    {
        $this->createInspectionCatalog();
        $booking = $this->createBooking();
        $this->bookingService->update($booking, ['items' => $this->actualItems()]);
        DB::table('TaiKhoan')->insert(['TaiKhoanID' => 90, 'KhachHangID' => 9, 'TenDangNhap' => 'mobile-customer']);
        $this->assertSame(0, DonHang::query()->count());
        $this->actingAsBookingEmployee();
        $actual = $this->actualItems();
        $actual[0]['SoLuong'] = 5;
        $actual[0]['DonGia'] = 1;
        $actual[0]['ThanhTien'] = 1;
        $actual[0]['GhiChu'] = 'Giặt riêng';
        $order = $this->createOrderThroughInspection($booking, $actual);

        $this->assertSame(85000.0, (float) $order->ThanhTien);
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
        $booking = $this->createBooking();
        $this->actingAsBookingEmployee();
        $this->withoutMiddleware([EnsureUserHasPermission::class, RejectCustomerRole::class]);
        $this->prepareBookingForInspection($booking);
        $actual = [array_merge($this->actualItems()[0], $itemData)];
        $payload = array_replace($this->inspectionPayload($booking, 1), [
            'method' => $bookingData['HinhThucNhanDo'] ?? $booking->HinhThucNhanDo,
            'address' => $bookingData['DiaChiNhan'] ?? $booking->DiaChiNhan,
            'return_method' => array_key_exists('HinhThucTraDo', $bookingData)
                ? $bookingData['HinhThucTraDo']
                : $booking->HinhThucTraDo,
            'return_address' => array_key_exists('DiaChiTra', $bookingData)
                ? $bookingData['DiaChiTra']
                : $booking->DiaChiTra,
            'items' => $actual,
        ]);
        if (array_key_exists('DiaChiNhan', $bookingData) && $bookingData['DiaChiNhan'] === null) {
            $payload['address'] = null;
        }
        if (array_key_exists('DiaChiTra', $bookingData) && $bookingData['DiaChiTra'] === null) {
            $payload['return_address'] = null;
        }
        $this->post(route('bookings.confirm', $booking), $payload)
            ->assertRedirect(route('bookings.inspection', $booking))
            ->assertSessionHasErrors($errorKey);

        $this->assertSame(BookingStatus::Confirmed->value, $booking->fresh()->TrangThai);
        $this->assertNotNull($booking->fresh()->ThoiGianXacNhan);
        $this->assertSame(0, DonHang::query()->count());
        $this->assertSame(0, ChiTietDonHang::query()->count());
        $this->assertSame(0, GiaoNhan::query()->count());
        $this->assertSame(1, NhatKyHeThong::query()->where('HanhDong', 'Xác nhận Booking')->count());
    }

    public static function invalidInspectionInputs(): array
    {
        return [
            'missing legacy return method' => [['HinhThucTraDo' => null], [], 'return_method'],
            'invalid return method' => [['HinhThucTraDo' => 'GIAO_DO'], [], 'return_method'],
            'missing home return address' => [['HinhThucTraDo' => 'Tại nhà', 'DiaChiTra' => null], [], 'return_address'],
            'missing home receive address' => [['DiaChiNhan' => null], [], 'address'],
            'missing condition' => [[], ['TinhTrangTruocKhiGiat' => null], 'items.0.TinhTrangTruocKhiGiat'],
            'blank condition' => [[], ['TinhTrangTruocKhiGiat' => '   '], 'items.0.TinhTrangTruocKhiGiat'],
            'overlong condition' => [[], ['TinhTrangTruocKhiGiat' => str_repeat('a', 321)], 'items.0.TinhTrangTruocKhiGiat'],
            'fractional pieces' => [[], ['SoLuong' => 1.5], 'items.0.SoLuong'],
            'both measurements' => [[], ['KhoiLuong' => 2], 'items.0.KhoiLuong'],
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
        $booking = $this->createBooking(['GhiChu' => 'Ghi chú từ khách hàng cần giữ']);
        $this->bookingService->update($booking, ['items' => $this->actualItems()]);
        $this->actingAsBookingEmployee();
        $this->prepareBookingForInspection($booking);
        $this->createPermissionTables();
        $this->withoutMiddleware([Authenticate::class, EnsureUserHasPermission::class, RejectCustomerRole::class, RestoreRememberedLogin::class]);
        $this->get(route('bookings.inspection', $booking))
            ->assertOk()
            ->assertSee('Kiểm tra thực tế trước khi tạo đơn')
            ->assertSee('TinhTrangTruocKhiGiat')
            ->assertSee('name="return_method"', false)
            ->assertSee('Ghi chú từ khách hàng cần giữ')
            ->assertSee('Ghi chú từ khách hàng')
            ->assertDontSee('name="quality_difference"', false)
            ->assertDontSee('id="quality_difference"', false)
            ->assertDontSee('items[0][GhiChu]', false)
            ->assertSee('Xác nhận nhận đồ và tạo đơn', false);
        $this->assertSame(BookingStatus::Confirmed->value, $booking->fresh()->TrangThai);
        $this->assertSame(0, DonHang::query()->count());
        $this->assertSame(0, ChiTietDonHang::query()->count());
        $this->assertSame(0, GiaoNhan::query()->count());
    }

    public function test_booking_conversion_does_not_create_a_return_delivery(): void
    {
        $this->createInspectionCatalog();
        $booking = $this->createBooking(['HinhThucTraDo' => 'Tại nhà', 'DiaChiTra' => '88 Lê Lợi']);
        DB::statement("CREATE TRIGGER reject_return BEFORE INSERT ON GiaoNhan WHEN NEW.LoaiGiaoNhan = 'GIAO_DO' BEGIN SELECT RAISE(ABORT, 'simulated return failure'); END");
        $this->actingAsBookingEmployee();

        $order = $this->createOrderThroughInspection($booking, $this->actualItems());

        $this->assertSame(BookingStatus::Confirmed->value, $booking->fresh()->TrangThai);
        $this->assertNotNull($booking->fresh()->ThoiGianXacNhan);
        $this->assertSame(1, DonHang::query()->count());
        $this->assertSame(1, ChiTietDonHang::query()->where('DonHangID', $order->DonHangID)->count());
        $this->assertSame(0, GiaoNhan::query()->where('LoaiGiaoNhan', 'GIAO_DO')->count());
        $this->assertSame(1, BookingGiaoNhan::query()->where('BookingID', $booking->BookingID)
            ->where('LoaiGiaoNhan', 'GIAO_DO')->count());
        $this->assertSame(1, NhatKyHeThong::query()->where('HanhDong', 'Xác nhận Booking')->count());
    }

    public function test_create_and_inspection_reject_inactive_employee_without_side_effects(): void
    {
        $this->createInspectionCatalog();
        $booking = $this->createBooking();
        $this->actingAsBookingEmployee();
        $this->prepareBookingForInspection($booking);
        DB::table('NhanVien')->where('NhanVienID', 1)->update(['TrangThai' => 'Khóa']);
        try {
            app(OrderService::class)->create(['KhachHangID' => 9, 'NhanVienID' => 1,
                'TrangThai' => OrderStatus::Received->value, 'items' => $this->actualItems()]);
            $this->fail('Inactive employee accepted for a direct order.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('NhanVienID', $exception->errors());
        }
        try {
            $this->bookingService->submitInspection(
                $booking,
                array_replace($this->inspectionPayload($booking, 1), ['items' => $this->actualItems()]),
            );
            $this->fail('Inactive employee accepted for Booking inspection.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('booking', $exception->errors());
        }
        $this->assertSame(0, DonHang::query()->count());
        $this->assertSame(0, GiaoNhan::query()->count());
    }

    public function test_manual_order_request_rejects_locked_employee(): void
    {
        $this->createRequestCatalog();
        DB::table('NhanVien')->where('NhanVienID', 1)->update(['TrangThai' => 'Ngừng hoạt động']);
        $this->withoutMiddleware([Authenticate::class, EnsureUserHasPermission::class, RejectCustomerRole::class, RestoreRememberedLogin::class]);
        $this->post(route('orders.store'), [
            'KhachHangID' => 9, 'NhanVienID' => 1, 'TrangThai' => OrderStatus::Received->value,
            'items' => [['DichVuID' => 1, 'LoaiDoGiatID' => 2, 'DonViTinhID' => 2, 'SoLuong' => 1, 'TinhTrangTruocKhiGiat' => 'Bình thường']],
        ])->assertSessionHasErrors('NhanVienID');
        $this->assertSame(0, DonHang::count());
    }

    public function test_booking_inspection_uses_current_customer_and_inspection_address(): void
    {
        $this->createInspectionCatalog();
        $booking = $this->createBooking();
        DB::table('KhachHang')->insert(['KhachHangID' => 10]);
        DB::table('Booking')->where('BookingID', $booking->BookingID)->update(['KhachHangID' => 10]);
        DB::table('TaiKhoan')->insert(['TaiKhoanID' => 90, 'KhachHangID' => 10, 'TenDangNhap' => 'mobile-customer']);
        $this->actingAsBookingEmployee();
        $order = $this->createOrderThroughInspection($booking, $this->actualItems(), [
            'address' => 'Địa chỉ kiểm tra mới',
        ]);
        $this->assertSame(10, $order->KhachHangID);
        $this->assertSame('12 Nguyễn Huệ', BookingGiaoNhan::query()->where('BookingID', $booking->BookingID)
            ->where('LoaiGiaoNhan', 'NHAN_DO')->firstOrFail()->DiaChi);
    }

    public function test_standalone_conversion_rejects_booking_cancelled_since_snapshot(): void
    {
        $this->createInspectionCatalog();
        $booking = $this->createBooking();
        DB::table('Booking')->where('BookingID', $booking->BookingID)->update(['TrangThai' => BookingStatus::Cancelled->value]);
        $this->expectException(ValidationException::class);
        app(OrderService::class)->createFromBooking($booking, $this->actualItems(), 1);
    }

    public function test_mobile_aliases_and_exact_fees_survive_conversion_without_route_api(): void
    {
        $this->createInspectionCatalog();
        $booking = $this->createBooking(['HinhThucTraDo' => null]);
        $booking->forceFill([
            'HinhThucGiaoDo' => 'Tại nhà', 'DiaChiGiao' => 'Địa chỉ mobile',
            'PickupDeliveryFee' => 11111, 'DeliveryFee' => 22222,
            'PickupDistanceMeters' => 1234, 'DeliveryDistanceMeters' => 5678,
            'PhuongThucThanhToan' => 'Chuyển khoản',
        ])->saveQuietly();
        $this->deliveryRouteFailure = true;
        $this->actingAsBookingEmployee();
        // Unsaved or posted money must not replace the locked mobile snapshot.
        $booking->forceFill(['DeliveryFee' => 999999]);
        $order = $this->createOrderThroughInspection($booking, $this->actualItems());
        $this->assertSame(33333.0, (float) $order->PhiGiaoHang);
        $this->assertSame(63333.0, (float) $order->ThanhTien);
        $this->assertSame(22222.0, (float) $booking->fresh()->DeliveryFee);
        $this->assertSame(0, $order->giaoNhans()->where('LoaiGiaoNhan', 'GIAO_DO')->count());
        $this->assertSame(33333.0, (float) BookingGiaoNhan::query()->where('BookingID', $booking->BookingID)->sum('PhiGiaoNhan'));
        Http::assertNothingSent();
    }

    public function test_malformed_raw_mobile_fee_is_rejected_before_float_cast(): void
    {
        $booking = $this->createBooking();
        DB::table('Booking')->where('BookingID', $booking->getKey())->update(['PickupDeliveryFee' => 'not-a-number']);
        try {
            $booking->fresh()->deliveryFeeSnapshot();
            $this->fail('Malformed stored fees must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('pickup_fee', $exception->errors());
        }
    }

    public function test_zero_mobile_fees_are_valid_and_not_recalculated(): void
    {
        $this->createInspectionCatalog();
        $booking = $this->createBooking(['PickupDeliveryFee' => 0, 'DeliveryFee' => 0]);
        $this->actingAsBookingEmployee();
        $order = $this->createOrderThroughInspection($booking, $this->actualItems());
        $this->assertSame(0.0, (float) $order->PhiGiaoHang);
        $this->assertSame(30000.0, (float) $order->ThanhTien);
        Http::assertNothingSent();
    }

    public function test_switching_return_to_store_clears_both_mobile_address_aliases(): void
    {
        $booking = $this->createBooking(['HinhThucTraDo' => 'Tại nhà', 'DiaChiTra' => 'Địa chỉ cũ']);
        $booking->forceFill(['HinhThucGiaoDo' => 'Tại nhà', 'DiaChiGiao' => 'Địa chỉ mobile cũ'])->saveQuietly();
        $this->bookingService->update($booking, ['return_method' => 'Tại cửa hàng']);
        $fresh = $booking->fresh();
        $this->assertSame('Tại cửa hàng', $fresh->HinhThucGiaoDo);
        $this->assertNull($fresh->DiaChiTra);
        $this->assertNull($fresh->DiaChiGiao);
    }

    public function test_inspection_preserves_booking_estimate_and_delivery_details(): void
    {
        $this->createInspectionCatalog();
        DB::table('TaiKhoan')->insert(['TaiKhoanID' => 90, 'KhachHangID' => 9, 'TenDangNhap' => 'mobile-customer']);
        $booking = $this->createBooking();
        $this->actingAsBookingEmployee();
        $order = $this->createOrderThroughInspection($booking, $this->actualItems(), [
            'method' => 'Tại cửa hàng',
            'address' => null,
            'return_method' => 'Tại nhà',
            'return_address' => 'Địa chỉ trả mới',
        ]);
        $this->assertSame(['NHAN_DO'], $order->giaoNhans()->pluck('LoaiGiaoNhan')->all());
        $this->assertDatabaseHas('BookingGiaoNhan', [
            'BookingID' => $booking->BookingID,
            'LoaiGiaoNhan' => 'GIAO_DO',
            'HinhThuc' => 'Tại cửa hàng',
            'DiaChi' => null,
        ]);
        $this->assertSame(10000.0, (float) $order->PhiGiaoHang);
        $this->assertSame(10000.0, (float) $booking->fresh()->PickupDeliveryFee);
        $this->assertSame(0.0, (float) $booking->fresh()->DeliveryFee);
        $this->assertSame('Tại nhà', $booking->fresh()->HinhThucNhanDo);
        $this->assertSame('12 Nguyễn Huệ', $booking->fresh()->DiaChiNhan);
    }

    public function test_order_price_options_preserve_multiple_units_and_form_exposes_unit_selection(): void
    {
        $this->createRequestCatalog();
        DB::table('BangGia')->insert([
            ['DichVuID' => 1, 'LoaiDoGiatID' => 2, 'DonViTinhID' => 1, 'DonGia' => 40000, 'TrangThai' => 'Hoạt động'],
            ['DichVuID' => 1, 'LoaiDoGiatID' => 2, 'DonViTinhID' => 2, 'DonGia' => 15000, 'TrangThai' => 'Hoạt động'],
        ]);
        Schema::create('KhuyenMai', function (Blueprint $table): void {
            $table->increments('KhuyenMaiID');
            $table->string('TrangThai');
        });
        $prices = app(PricingService::class)->orderOptions();
        $this->assertEqualsCanonicalizing([1, 2], $prices->pluck('DonViTinhID')->all());
        $this->withoutMiddleware([Authenticate::class, EnsureUserHasPermission::class, RejectCustomerRole::class, RestoreRememberedLogin::class]);
        $response = $this->get(route('orders.create'))->assertOk();
        $response->assertSee('Đơn vị tính')->assertSee('order-item-pricing.js');
        $this->assertMatchesRegularExpression('/<select[^>]*item-unit-value[^>]*name="items\[0\]\[DonViTinhID\]"/', $response->getContent());
        $this->assertMatchesRegularExpression('/<input[^>]*item-price[^>]*readonly/', $response->getContent());
    }
}
