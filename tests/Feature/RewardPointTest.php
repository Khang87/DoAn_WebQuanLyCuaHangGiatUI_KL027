<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\ChiTietBooking;
use App\Models\DiemTichLuy;
use App\Models\DonHang;
use App\Models\KhachHang;
use App\Models\User;
use App\Services\BookingService;
use App\Services\OrderService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RewardPointTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');

        $this->createSchema();
        $this->createCatalog();
    }

    protected function tearDown(): void
    {
        foreach ([
            'NhatKyHeThong',
            'ThanhToan',
            'HoaDon',
            'ChiTietBooking',
            'ChiTietDonHang',
            'GiaoNhan',
            'DonHang',
            'Booking',
            'DiemTichLuy',
            'BangGia',
            'DonViTinh',
            'LoaiDoGiat',
            'DichVu',
            'NhanVien',
            'KhachHang',
            'KhuyenMai',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        parent::tearDown();
    }

    public function test_delivered_order_awards_points_using_the_configured_rate_only_once(): void
    {
        $customer = $this->createCustomer(1, 2);
        $order = $this->createOrder($customer, 100000, 0);
        $order->update(['TrangThai' => OrderStatus::Received->value]);
        $service = app(OrderService::class);

        $service->updateStatus($order, OrderStatus::Washing->value);
        $service->updateStatus($order->fresh(), OrderStatus::Washed->value);
        $service->updateStatus($order->fresh(), OrderStatus::Delivering->value);
        $service->updateStatus($order, OrderStatus::Delivered->value);
        $service->updateStatus($order->fresh(), OrderStatus::Delivered->value);

        $this->assertSame(10002, $customer->fresh()->points());
        $this->assertSame(1, DB::table('NhatKyHeThong')
            ->where('BangDuLieu', 'DonHang')
            ->where('BanGhiID', $order->DonHangID)
            ->where('HanhDong', 'Cộng điểm tích lũy đơn hàng')
            ->count());
    }

    public function test_booking_conversion_redeems_selected_points_and_reduces_order_total(): void
    {
        $customer = $this->createCustomer(1, 500);
        $booking = $this->createBooking($customer);
        $this->actingAsBookingEmployee();

        $order = app(BookingService::class)->inspectBookingAndCreateOrder($booking, 1, [array_merge($this->orderItem(), ['TinhTrangTruocKhiGiat' => 'Bình thường'])], 100);

        $this->assertSame(100, $order->DiemSuDung);
        $this->assertSame(100.0, $order->TienGiamDoDiem);
        $this->assertSame(9900.0, $order->ThanhTien);
        $this->assertSame(400, $customer->fresh()->points());
    }

    public function test_booking_conversion_can_redeem_all_available_points_when_enabled(): void
    {
        $customer = $this->createCustomer(1, 500);
        $booking = $this->createBooking($customer);
        $this->actingAsBookingEmployee();

        $order = app(BookingService::class)->inspectBookingAndCreateOrder($booking, 1, [array_merge($this->orderItem(), ['TinhTrangTruocKhiGiat' => 'Bình thường'])], 0, true);

        $this->assertSame(500, $order->DiemSuDung);
        $this->assertSame(500.0, $order->TienGiamDoDiem);
        $this->assertSame(9500.0, $order->ThanhTien);
        $this->assertSame(0, $customer->fresh()->points());
    }

    public function test_order_creation_redeems_points_at_one_vnd_per_point(): void
    {
        $customer = $this->createCustomer(3, 500);

        $order = app(OrderService::class)->create([
            'MaDonHang' => 'MANUAL-CODE',
            'KhachHangID' => $customer->KhachHangID,
            'TrangThai' => OrderStatus::Pending->value,
            'DiemSuDung' => 500,
            'items' => [$this->orderItem()],
        ]);

        $this->assertSame(500, $order->DiemSuDung);
        $this->assertSame(500.0, $order->TienGiamDoDiem);
        $this->assertSame(9500.0, $order->ThanhTien);
        $this->assertSame('DH'.str_pad((string) $order->DonHangID, 4, '0', STR_PAD_LEFT), $order->MaDonHang);
        $this->assertSame(0, $customer->fresh()->points());
    }

    public function test_order_creation_redeems_all_available_points_when_enabled(): void
    {
        $customer = $this->createCustomer(4, 500);

        $order = app(OrderService::class)->create([
            'MaDonHang' => 'ALL-POINTS',
            'KhachHangID' => $customer->KhachHangID,
            'TrangThai' => OrderStatus::Pending->value,
            'use_points' => true,
            'DiemSuDung' => 1,
            'items' => [$this->orderItem()],
        ]);

        $this->assertSame(500, $order->DiemSuDung);
        $this->assertSame(500.0, $order->TienGiamDoDiem);
        $this->assertSame(9500.0, $order->ThanhTien);
        $this->assertSame(0, $customer->fresh()->points());
    }

    public function test_order_creation_does_not_redeem_points_when_switch_is_off(): void
    {
        $customer = $this->createCustomer(5, 500);

        $order = app(OrderService::class)->create([
            'MaDonHang' => 'NO-POINTS',
            'KhachHangID' => $customer->KhachHangID,
            'TrangThai' => OrderStatus::Pending->value,
            'use_points' => false,
            'DiemSuDung' => 500,
            'items' => [$this->orderItem()],
        ]);

        $this->assertSame(0, $order->DiemSuDung);
        $this->assertSame(10000.0, $order->ThanhTien);
        $this->assertSame(500, $customer->fresh()->points());
    }

    public function test_updating_order_to_another_customer_refunds_the_old_customer_and_redeems_from_the_new_one(): void
    {
        $oldCustomer = $this->createCustomer(1, 2);
        $newCustomer = $this->createCustomer(2, 10);
        $order = $this->createOrder($oldCustomer, 10000, 3);

        $updatedOrder = app(OrderService::class)->update($order, [
            'MaDonHang' => 'DH9999',
            'KhachHangID' => $newCustomer->KhachHangID,
            'TrangThai' => OrderStatus::Pending->value,
            'DiemSuDung' => 4,
            'items' => [$this->orderItem()],
        ]);

        $this->assertSame($order->MaDonHang, $updatedOrder->MaDonHang);
        $this->assertSame(5, $oldCustomer->fresh()->points());
        $this->assertSame(6, $newCustomer->fresh()->points());
        $this->assertSame($newCustomer->KhachHangID, $updatedOrder->KhachHangID);
        $this->assertSame(4, $updatedOrder->DiemSuDung);
        $this->assertSame(9996.0, $updatedOrder->ThanhTien);
    }

    public function test_repeated_order_edits_only_adjust_the_difference_in_redeemed_points(): void
    {
        $customer = $this->createCustomer(5, 7);
        $order = $this->createOrder($customer, 10000, 3);
        $service = app(OrderService::class);
        $orderData = [
            'KhachHangID' => $customer->KhachHangID,
            'TrangThai' => OrderStatus::Pending->value,
            'DiemSuDung' => 3,
            'items' => [$this->orderItem()],
        ];

        $service->update($order, $orderData);
        $this->assertSame(7, $customer->fresh()->points());

        $service->update($order->fresh(), $orderData);
        $this->assertSame(7, $customer->fresh()->points());

        $orderData['DiemSuDung'] = 5;
        $service->update($order->fresh(), $orderData);
        $this->assertSame(5, $customer->fresh()->points());

        $service->update($order->fresh(), $orderData);
        $this->assertSame(5, $customer->fresh()->points());
    }

    public function test_fully_paid_order_becomes_settled_when_delivery_is_completed(): void
    {
        $customer = $this->createCustomer(6, 0);
        $order = $this->createOrder($customer, 10000, 0);
        $order->update(['TrangThai' => OrderStatus::Received->value]);

        DB::table('HoaDon')->insert([
            'DonHangID' => $order->DonHangID,
            'ThanhTien' => 10000,
            'TrangThai' => 'Đã thanh toán',
        ]);
        DB::table('ThanhToan')->insert([
            'DonHangID' => $order->DonHangID,
            'SoTien' => 10000,
            'TrangThai' => PaymentStatus::Paid->value,
        ]);

        $service = app(OrderService::class);
        foreach ([OrderStatus::Washing, OrderStatus::Washed, OrderStatus::Delivering, OrderStatus::Delivered] as $status) {
            $order = $service->updateStatus($order, $status->value);
        }

        $this->assertSame(OrderStatus::Paid->value, $order->fresh()->TrangThai);
    }

    public function test_points_discount_never_exceeds_the_value_of_points_redeemed(): void
    {
        $amounts = app(OrderService::class)->calculateAmounts(5000, null, 1000, 1000);

        $this->assertSame(1000, $amounts['DiemSuDung']);
        $this->assertSame(1000.0, $amounts['TienGiamDoDiem']);
        $this->assertSame(4000.0, $amounts['ThanhTien']);
    }

    public function test_order_creation_rolls_back_when_requested_points_exceed_the_balance(): void
    {
        $customer = $this->createCustomer(1, 5);

        try {
            app(OrderService::class)->create([
                'KhachHangID' => $customer->KhachHangID,
                'TrangThai' => OrderStatus::Pending->value,
                'DiemSuDung' => 6,
                'items' => [$this->orderItem()],
            ]);
            $this->fail('Insufficient point balance should reject order creation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('DiemSuDung', $exception->errors());
        }

        $this->assertSame(0, DonHang::query()->count());
        $this->assertSame(5, $customer->fresh()->points());
    }

    public function test_failed_order_update_rolls_back_old_customer_point_refund(): void
    {
        $oldCustomer = $this->createCustomer(1, 2);
        $newCustomer = $this->createCustomer(2, 1);
        $order = $this->createOrder($oldCustomer, 10000, 3);

        try {
            app(OrderService::class)->update($order, [
                'KhachHangID' => $newCustomer->KhachHangID,
                'TrangThai' => OrderStatus::Pending->value,
                'DiemSuDung' => 4,
                'items' => [$this->orderItem()],
            ]);
            $this->fail('An order update must fail when the new customer has insufficient points.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('DiemSuDung', $exception->errors());
        }

        $this->assertSame(2, $oldCustomer->fresh()->points());
        $this->assertSame(1, $newCustomer->fresh()->points());
        $this->assertSame($oldCustomer->KhachHangID, $order->fresh()->KhachHangID);
        $this->assertSame(3, $order->fresh()->DiemSuDung);
    }

    public function test_legacy_booking_reservation_is_not_redeemed_twice_at_inspection(): void
    {
        $customer = $this->createCustomer(1, 400);
        $booking = $this->createBooking($customer);
        $booking->forceFill(['DiemDaTru' => true, 'DiemSuDung' => 100, 'TienGiamDoDiem' => 100])->saveQuietly();
        $this->actingAsBookingEmployee();
        $order = app(BookingService::class)->inspectBookingAndCreateOrder($booking, 1,
            [array_merge($this->orderItem(), ['TinhTrangTruocKhiGiat' => 'Bình thường'])], 100);
        $this->assertSame(400, $customer->fresh()->points());
        $this->assertSame(100, $order->DiemSuDung);
        $this->assertFalse($booking->fresh()->DiemDaTru);
        app(BookingService::class)->inspectBookingAndCreateOrder($booking->fresh(), 1,
            [array_merge($this->orderItem(), ['TinhTrangTruocKhiGiat' => 'Bình thường'])], 100);
        $this->assertSame(400, $customer->fresh()->points());
    }

    public function test_canceling_a_legacy_pending_booking_returns_reserved_points_once(): void
    {
        $customer = $this->createCustomer(1, 400);
        $booking = $this->createBooking($customer);
        $booking->forceFill(['DiemDaTru' => true, 'DiemSuDung' => 100, 'TienGiamDoDiem' => 100])->saveQuietly();
        $service = app(BookingService::class);
        $service->update($booking, ['status' => BookingStatus::Cancelled->value]);
        $service->update($booking->fresh(), ['status' => BookingStatus::Cancelled->value]);
        $this->assertSame(500, $customer->fresh()->points());
        $this->assertFalse($booking->fresh()->DiemDaTru);
    }

    public function test_canceled_order_refunds_points_once_and_deleting_it_does_not_refund_again(): void
    {
        $customer = $this->createCustomer(1, 400);
        $order = $this->createOrder($customer, 10000, 100);
        $order->update(['TrangThai' => OrderStatus::Received->value]);
        $service = app(OrderService::class);
        $service->updateStatus($order, OrderStatus::Cancelled->value, false, 'Khách yêu cầu hủy');
        $service->updateStatus($order->fresh(), OrderStatus::Cancelled->value);
        $this->assertSame(500, $customer->fresh()->points());
        $service->delete($order->fresh());
        $this->assertSame(500, $customer->fresh()->points());
    }

    public function test_inspection_reprices_estimated_booking_details_at_current_effective_price(): void
    {
        $customer = $this->createCustomer(1, 0);
        $booking = $this->createBooking($customer);
        DB::table('BangGia')->insert([
            'BangGiaID' => 2, 'DichVuID' => 1, 'LoaiDoGiatID' => 1, 'DonViTinhID' => 1,
            'DonGia' => 20000, 'NgayApDung' => today()->toDateString(), 'TrangThai' => 'Hoạt động',
        ]);
        $this->actingAsBookingEmployee();
        $order = app(BookingService::class)->inspectBookingAndCreateOrder($booking, 1,
            [array_merge($this->orderItem(), ['TinhTrangTruocKhiGiat' => 'Bình thường'])]);
        $this->assertSame(20000.0, $order->TongTien);
        $this->assertSame(20000.0, $order->chiTietDonHangs()->first()->DonGia);
        $this->assertSame(10000.0, (float) $booking->chiTietBookings()->first()->DonGia);
    }

    public function test_washing_order_cannot_jump_to_delivered_or_move_backwards(): void
    {
        $customer = $this->createCustomer(1, 0);
        $order = $this->createOrder($customer, 10000, 0);
        $order->update(['TrangThai' => OrderStatus::Washing->value]);
        foreach ([OrderStatus::Delivered, OrderStatus::Received, OrderStatus::Cancelled] as $target) {
            try {
                app(OrderService::class)->updateStatus($order, $target->value);
                $this->fail('Invalid transition should be rejected.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('TrangThai', $exception->errors());
            }
        }
        $this->assertSame(OrderStatus::Washing->value, $order->fresh()->TrangThai);
    }

    public function test_unpaid_delivered_order_cannot_be_manually_marked_as_settled(): void
    {
        $customer = $this->createCustomer(1, 0);
        $order = $this->createOrder($customer, 10000, 0);
        $order->update(['TrangThai' => OrderStatus::Delivered->value]);
        $this->expectException(ValidationException::class);
        app(OrderService::class)->updateStatus($order, OrderStatus::Paid->value);
    }

    private function reservedPromotion(Booking $booking, bool $expired = false): void
    {
        DB::table('KhuyenMai')->insert([
            'KhuyenMaiID' => 1, 'MaKhuyenMai' => 'TEST', 'TenKhuyenMai' => 'Test',
            'LoaiKhuyenMai' => 'Tiền mặt', 'GiaTriGiam' => 9900,
            'NgayBatDau' => today()->subDays(10)->toDateString(),
            'NgayKetThuc' => ($expired ? today()->subDay() : today()->addDay())->toDateString(),
            'SoLuongSuDung' => 0, 'TrangThai' => 'Hoạt động',
        ]);
        $booking->forceFill(['KhuyenMaiID' => 1, 'KhuyenMaiDaTru' => true])->saveQuietly();
    }

    public function test_reserved_promotion_is_applied_before_points_and_delivery_fees_are_added_after_discounts(): void
    {
        $customer = $this->createCustomer(1, 500);
        $booking = $this->createBooking($customer);
        $this->reservedPromotion($booking);
        $booking->forceFill(['PickupDeliveryFee' => 1000, 'DeliveryFee' => 1000])->saveQuietly();
        $this->actingAsBookingEmployee();
        $order = app(BookingService::class)->inspectBookingAndCreateOrder($booking, 1,
            [array_merge($this->orderItem(), ['TinhTrangTruocKhiGiat' => 'Bình thường'])], 500);
        $this->assertSame(9900.0, $order->TienGiamKhuyenMai);
        $this->assertSame(100, $order->DiemSuDung);
        $this->assertSame(2000.0, $order->PhiGiaoHang);
        $this->assertSame(2000.0, $order->ThanhTien);
        $this->assertSame(400, $customer->fresh()->points());
    }

    public function test_expired_reserved_promotion_is_released_once_at_inspection(): void
    {
        $customer = $this->createCustomer(1, 0);
        $booking = $this->createBooking($customer);
        $this->reservedPromotion($booking, true);
        $this->actingAsBookingEmployee();
        $service = app(BookingService::class);
        $items = [array_merge($this->orderItem(), ['TinhTrangTruocKhiGiat' => 'Bình thường'])];
        $order = $service->inspectBookingAndCreateOrder($booking, 1, $items);
        $service->inspectBookingAndCreateOrder($booking->fresh(), 1, $items);
        $this->assertSame(0.0, $order->TienGiamKhuyenMai);
        $this->assertEquals(1, DB::table('KhuyenMai')->value('SoLuongSuDung'));
        $this->assertNull($booking->fresh()->getAttribute('KhuyenMaiID'));
    }

    public function test_canceling_a_booking_releases_its_reserved_promotion_once(): void
    {
        $booking = $this->createBooking($this->createCustomer(1, 0));
        $this->reservedPromotion($booking);
        $service = app(BookingService::class);
        $service->update($booking, ['status' => BookingStatus::Cancelled->value]);
        $service->update($booking->fresh(), ['status' => BookingStatus::Cancelled->value]);
        $this->assertEquals(1, DB::table('KhuyenMai')->value('SoLuongSuDung'));
    }

    public function test_updating_an_order_deducts_only_points_effectively_used_after_promotion(): void
    {
        $customer = $this->createCustomer(1, 500);
        $booking = $this->createBooking($customer);
        $this->reservedPromotion($booking);
        $order = $this->createOrder($customer, 10000, 0);
        $updated = app(OrderService::class)->update($order, [
            'KhuyenMaiID' => 1, 'DiemSuDung' => 500, 'items' => [$this->orderItem()],
        ]);
        $this->assertSame(100, $updated->DiemSuDung);
        $this->assertSame(400, $customer->fresh()->points());
    }

    public function test_owner_override_does_not_allow_skipping_washing(): void
    {
        $order = $this->createOrder($this->createCustomer(1, 0), 10000, 0);
        $order->update(['TrangThai' => OrderStatus::Received->value]);
        $this->expectException(ValidationException::class);
        app(OrderService::class)->updateStatus($order, OrderStatus::Delivered->value, true);
    }

    private function createCustomer(int $id, int $points): KhachHang
    {
        $customer = KhachHang::query()->create([
            'KhachHangID' => $id,
            'HoTen' => 'Khách '.$id,
        ]);
        DiemTichLuy::query()->create([
            'KhachHangID' => $id,
            'DiemHienTai' => $points,
            'NgayCapNhat' => now(),
        ]);

        return $customer;
    }

    private function createOrder(KhachHang $customer, float $amount, int $pointsUsed): DonHang
    {
        return DonHang::query()->create([
            'MaDonHang' => 'DH'.str_pad((string) $customer->KhachHangID, 3, '0', STR_PAD_LEFT),
            'KhachHangID' => $customer->KhachHangID,
            'TrangThai' => OrderStatus::Pending->value,
            'TongTien' => $amount,
            'DiemSuDung' => $pointsUsed,
            'TienGiamDoDiem' => $pointsUsed * OrderService::POINT_VALUE,
            'ThanhTien' => $amount - ($pointsUsed * OrderService::POINT_VALUE),
        ]);
    }

    private function createBooking(KhachHang $customer): Booking
    {
        $booking = Booking::query()->create([
            'KhachHangID' => $customer->KhachHangID,
            'HinhThucNhanDo' => 'Tại nhà',
            'DiaChiNhan' => '12 Nguyễn Huệ',
            'HinhThucTraDo' => 'Tại cửa hàng',
            'NgayHen' => '2026-10-05',
            'GioHen' => '14:30',
            'TrangThai' => BookingStatus::Pending->value,
        ]);

        ChiTietBooking::query()->create([
            'BookingID' => $booking->BookingID,
            'DichVuID' => 1,
            'LoaiDoGiatID' => 1,
            'DonViTinhID' => 1,
            'SoLuong' => 1,
            'DonGia' => 10000,
            'ThanhTien' => 10000,
        ]);

        return $booking;
    }

    private function orderItem(): array
    {
        return [
            'DichVuID' => 1,
            'LoaiDoGiatID' => 1,
            'DonViTinhID' => 1,
            'SoLuong' => 1,
        ];
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

    private function createCatalog(): void
    {
        DB::table('DichVu')->insert(['DichVuID' => 1]);
        DB::table('LoaiDoGiat')->insert(['LoaiDoGiatID' => 1]);
        DB::table('NhanVien')->insert(['NhanVienID' => 1]);
        DB::table('DonViTinh')->insert([
            'DonViTinhID' => 1,
            'TenDonViTinh' => 'Cái',
            'KyHieu' => 'Cái',
            'TrangThai' => 'Hoạt động',
        ]);
        DB::table('BangGia')->insert([
            'BangGiaID' => 1,
            'DichVuID' => 1,
            'LoaiDoGiatID' => 1,
            'DonViTinhID' => 1,
            'DonGia' => 10000,
            'NgayApDung' => '2026-01-01',
            'TrangThai' => 'Hoạt động',
        ]);
    }

    private function createSchema(): void
    {
        Schema::create('KhuyenMai', function (Blueprint $table): void {
            $table->increments('KhuyenMaiID');
            $table->string('MaKhuyenMai');
            $table->string('TenKhuyenMai');
            $table->string('LoaiKhuyenMai');
            $table->decimal('GiaTriGiam', 18, 2);
            $table->decimal('GiaTriDonToiThieu', 18, 2)->nullable();
            $table->decimal('MucGiamToiDa', 18, 2)->nullable();
            $table->integer('SoLuongSuDung')->nullable();
            $table->string('DieuKienApDung')->nullable();
            $table->date('NgayBatDau');
            $table->date('NgayKetThuc');
            $table->string('TrangThai');
        });
        Schema::create('KhachHang', function (Blueprint $table): void {
            $table->increments('KhachHangID');
            $table->string('HoTen')->nullable();
        });

        Schema::create('DiemTichLuy', function (Blueprint $table): void {
            $table->increments('DiemTichLuyID');
            $table->unsignedInteger('KhachHangID')->unique();
            $table->integer('DiemHienTai')->default(0);
            $table->dateTime('NgayCapNhat')->useCurrent();
        });

        Schema::create('DichVu', function (Blueprint $table): void {
            $table->increments('DichVuID');
        });

        Schema::create('LoaiDoGiat', function (Blueprint $table): void {
            $table->increments('LoaiDoGiatID');
        });

        Schema::create('NhanVien', function (Blueprint $table): void {
            $table->increments('NhanVienID');
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
            $table->string('GhiChu')->nullable();
            $table->string('TrangThai');
            $table->dateTime('NgayTao')->useCurrent();
            $table->dateTime('NgayCapNhat')->nullable();
            $table->unsignedInteger('NhanVienID')->nullable();
            $table->unsignedInteger('NhanVienXacNhanID')->nullable();
            $table->dateTime('ThoiGianXacNhan')->nullable();
            $table->unsignedInteger('KhuyenMaiID')->nullable();
            $table->boolean('KhuyenMaiDaTru')->default(false);
            $table->decimal('PickupDeliveryFee', 18, 2)->default(0);
            $table->decimal('DeliveryFee', 18, 2)->default(0);
            $table->boolean('DiemDaTru')->default(false);
            $table->integer('DiemSuDung')->default(0);
            $table->decimal('TienGiamDoDiem', 18, 2)->default(0);
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
            $table->string('GhiChu')->nullable();
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
            $table->string('GhiChu')->nullable();
            $table->dateTime('NgayTao')->useCurrent();
            $table->dateTime('NgayCapNhat')->nullable();
        });

        Schema::create('HoaDon', function (Blueprint $table): void {
            $table->increments('HoaDonID');
            $table->unsignedInteger('DonHangID');
            $table->decimal('ThanhTien', 18, 2)->default(0);
            $table->string('TrangThai')->nullable();
        });

        Schema::create('ThanhToan', function (Blueprint $table): void {
            $table->increments('ThanhToanID');
            $table->unsignedInteger('DonHangID');
            $table->decimal('SoTien', 18, 2)->default(0);
            $table->string('TrangThai');
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
            $table->string('GhiChu')->nullable();
            $table->text('TinhTrangTruocKhiGiat')->nullable();
        });

        Schema::create('GiaoNhan', function (Blueprint $table): void {
            $table->increments('GiaoNhanID');
            $table->unsignedInteger('DonHangID');
            $table->unsignedInteger('NhanVienID')->nullable();
            $table->string('LoaiGiaoNhan');
            $table->string('HinhThuc');
            $table->string('DiaChi')->nullable();
            $table->dateTime('ThoiGianDuKien')->nullable();
            $table->dateTime('ThoiGianThucTe')->nullable();
            $table->decimal('PhiGiaoNhan', 18, 2)->default(0);
            $table->string('TrangThai');
            $table->string('GhiChu')->nullable();
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
    }
}
