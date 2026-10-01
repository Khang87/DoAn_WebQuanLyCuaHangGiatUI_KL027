<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\ChiTietDonHang;
use App\Models\DonHang;
use App\Models\GiaoNhan;
use App\Services\BookingService;
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
        foreach (['ChiTietDonHang', 'GiaoNhan', 'DonHang', 'Booking', 'BangGia', 'DonViTinh', 'KhachHang'] as $table) {
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

        $this->bookingService->update($booking, [
            'status' => BookingStatus::Confirmed->value,
            'customer_id' => 9,
            'method' => 'Tại nhà',
            'address' => '12 Nguyễn Huệ',
            'scheduled_date' => '2026-10-05',
            'scheduled_time' => '14:30',
            'service_id' => 1,
            'garment_id' => 2,
            'unit_id' => 3,
            'quantity' => 2,
            'weight' => null,
        ]);

        $order = DonHang::query()->where('BookingID', $booking->BookingID)->firstOrFail();
        $item = ChiTietDonHang::query()->where('DonHangID', $order->DonHangID)->firstOrFail();
        $delivery = GiaoNhan::query()->where('DonHangID', $order->DonHangID)->firstOrFail();

        $this->assertSame(1, ChiTietDonHang::query()->where('DonHangID', $order->DonHangID)->count());
        $this->assertSame('DH001', $order->MaDonHang);
        $this->assertSame(30000.0, (float) $order->TongTien);
        $this->assertSame(30000.0, (float) $order->ThanhTien);
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
    }

    public function test_weight_booking_uses_the_configured_minimum_and_keeps_the_actual_weight(): void
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

        $this->bookingService->update($booking, [
            'service_id' => 1,
            'garment_id' => 2,
            'unit_id' => 1,
            'quantity' => null,
            'weight' => 2,
        ]);
        $booking->refresh();
        $this->bookingService->update($booking, [
            'status' => BookingStatus::Confirmed->value,
        ]);

        $order = DonHang::query()->where('BookingID', $booking->BookingID)->firstOrFail();
        $item = ChiTietDonHang::query()->where('DonHangID', $order->DonHangID)->firstOrFail();

        $this->assertSame(2.0, (float) $item->KhoiLuong);
        $this->assertNull($item->SoLuong);
        $this->assertSame(30000.0, (float) $item->ThanhTien);
        $this->assertSame(30000.0, (float) $order->ThanhTien);
    }

    public function test_booking_confirmation_rolls_back_when_no_service_snapshot_is_provided(): void
    {
        $booking = $this->createBooking();

        try {
            $this->bookingService->update($booking, [
                'status' => BookingStatus::Confirmed->value,
            ]);
            $this->fail('A booking without an item snapshot must not become an order.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('service_id', $exception->errors());
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

    private function createSchema(): void
    {
        Schema::create('KhachHang', function (Blueprint $table): void {
            $table->increments('KhachHangID');
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
            $table->unsignedInteger('DichVuID')->nullable();
            $table->unsignedInteger('LoaiDoGiatID')->nullable();
            $table->unsignedInteger('DonViTinhID')->nullable();
            $table->decimal('SoLuong', 10, 2)->nullable();
            $table->decimal('KhoiLuong', 10, 2)->nullable();
            $table->decimal('DonGia', 18, 2)->nullable();
            $table->decimal('ThanhTien', 18, 2)->nullable();
            $table->unsignedInteger('NhanVienID')->nullable();
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
