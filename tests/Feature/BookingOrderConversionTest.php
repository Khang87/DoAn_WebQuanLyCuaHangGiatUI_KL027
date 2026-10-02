<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\OrderStatus;
use App\Http\Requests\Admin\LuuBookingRequest;
use App\Models\Booking;
use App\Models\ChiTietDonHang;
use App\Models\DonHang;
use App\Models\GiaoNhan;
use App\Models\NhatKyHeThong;
use App\Models\User;
use App\Services\BookingService;
use App\Services\OrderService;
use App\Services\PricingService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
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
        foreach (['NhatKyHeThong', 'ChiTietBooking', 'ChiTietDonHang', 'GiaoNhan', 'DonHang', 'Booking', 'BangGia', 'DonViTinh', 'LoaiDoGiat', 'DichVu', 'NhanVien', 'KhachHang'] as $table) {
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
            'status' => BookingStatus::Confirmed->value,
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

        $order = DonHang::query()->where('BookingID', $booking->BookingID)->firstOrFail();
        $items = ChiTietDonHang::query()->where('DonHangID', $order->DonHangID)->orderBy('ChiTietDonHangID')->get();
        $item = $items->firstOrFail();
        $delivery = GiaoNhan::query()->where('DonHangID', $order->DonHangID)->firstOrFail();

        $this->assertCount(2, $items);
        $this->assertSame('DH001', $order->MaDonHang);
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
        $this->assertNotNull($booking->fresh()->ThoiGianXacNhan);
        $audit = NhatKyHeThong::query()->where('BangDuLieu', 'Booking')->firstOrFail();
        $this->assertSame(1, NhatKyHeThong::query()->where('BangDuLieu', 'Booking')->count());
        $this->assertSame('Xác nhận Booking', $audit->HanhDong);
        $this->assertCount(2, $audit->DuLieuMoi['ChiTietBooking']);
        $this->assertSame(1, $audit->DuLieuMoi['Booking']['NhanVienXacNhanID']);
        $this->assertNotEmpty($audit->DuLieuMoi['Booking']['ThoiGianXacNhan']);

        $sameOrder = $this->bookingService->confirmAndCreateOrder($booking->fresh());
        $this->assertSame($order->DonHangID, $sameOrder?->DonHangID);
        $this->assertSame(1, DonHang::query()->where('BookingID', $booking->BookingID)->count());
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
                ['DichVuID' => 1, 'LoaiDoGiatID' => 2, 'DonViTinhID' => 1, 'KhoiLuong' => 2],
                ['DichVuID' => 1, 'LoaiDoGiatID' => 2, 'DonViTinhID' => 1, 'KhoiLuong' => 1],
            ],
        ]);
        $booking->refresh();
        $this->bookingService->update($booking, [
            'status' => BookingStatus::Confirmed->value,
        ]);

        $order = DonHang::query()->where('BookingID', $booking->BookingID)->firstOrFail();
        $items = ChiTietDonHang::query()
            ->where('DonHangID', $order->DonHangID)
            ->orderBy('ChiTietDonHangID')
            ->get();

        $this->assertCount(2, $items);
        $this->assertSame([2.0, 1.0], $items->pluck('KhoiLuong')->map(fn ($weight): float => (float) $weight)->all());
        $this->assertSame([30000.0, 30000.0], $items->pluck('ThanhTien')->map(fn ($amount): float => (float) $amount)->all());
        $this->assertNull($items[0]->SoLuong);
        $this->assertSame(60000.0, (float) $order->ThanhTien);
    }

    public function test_order_status_change_is_written_to_the_system_audit_log(): void
    {
        $order = DonHang::query()->create([
            'MaDonHang' => 'DH001',
            'KhachHangID' => 9,
            'TrangThai' => OrderStatus::Pending->value,
            'TongTien' => 0,
            'ThanhTien' => 0,
        ]);
        $this->actingAsBookingEmployee();

        app(OrderService::class)->updateStatus($order, OrderStatus::Received->value);

        $audit = NhatKyHeThong::query()->where('BangDuLieu', 'DonHang')->firstOrFail();
        $this->assertSame('Chuyển trạng thái đơn hàng', $audit->HanhDong);
        $this->assertSame(['TrangThai' => OrderStatus::Pending->value], $audit->DuLieuCu);
        $this->assertSame(['TrangThai' => OrderStatus::Received->value], $audit->DuLieuMoi);
        $this->assertSame(1, $audit->TaiKhoanID);
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

    public function test_booking_confirmation_rolls_back_when_no_service_snapshot_is_provided(): void
    {
        $booking = $this->createBooking();
        $this->actingAsBookingEmployee();

        try {
            $this->bookingService->update($booking, [
                'status' => BookingStatus::Confirmed->value,
            ]);
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
                ['DichVuID' => 1, 'LoaiDoGiatID' => 2, 'DonViTinhID' => 1, 'SoLuong' => null, 'KhoiLuong' => 2],
            ],
        ]);

        $this->assertEquals(2, $validated['items'][0]['KhoiLuong']);
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
            $this->bookingService->update($booking, [
                'status' => BookingStatus::Confirmed->value,
            ]);
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

    private function actingAsBookingEmployee(): void
    {
        $user = new User;
        $user->forceFill(['TaiKhoanID' => 1, 'NhanVienID' => 1]);
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
