<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Exceptions\SettledOrderException;
use App\Models\GiaoNhan;
use App\Services\DeliveryService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
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
            $table->string('TrangThai')->default(OrderStatus::Received->value);
        });
        Schema::create('NhanVien', function (Blueprint $table): void {
            $table->increments('NhanVienID');
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
        DB::table('DonHang')->insert(['DonHangID' => 1]);
        DB::table('NhanVien')->insert([['NhanVienID' => 1, 'TrangThai' => 'Hoạt động'], ['NhanVienID' => 2, 'TrangThai' => 'Khóa']]);
    }

    protected function tearDown(): void
    {
        foreach (['GiaoNhan', 'NhanVien', 'DonHang'] as $table) {
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
                $response->assertSessionHasNoErrors();
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
            ->assertSessionHasNoErrors();
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
        return [[OrderStatus::Cancelled->value], [OrderStatus::Paid->value]];
    }

    public function test_return_execution_waits_for_washing_and_requires_schedule(): void
    {
        $service = app(DeliveryService::class);
        $delivery = $service->create($this->data());
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
        $this->reject(fn () => $service->create($this->data()), 'method');
    }

    public function test_pickup_execution_preserves_type_when_editing_notes_and_cannot_go_back(): void
    {
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
