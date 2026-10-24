<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\DiemTichLuy;
use App\Models\DonHang;
use App\Models\GiaoNhan;
use App\Models\KhachHang;
use App\Models\User;
use App\Services\DeliveryService;
use App\Services\OrderService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class OrderDeliveryProgressTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');

        Schema::create('DonHang', function (Blueprint $table): void {
            $table->increments('DonHangID');
            $table->string('MaDonHang');
            $table->unsignedInteger('BookingID')->nullable();
            $table->unsignedInteger('KhachHangID')->nullable();
            $table->string('TrangThai');
            $table->decimal('ThanhTien', 12, 2)->default(0);
            $table->integer('DiemSuDung')->default(0);
            $table->dateTime('NgayTao')->nullable();
            $table->dateTime('NgayCapNhat')->nullable();
        });
        Schema::create('Booking', function (Blueprint $table): void {
            $table->increments('BookingID');
            $table->string('TrangThai')->default('DaXacNhan');
            $table->string('HinhThucNhanDo')->nullable();
            $table->string('DiaChiNhan')->nullable();
            $table->string('HinhThucTraDo')->nullable();
            $table->string('DiaChiTra')->nullable();
            $table->date('NgayHen')->nullable();
            $table->time('GioHen')->nullable();
        });
        Schema::create('GiaoNhan', function (Blueprint $table): void {
            $table->increments('GiaoNhanID');
            $table->unsignedInteger('DonHangID');
            $table->unsignedInteger('NhanVienID')->nullable();
            $table->string('LoaiGiaoNhan');
            $table->string('HinhThuc')->nullable();
            $table->string('DiaChi')->nullable();
            $table->dateTime('ThoiGianDuKien')->nullable();
            $table->dateTime('ThoiGianThucTe')->nullable();
            $table->decimal('PhiGiaoNhan', 12, 2)->default(0);
            $table->string('TrangThai')->default('Chờ thực hiện');
            $table->string('GhiChu')->nullable();
        });
        Schema::create('NhanVien', function (Blueprint $table): void {
            $table->increments('NhanVienID');
            $table->string('TrangThai');
        });
        Schema::create('HoaDon', function (Blueprint $table): void {
            $table->increments('HoaDonID');
            $table->unsignedInteger('DonHangID');
            $table->decimal('ThanhTien', 12, 2)->default(0);
        });
        Schema::create('ThanhToan', function (Blueprint $table): void {
            $table->increments('ThanhToanID');
            $table->unsignedInteger('DonHangID');
            $table->decimal('SoTien', 12, 2)->default(0);
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
            $table->dateTime('NgayCapNhat')->nullable();
        });
        Schema::create('NhatKyHeThong', function (Blueprint $table): void {
            $table->increments('NhatKyID');
            $table->unsignedInteger('TaiKhoanID')->nullable();
            $table->string('HanhDong');
            $table->string('BangDuLieu');
            $table->unsignedInteger('BanGhiID');
            $table->json('DuLieuCu')->nullable();
            $table->json('DuLieuMoi')->nullable();
            $table->string('LyDo')->nullable();
            $table->dateTime('ThoiGian')->nullable();
            $table->string('IPAddress')->nullable();
            $table->text('UserAgent')->nullable();
        });
    }

    protected function tearDown(): void
    {
        foreach (['NhatKyHeThong', 'DiemTichLuy', 'KhachHang', 'ThanhToan', 'HoaDon', 'GiaoNhan', 'NhanVien', 'Booking', 'DonHang'] as $table) {
            Schema::dropIfExists($table);
        }

        parent::tearDown();
    }

    public function test_order_cannot_enter_delivering_through_direct_order_status_service(): void
    {
        $order = $this->createOrder(1);
        DB::table('GiaoNhan')->insert([
            'DonHangID' => 1,
            'LoaiGiaoNhan' => 'GIAO_DO',
            'TrangThai' => 'Chờ thực hiện',
        ]);

        $this->expectException(ValidationException::class);
        app(OrderService::class)->updateStatus($order, OrderStatus::Delivering->value);
    }

    public function test_store_return_order_cannot_enter_delivering_even_through_direct_route(): void
    {
        $this->createOrder(2);
        $this->actingAs($this->userWithStatusPermission());

        $this->patchJson(route('staff.dashboard.update-order-status', 2), [
            'status' => OrderStatus::Delivering->value,
        ])->assertStatus(400);

        $this->assertSame(OrderStatus::Washed->value, DB::table('DonHang')->where('DonHangID', 2)->value('TrangThai'));
    }

    public function test_store_return_order_can_be_completed_without_delivery_leg(): void
    {
        $order = $this->createOrder(3);

        $updated = app(OrderService::class)->updateStatus($order, OrderStatus::Delivered->value);

        $this->assertSame(OrderStatus::Delivered->value, $updated->TrangThai);
    }

    public function test_staff_dashboard_status_route_returns_a_status_that_remains_in_its_order_list(): void
    {
        $this->createOrder(9);
        DB::table('DonHang')->where('DonHangID', 9)->update(['TrangThai' => OrderStatus::Received->value]);
        $this->actingAs($this->userWithStatusPermission());

        $this->patchJson(route('staff.dashboard.update-order-status', 9), [
            'status' => OrderStatus::Washing->value,
        ])->assertOk()->assertJson([
            'success' => true,
            'status' => OrderStatus::Washing->value,
            'status_label' => OrderStatus::Washing->label(),
        ]);

        $this->assertSame(OrderStatus::Washing->value, DB::table('DonHang')->where('DonHangID', 9)->value('TrangThai'));
    }

    public function test_staff_dashboard_status_route_returns_a_status_that_removes_a_store_pickup_order_from_its_list(): void
    {
        $this->createOrder(10);
        $this->actingAs($this->userWithStatusPermission());

        $this->patchJson(route('staff.dashboard.update-order-status', 10), [
            'status' => OrderStatus::Delivered->value,
        ])->assertOk()->assertJson([
            'success' => true,
            'status' => OrderStatus::Delivered->value,
            'status_label' => OrderStatus::Delivered->label(),
        ]);

        $this->assertSame(OrderStatus::Delivered->value, DB::table('DonHang')->where('DonHangID', 10)->value('TrangThai'));
    }

    public function test_booking_for_home_return_requires_delivery_even_without_delivery_leg(): void
    {
        DB::table('Booking')->insert(['BookingID' => 1, 'HinhThucTraDo' => 'Tại nhà']);
        $order = $this->createOrder(4, 1);

        $this->expectException(ValidationException::class);
        app(OrderService::class)->updateStatus($order, OrderStatus::Delivered->value);
    }

    public function test_booking_store_return_cannot_be_marked_delivered_through_direct_status_route(): void
    {
        DB::table('Booking')->insert([
            'BookingID' => 1,
            'TrangThai' => 'DaXacNhan',
            'HinhThucTraDo' => 'Tại cửa hàng',
        ]);
        $this->createOrder(11, 1);
        $this->actingAs($this->userWithStatusPermission());

        $this->patchJson(route('staff.dashboard.update-order-status', 11), [
            'status' => OrderStatus::Delivered->value,
        ])->assertStatus(422);

        $this->assertSame(OrderStatus::Washed->value, DB::table('DonHang')->where('DonHangID', 11)->value('TrangThai'));
        $this->assertSame(0, GiaoNhan::where('DonHangID', 11)->count());
    }

    public function test_home_return_cannot_be_marked_delivered_through_staff_status_route(): void
    {
        DB::table('Booking')->insert(['BookingID' => 1, 'HinhThucTraDo' => 'Tại nhà']);
        $this->createOrder(7, 1);
        $this->actingAs($this->userWithStatusPermission());

        $this->patchJson(route('staff.dashboard.update-order-status', 7), [
            'status' => OrderStatus::Delivered->value,
        ])->assertStatus(400);

        $this->assertSame(OrderStatus::Washed->value, DB::table('DonHang')->where('DonHangID', 7)->value('TrangThai'));
    }

    public function test_progressing_paid_home_delivery_order_preserves_payment_status(): void
    {
        $order = $this->createOrder(5);
        DB::table('NhanVien')->insert(['NhanVienID' => 1, 'TrangThai' => 'Hoạt động']);
        DB::table('GiaoNhan')->insert([
            'DonHangID' => 5,
            'NhanVienID' => 1,
            'LoaiGiaoNhan' => 'GIAO_DO',
            'HinhThuc' => 'Tại nhà',
            'DiaChi' => '12 Nguyễn Huệ',
            'ThoiGianDuKien' => now()->addDay(),
            'TrangThai' => 'Chờ thực hiện',
        ]);
        DB::table('ThanhToan')->insert([
            'DonHangID' => 5,
            'SoTien' => 25000,
            'TrangThai' => PaymentStatus::Paid->value,
        ]);

        $delivery = GiaoNhan::findOrFail(1);
        $updated = app(DeliveryService::class)->update($delivery, [
            'status' => 'delivering',
            'pickup_date' => now()->addDay()->format('Y-m-d'),
            'pickup_time' => now()->addDay()->format('H:i'),
        ]);

        $this->assertSame('Đang thực hiện', $updated->TrangThai);
        $this->assertSame(OrderStatus::Delivering->value, DB::table('DonHang')->where('DonHangID', 5)->value('TrangThai'));
        $this->assertSame(PaymentStatus::Paid->value, DB::table('ThanhToan')->where('DonHangID', 5)->value('TrangThai'));
    }

    public function test_legacy_paid_order_can_progress_home_delivery_without_changing_payment_status(): void
    {
        $this->createOrder(8);
        DB::table('KhachHang')->insert(['KhachHangID' => 1, 'HoTen' => 'Khách test']);
        DB::table('DonHang')->where('DonHangID', 8)->update([
            'TrangThai' => OrderStatus::Paid->value,
            'KhachHangID' => 1,
        ]);
        DB::table('NhanVien')->insert(['NhanVienID' => 1, 'TrangThai' => 'Hoạt động']);
        DB::table('GiaoNhan')->insert([
            'DonHangID' => 8,
            'NhanVienID' => 1,
            'LoaiGiaoNhan' => 'GIAO_DO',
            'HinhThuc' => 'Tại nhà',
            'DiaChi' => '12 Nguyễn Huệ',
            'ThoiGianDuKien' => now()->addDay(),
            'TrangThai' => 'Chờ thực hiện',
        ]);
        DB::table('ThanhToan')->insert([
            'DonHangID' => 8,
            'SoTien' => 25000,
            'TrangThai' => PaymentStatus::Paid->value,
        ]);

        $service = app(DeliveryService::class);
        $delivery = GiaoNhan::findOrFail(1);

        $delivery = $service->advanceProgress($delivery);
        $this->assertSame('Đang thực hiện', $delivery->TrangThai);
        $this->assertSame(OrderStatus::Delivering->value, DB::table('DonHang')->where('DonHangID', 8)->value('TrangThai'));
        $this->assertSame(PaymentStatus::Paid->value, DB::table('ThanhToan')->where('DonHangID', 8)->value('TrangThai'));

        $delivery = $service->advanceProgress($delivery);
        $this->assertSame('Hoàn thành', $delivery->TrangThai);
        $this->assertSame(OrderStatus::Delivered->value, DB::table('DonHang')->where('DonHangID', 8)->value('TrangThai'));
        $this->assertSame(PaymentStatus::Paid->value, DB::table('ThanhToan')->where('DonHangID', 8)->value('TrangThai'));
        $this->assertSame(2500, DB::table('DiemTichLuy')->where('KhachHangID', 1)->value('DiemHienTai'));
    }

    public function test_order_detail_header_restores_delivered_badge_for_legacy_paid_order_without_history(): void
    {
        $order = new DonHang;
        $order->setRawAttributes([
            'DonHangID' => 6,
            'TrangThai' => OrderStatus::Paid->value,
        ]);

        $html = view('components.admin.order-status-badges', [
            'order' => $order,
            'isPaid' => true,
            'isReceiving' => false,
        ])->render();

        $this->assertSame(1, substr_count($html, 'Đã thanh toán'));
        $this->assertSame(1, substr_count($html, 'Đã giao'));
        $this->assertSame(OrderStatus::Delivered, $order->operationalStatusEnum());

        $order->setAttribute('TrangThai', OrderStatus::Washing->value);
        $html = view('components.admin.order-status-badges', [
            'order' => $order,
            'isPaid' => true,
            'isReceiving' => false,
        ])->render();

        $this->assertSame(1, substr_count($html, 'Đã thanh toán'));
        $this->assertSame(1, substr_count($html, 'Đang giặt'));
    }

    public function test_order_status_badges_show_last_operational_status_for_legacy_paid_order(): void
    {
        $this->createOrder(9);
        DB::table('DonHang')->where('DonHangID', 9)->update([
            'TrangThai' => OrderStatus::Paid->value,
        ]);
        DB::table('NhatKyHeThong')->insert([
            'HanhDong' => 'Thay đổi trạng thái đơn hàng',
            'BangDuLieu' => 'DonHang',
            'BanGhiID' => 9,
            'DuLieuCu' => json_encode(['TrangThai' => OrderStatus::Delivered->value]),
            'DuLieuMoi' => json_encode(['TrangThai' => OrderStatus::Paid->value]),
            'ThoiGian' => now(),
        ]);

        $html = view('components.admin.order-status-badges', [
            'order' => DonHang::findOrFail(9),
            'isPaid' => true,
            'isReceiving' => false,
        ])->render();

        $this->assertStringContainsString('Đã giao', $html);
        $this->assertStringContainsString('Đã thanh toán', $html);
    }

    public function test_customer_order_history_shows_operational_and_paid_badges_for_paid_order(): void
    {
        $this->createOrder(10);
        DB::table('DonHang')->where('DonHangID', 10)->update([
            'TrangThai' => OrderStatus::Paid->value,
        ]);
        DB::table('NhatKyHeThong')->insert([
            'HanhDong' => 'Thay đổi trạng thái đơn hàng',
            'BangDuLieu' => 'DonHang',
            'BanGhiID' => 10,
            'DuLieuCu' => json_encode(['TrangThai' => OrderStatus::Delivered->value]),
            'DuLieuMoi' => json_encode(['TrangThai' => OrderStatus::Paid->value]),
            'ThoiGian' => now(),
        ]);
        $order = DonHang::findOrFail(10);
        $order->setRelation('statusChanges', $order->statusChanges()->get());
        $order->setRelation('chiTietDonHangs', collect());

        $customer = (new KhachHang)->forceFill([
            'KhachHangID' => 1,
            'HoTen' => 'Khách kiểm thử',
            'TrangThai' => 'Hoạt động',
        ]);
        $customer->setRelation('taiKhoan', null);
        $customer->setRelation('diemTichLuy', (new DiemTichLuy)->forceFill([
            'DiemHienTai' => 0,
        ]));
        $orders = new LengthAwarePaginator([$order], 1, 10);

        $html = view('admin.customers.show', [
            'customer' => $customer,
            'orders' => $orders,
            'totalSpent' => 0,
            'orderCount' => 1,
        ])->render();

        $this->assertStringContainsString('Đã giao', $html);
        $this->assertStringContainsString('Đã thanh toán', $html);
        $this->assertStringContainsString('rounded-pill', $html);
        $this->assertStringContainsString('Đang hoạt động', $html);
    }

    public function test_order_status_badges_use_distinct_styles_for_each_operational_state(): void
    {
        $expectedClasses = [
            OrderStatus::Pending->value => 'bg-secondary-subtle text-secondary-emphasis border border-secondary',
            OrderStatus::Received->value => 'bg-primary-subtle text-primary-emphasis border border-primary',
            OrderStatus::Washing->value => 'bg-purple-subtle text-purple-emphasis border border-purple',
            OrderStatus::Washed->value => 'bg-teal-subtle text-teal-emphasis border border-teal',
            OrderStatus::Delivering->value => 'bg-warning-subtle text-warning-emphasis border border-warning',
            OrderStatus::Delivered->value => 'bg-success-subtle text-success-emphasis border border-success',
            OrderStatus::Paid->value => 'bg-info-subtle text-info-emphasis border border-info',
            OrderStatus::Cancelled->value => 'bg-danger-subtle text-danger-emphasis border border-danger',
        ];

        foreach ($expectedClasses as $status => $expectedClass) {
            $this->assertSame($expectedClass, OrderStatus::badgeClassFor($status));
        }
        $this->assertCount(count(OrderStatus::cases()), array_unique(array_values($expectedClasses)));
    }

    private function createOrder(int $id, ?int $bookingId = null): DonHang
    {
        DB::table('DonHang')->insert([
            'DonHangID' => $id,
            'MaDonHang' => 'DH'.str_pad((string) $id, 4, '0', STR_PAD_LEFT),
            'BookingID' => $bookingId,
            'TrangThai' => OrderStatus::Washed->value,
            'ThanhTien' => 25000,
        ]);

        return DonHang::findOrFail($id);
    }

    private function userWithStatusPermission(): User&MockInterface
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('isActive')->andReturn(true);
        $user->shouldReceive('isCustomer')->andReturn(false);
        $user->shouldReceive('canPermission')
            ->andReturnUsing(fn (string $permission): bool => $permission === 'orders.update_status');

        return $user;
    }
}
