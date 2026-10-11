<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\SettledOrderException;
use App\Http\Controllers\Admin\GiaoNhanController;
use App\Models\DonHang;
use App\Models\GiaoNhan;
use App\Models\User;
use App\Services\DeliveryService;
use App\Services\OrderService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class DeliveryContractTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 7)->startOfDay());
        Schema::create('DonHang', function (Blueprint $table): void {
            $table->increments('DonHangID');
            $table->string('MaDonHang')->default('DH-test');
            $table->unsignedInteger('BookingID')->nullable();
            $table->unsignedInteger('KhachHangID')->nullable();
            $table->string('TrangThai')->default(OrderStatus::Washed->value);
            $table->dateTime('NgayTao')->nullable();
            $table->dateTime('NgayCapNhat')->nullable();
        });
        Schema::create('Booking', function (Blueprint $table): void {
            $table->increments('BookingID');
            $table->string('HinhThucNhanDo')->nullable();
            $table->string('DiaChiNhan')->nullable();
            $table->string('HinhThucTraDo')->default('Tại nhà');
            $table->string('DiaChiTra')->nullable();
            $table->date('NgayHen')->nullable();
            $table->time('GioHen')->nullable();
        });
        Schema::create('KhachHang', function (Blueprint $table): void {
            $table->increments('KhachHangID');
            $table->string('HoTen')->nullable();
            $table->string('SoDienThoai')->nullable();
        });
        Schema::create('NhanVien', function (Blueprint $table): void {
            $table->increments('NhanVienID');
            $table->string('HoTen')->nullable();
            $table->string('TrangThai')->default('Hoạt động');
        });
        Schema::create('GiaoNhan', function (Blueprint $table): void {
            $table->increments('GiaoNhanID');
            $table->unsignedInteger('DonHangID');
            $table->unsignedInteger('NhanVienID')->nullable();
            $table->string('LoaiGiaoNhan');
            $table->string('HinhThuc');
            $table->string('DiaChi')->nullable();
            $table->dateTime('ThoiGianDuKien')->nullable();
            $table->string('TrangThai');
            $table->string('GhiChu')->nullable();
        });
        Schema::create('ThanhToan', function (Blueprint $table): void {
            $table->increments('ThanhToanID');
            $table->unsignedInteger('DonHangID');
            $table->string('TrangThai');
            $table->decimal('SoTien', 12, 2)->default(0);
        });
        Schema::create('HoaDon', function (Blueprint $table): void {
            $table->increments('HoaDonID');
            $table->unsignedInteger('DonHangID');
            $table->decimal('ThanhTien', 12, 2)->default(0);
            $table->string('TrangThai')->nullable();
        });
        Schema::create('NhatKyHeThong', function (Blueprint $table): void {
            $table->increments('NhatKyID');
            $table->unsignedInteger('TaiKhoanID')->nullable();
            $table->string('HanhDong');
            $table->string('BangDuLieu');
            $table->unsignedInteger('BanGhiID');
            $table->text('DuLieuCu')->nullable();
            $table->text('DuLieuMoi')->nullable();
            $table->dateTime('ThoiGian');
            $table->string('IPAddress')->nullable();
            $table->text('UserAgent')->nullable();
        });
        DB::table('Booking')->insert(['BookingID' => 1, 'HinhThucTraDo' => 'Tại nhà', 'DiaChiTra' => '12 Nguyễn Huệ']);
        DB::table('DonHang')->insert(['DonHangID' => 1, 'BookingID' => 1]);
        DB::table('NhanVien')->insert([['NhanVienID' => 1, 'TrangThai' => 'Hoạt động'], ['NhanVienID' => 2, 'TrangThai' => 'Khóa']]);
    }

    protected function tearDown(): void
    {
        foreach (['NhatKyHeThong', 'HoaDon', 'ThanhToan', 'GiaoNhan', 'NhanVien', 'KhachHang', 'Booking', 'DonHang'] as $table) {
            Schema::dropIfExists($table);
        }
        $this->travelBack();
        parent::tearDown();
    }

    private function data(array $overrides = []): array
    {
        return array_merge(['order_id' => 1, 'employee_id' => 1, 'method' => 'giao_do', 'fulfillment' => 'Tại nhà', 'address' => '12 Nguyễn Huệ'], $overrides);
    }

    private function reject(callable $operation, string $field): void
    {
        try {
            $operation();
            $this->fail('Invalid mutation accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
    }

    public function test_pending_return_can_be_unscheduled_and_notes_edit_preserves_schedule_and_status(): void
    {
        $service = app(DeliveryService::class);
        $delivery = $service->create($this->data());
        $this->assertNull($delivery->ThoiGianDuKien);
        $delivery = $service->update($delivery, ['notes' => 'Chưa chốt lịch']);
        $this->assertNull($delivery->ThoiGianDuKien);
        $this->assertSame('Chờ thực hiện', $delivery->TrangThai);
        $this->assertSame('Chưa chốt lịch', $delivery->GhiChu);
        $before = $delivery->getAttributes();
        $delivery = $service->update($delivery, ['notes' => 'Updated notes only']);
        unset($before['GhiChu']);
        $after = $delivery->getAttributes();
        unset($after['GhiChu']);
        $this->assertSame($before, $after, '77: omitted fields must be preserved');
    }

    public function test_54_stale_model_rejects_changed_parent_for_update_and_delete(): void
    {
        $service = app(DeliveryService::class);
        $delivery = $service->create($this->data());
        DB::table('DonHang')->insert(['DonHangID' => 2]);
        DB::table('GiaoNhan')->where('GiaoNhanID', $delivery->getKey())->update(['DonHangID' => 2]);
        $this->reject(fn () => $service->update($delivery, ['notes' => 'Stale edit']), 'order_id');
        $this->reject(fn () => $service->delete($delivery), 'delivery');
        $this->assertSame(2, $delivery->fresh()->DonHangID);
        $this->assertNull($delivery->fresh()->GhiChu);
    }

    public static function malformedServicePayloads(): array
    {
        return [
            [['method' => 'other'], 'method'], [['status' => 'unknown'], 'status'],
            [['address' => ''], 'address'], [['address' => str_repeat('a', 256)], 'address'],
            [['pickup_date' => 'bad', 'pickup_time' => '10:00'], 'pickup_date'],
            [['pickup_date' => '2026-10-08', 'pickup_time' => '25:90'], 'pickup_time'],
            [['employee_id' => 999], 'employee_id'], [['employee_id' => 'abc'], 'employee_id'],
            [['employee_id' => 2], 'employee_id'], [['fulfillment' => 'other'], 'fulfillment'],
        ];
    }

    #[DataProvider('malformedServicePayloads')]
    public function test_55_direct_service_rejects_malformed_payload(array $changes, string $field): void
    {
        $this->reject(fn () => app(DeliveryService::class)->create($this->data($changes)), $field);
        $this->assertSame(0, GiaoNhan::count());
    }

    public function test_84_86_87_delivery_assignment_request_and_service_matrix(): void
    {
        foreach (['Hoạt động', 'Ngừng hoạt động', 'Khóa'] as $status) {
            DB::table('NhanVien')->where('NhanVienID', 1)->update(['TrangThai' => $status]);
            $response = $this->withoutMiddleware()->post(route('deliveries.store'), $this->data());
            if ($status === 'Hoạt động') {
                $response->assertRedirect(route('deliveries.show', 1))
                    ->assertSessionHasNoErrors()
                    ->assertSessionMissing('error');
                $this->assertSame(1, GiaoNhan::count());
                GiaoNhan::query()->delete();
            } else {
                $response->assertSessionHasErrors('employee_id');
                $this->reject(fn () => app(DeliveryService::class)->create($this->data()), 'employee_id');
            }
            $this->assertSame(0, GiaoNhan::count());
        }
        foreach ([999, 'abc'] as $id) {
            $this->withoutMiddleware()->post(route('deliveries.store'), $this->data(['employee_id' => $id]))->assertSessionHasErrors('employee_id');
        }
    }

    public function test_88_delivery_can_remain_unassigned(): void
    {
        foreach ([null, ''] as $employeeId) {
            $this->withoutMiddleware()->post(route('deliveries.store'), $this->data(['employee_id' => $employeeId]))->assertSessionHasNoErrors();
            $this->assertNull(GiaoNhan::first()->NhanVienID);
            GiaoNhan::query()->delete();
            $delivery = app(DeliveryService::class)->create($this->data(['employee_id' => $employeeId]));
            $this->assertNull($delivery->NhanVienID);
            GiaoNhan::query()->delete();
        }
    }

    public function test_request_accepts_unscheduled_return_and_rejects_incomplete_date_pair(): void
    {
        $this->withoutMiddleware()->post(route('deliveries.store'), $this->data())
            ->assertRedirect(route('deliveries.show', 1))
            ->assertSessionHasNoErrors()
            ->assertSessionMissing('error');
        $this->assertSame(1, GiaoNhan::count());
        $this->withoutMiddleware()->put(route('deliveries.update', 1), $this->data(['pickup_date' => '2026-10-08']))
            ->assertSessionHasErrors('pickup_time');
        $this->assertNull(GiaoNhan::first()->ThoiGianDuKien);
    }

    public function test_two_different_legs_allowed_duplicate_rejected_and_cancelled_leg_can_be_replaced(): void
    {
        $service = app(DeliveryService::class);
        $return = $service->create($this->data());
        $service->create($this->data(['method' => 'nhan_do', 'pickup_date' => '2026-10-08', 'pickup_time' => '10:00']));
        $this->reject(fn () => $service->create($this->data()), 'method');
        $service->update($return, ['status' => 'cancelled']);
        $service->create($this->data());
        $this->assertSame(3, GiaoNhan::count());
    }

    public function test_http_duplicate_returns_field_error(): void
    {
        app(DeliveryService::class)->create($this->data());
        $this->withSession([])->withoutMiddleware()->post(route('deliveries.store'), $this->data())->assertSessionHasErrors('method');
        $this->assertSame(1, GiaoNhan::count());
    }

    public function test_home_return_assignment_screen_does_not_change_order_or_create_a_slip(): void
    {
        $order = DonHang::findOrFail(1);
        $response = app(GiaoNhanController::class)->assignForOrder(
            $this->requestWithPermissions(['deliveries.create']),
            $order,
        );

        $this->assertInstanceOf(View::class, $response);
        $this->assertSame('admin.deliveries.create', $response->name());
        $this->assertSame(1, $response->getData()['selectedOrderId']);
        $this->assertSame('giao_do', $response->getData()['selectedMethod']);
        $this->assertSame(OrderStatus::Washed->value, $order->fresh()->TrangThai);
        $this->assertSame(0, GiaoNhan::count());
    }

    public function test_existing_return_slip_is_reused_by_assignment_screen(): void
    {
        $delivery = app(DeliveryService::class)->create($this->data());
        $response = app(GiaoNhanController::class)->assignForOrder(
            $this->requestWithPermissions(['deliveries.edit']),
            DonHang::findOrFail(1),
        );

        $this->assertSame(route('deliveries.edit', $delivery), $response->getTargetUrl());
        $this->assertSame(1, GiaoNhan::count());
        $this->assertSame(OrderStatus::Washed->value, DB::table('DonHang')->where('DonHangID', 1)->value('TrangThai'));
    }

    public function test_assignment_screen_requires_create_permission_when_no_return_slip_exists(): void
    {
        try {
            app(GiaoNhanController::class)->assignForOrder(
                $this->requestWithPermissions(['deliveries.edit']),
                DonHang::findOrFail(1),
            );
            $this->fail('A user without delivery-create permission was allowed to open the assignment screen.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_assignment_screen_requires_edit_permission_for_an_existing_return_slip(): void
    {
        app(DeliveryService::class)->create($this->data());

        try {
            app(GiaoNhanController::class)->assignForOrder(
                $this->requestWithPermissions(['deliveries.create']),
                DonHang::findOrFail(1),
            );
            $this->fail('A user without delivery-edit permission was allowed to edit an active delivery.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_store_return_cannot_create_a_home_delivery_leg(): void
    {
        DB::table('Booking')->where('BookingID', 1)->update(['HinhThucTraDo' => 'Tại cửa hàng']);

        $this->reject(fn () => app(DeliveryService::class)->create($this->data()), 'method');

        $this->assertSame(0, GiaoNhan::count());
    }

    public function test_return_leg_cannot_start_without_an_active_assignee(): void
    {
        $delivery = app(DeliveryService::class)->create($this->data(['employee_id' => null]));
        $this->reject(fn () => app(DeliveryService::class)->update($delivery, [
            'status' => 'delivering',
            'pickup_date' => '2026-10-08',
            'pickup_time' => '10:00',
        ]), 'employee_id');

        $this->assertSame(OrderStatus::Washed->value, DB::table('DonHang')->where('DonHangID', 1)->value('TrangThai'));
        $this->assertSame('Chờ thực hiện', $delivery->fresh()->TrangThai);
    }

    public function test_delivery_start_and_completion_sync_order_atomically_without_changing_payment(): void
    {
        DB::table('NhanVien')->insert(['NhanVienID' => 3, 'TrangThai' => 'Hoạt động']);
        $delivery = app(DeliveryService::class)->create($this->data(['employee_id' => 3]));
        DB::table('ThanhToan')->insert([
            'DonHangID' => 1,
            'TrangThai' => PaymentStatus::Paid->value,
            'SoTien' => 10000,
        ]);

        $service = app(DeliveryService::class);
        $delivery = $service->update($delivery, [
            'status' => 'delivering',
            'pickup_date' => '2026-10-08',
            'pickup_time' => '10:00',
        ]);
        $this->assertSame('Đang thực hiện', $delivery->TrangThai);
        $this->assertSame(OrderStatus::Delivering->value, DB::table('DonHang')->where('DonHangID', 1)->value('TrangThai'));

        $delivery = $service->update($delivery, ['status' => 'completed']);
        $this->assertSame('Hoàn thành', $delivery->TrangThai);
        $this->assertSame(OrderStatus::Delivered->value, DB::table('DonHang')->where('DonHangID', 1)->value('TrangThai'));
        $this->assertSame(PaymentStatus::Paid->value, DB::table('ThanhToan')->where('DonHangID', 1)->value('TrangThai'));
        $this->assertSame(1, DB::table('ThanhToan')->where('DonHangID', 1)->count());
    }

    public function test_technical_failure_rolls_back_delivery_and_order_together(): void
    {
        $delivery = app(DeliveryService::class)->create($this->data());
        $orderService = Mockery::mock(OrderService::class);
        $orderService->shouldReceive('syncStatusFromDelivery')
            ->once()
            ->andThrow(new RuntimeException('Simulated sync failure.'));
        $service = new DeliveryService($orderService);

        try {
            $service->update($delivery, [
                'status' => 'delivering',
                'pickup_date' => '2026-10-08',
                'pickup_time' => '10:00',
            ]);
            $this->fail('Expected the transactional sync to fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated sync failure.', $exception->getMessage());
        }

        $this->assertSame(OrderStatus::Washed->value, DB::table('DonHang')->where('DonHangID', 1)->value('TrangThai'));
        $this->assertSame('Chờ thực hiện', $delivery->fresh()->TrangThai);
    }

    public function test_failed_attempt_is_preserved_and_allows_a_new_return_attempt(): void
    {
        $service = app(DeliveryService::class);
        $delivery = $service->create($this->data());
        $delivery = $service->update($delivery, [
            'status' => 'delivering',
            'pickup_date' => '2026-10-08',
            'pickup_time' => '10:00',
        ]);
        $delivery = $service->update($delivery, ['status' => 'cancelled']);

        $this->assertSame('Đã hủy', $delivery->TrangThai);
        $this->assertSame(OrderStatus::Delivering->value, DB::table('DonHang')->where('DonHangID', 1)->value('TrangThai'));
        $this->assertSame(1, GiaoNhan::count());

        $retry = $service->create($this->data());

        $this->assertNotSame($delivery->getKey(), $retry->getKey());
        $this->assertSame(2, GiaoNhan::count());
        $this->assertSame('Đã hủy', $delivery->fresh()->TrangThai);
        $this->assertSame('Chờ thực hiện', $retry->TrangThai);
    }

    private function requestWithPermissions(array $permissions): Request
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('canPermission')
            ->andReturnUsing(fn (string $permission): bool => in_array($permission, $permissions, true));

        $request = Request::create('/orders/1/delivery-assignment');
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    #[DataProvider('immutableOrders')]
    public function test_all_mutations_reject_cancelled_or_paid_parent(string $status): void
    {
        $service = app(DeliveryService::class);
        $delivery = $service->create($this->data());
        DB::table('DonHang')->where('DonHangID', 1)->update(['TrangThai' => $status]);
        foreach ([fn () => $service->create($this->data(['method' => 'nhan_do'])), fn () => $service->update($delivery, ['notes' => 'Changed']), fn () => $service->delete($delivery)] as $operation) {
            try {
                $operation();
                $this->fail('Immutable order accepted a mutation.');
            } catch (ValidationException|SettledOrderException $exception) {
                $this->assertNotEmpty($exception->getMessage());
            }
        }
        $this->assertSame(1, GiaoNhan::count());
        $this->assertNull($delivery->fresh()->GhiChu);
    }

    public static function immutableOrders(): array
    {
        return [[OrderStatus::Cancelled->value]];
    }

    public function test_return_execution_waits_for_washing_and_requires_schedule(): void
    {
        $service = app(DeliveryService::class);
        $delivery = $service->create($this->data());
        DB::table('DonHang')->where('DonHangID', 1)->update(['TrangThai' => OrderStatus::Received->value]);
        $this->reject(fn () => $service->update($delivery, ['status' => 'delivering']), 'pickup_date');
        $scheduled = ['pickup_date' => '2026-10-08', 'pickup_time' => '10:00'];
        $this->reject(fn () => $service->update($delivery, $scheduled + ['status' => 'delivering']), 'status');
        DB::table('DonHang')->where('DonHangID', 1)->update(['TrangThai' => OrderStatus::Washed->value]);
        $delivery = $service->update($delivery, $scheduled + ['status' => 'delivering']);
        $this->assertSame('Đang thực hiện', $delivery->TrangThai);
        $delivery = $service->update($delivery, ['notes' => 'Đang giao']);
        $this->assertSame('Đang thực hiện', $delivery->TrangThai);
        $delivery = $service->update($delivery, ['status' => 'completed']);
        $this->reject(fn () => $service->update($delivery, ['status' => 'pending']), 'status');
        $this->reject(fn () => $service->delete($delivery), 'delivery');
        $this->reject(fn () => $service->create($this->data()), 'order_id');
    }

    public function test_pickup_execution_preserves_type_when_editing_notes_and_cannot_go_back(): void
    {
        DB::table('DonHang')->where('DonHangID', 1)->update(['TrangThai' => OrderStatus::Received->value]);
        $service = app(DeliveryService::class);
        $delivery = $service->create($this->data(['method' => 'nhan_do', 'status' => 'picking', 'pickup_date' => '2026-10-08', 'pickup_time' => '10:00']));
        $delivery = $service->update($delivery, ['notes' => 'Đang nhận đồ']);
        $this->assertSame('Đang thực hiện', $delivery->TrangThai);
        $this->reject(fn () => $service->update($delivery, ['status' => 'pending']), 'status');
        $this->reject(fn () => $service->update($delivery, ['status' => 'delivering']), 'status');
    }

    public function test_pickup_requires_schedule_and_cannot_execute_after_washing_starts(): void
    {
        $service = app(DeliveryService::class);
        $this->reject(fn () => $service->create($this->data(['method' => 'nhan_do'])), 'pickup_date');
        DB::table('DonHang')->where('DonHangID', 1)->update(['TrangThai' => OrderStatus::Washing->value]);
        $this->reject(fn () => $service->create($this->data(['method' => 'nhan_do', 'status' => 'picking', 'pickup_date' => '2026-10-08', 'pickup_time' => '10:00'])), 'status');
        $this->assertSame(0, GiaoNhan::count());
    }

    public function test_existing_slip_cannot_change_order_or_leg(): void
    {
        $service = app(DeliveryService::class);
        $delivery = $service->create($this->data());
        DB::table('DonHang')->insert(['DonHangID' => 2]);
        $this->reject(fn () => $service->update($delivery, ['order_id' => 2]), 'order_id');
        $this->reject(fn () => $service->update($delivery, ['method' => 'nhan_do', 'pickup_date' => '2026-10-08', 'pickup_time' => '10:00']), 'order_id');
        $this->assertSame(1, $delivery->fresh()->DonHangID);
    }

    public function test_active_assignment_and_unchanged_historical_employee(): void
    {
        $service = app(DeliveryService::class);
        $this->reject(fn () => $service->create($this->data(['employee_id' => 2])), 'employee_id');
        $delivery = $service->create($this->data());
        DB::table('NhanVien')->where('NhanVienID', 1)->update(['TrangThai' => 'Khóa']);
        $delivery = $service->update($delivery, ['notes' => 'Giữ phân công cũ']);
        $this->assertSame(1, $delivery->NhanVienID);
        $this->reject(fn () => $service->update($delivery, ['employee_id' => 2]), 'employee_id');
    }

    public function test_unchanged_past_schedule_is_preserved_but_new_past_schedule_rejected(): void
    {
        $service = app(DeliveryService::class);
        $delivery = $service->create($this->data(['pickup_date' => '2026-10-08', 'pickup_time' => '10:00']));
        DB::table('GiaoNhan')->where('GiaoNhanID', $delivery->GiaoNhanID)->update(['ThoiGianDuKien' => '2026-10-08 10:00:37']);
        $delivery = $delivery->fresh();
        $this->travelTo(now()->addDays(3));
        $delivery = $service->update($delivery, ['notes' => 'Cập nhật ghi chú']);
        $this->assertSame('2026-10-08 10:00:37', $delivery->ThoiGianDuKien->format('Y-m-d H:i:s'));
        $this->withoutMiddleware()->put(route('deliveries.update', $delivery), $this->data(['pickup_date' => '2026-10-08', 'pickup_time' => '10:00']))->assertSessionHasNoErrors();
        $this->assertSame('2026-10-08 10:00:37', $delivery->fresh()->ThoiGianDuKien->format('Y-m-d H:i:s'));
        $this->reject(fn () => $service->update($delivery, ['pickup_date' => '2026-10-09', 'pickup_time' => '10:00']), 'pickup_time');
        $delivery = $service->update($delivery, ['pickup_date' => null, 'pickup_time' => null]);
        $this->assertNull($delivery->ThoiGianDuKien);
    }
}
