<?php

namespace Tests\Feature;

use App\Http\Requests\Admin\LuuGiaoNhanRequest;
use App\Models\GiaoNhan;
use App\Services\DeliveryService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Mockery;
use Tests\TestCase;

class DeliverySchedulingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (
            config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:'
        ) {
            $this->markTestSkipped('Delivery scheduling tests require isolated SQLite in-memory storage.');
        }

        Schema::create('DonHang', function (Blueprint $table): void {
            $table->increments('DonHangID');
        });

        Schema::create('NhanVien', function (Blueprint $table): void {
            $table->increments('NhanVienID');
        });

        DB::table('DonHang')->insert(['DonHangID' => 1]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('NhanVien');
        Schema::dropIfExists('DonHang');
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_delivery_time_in_the_past_fails_validation(): void
    {
        Carbon::setTestNow('2026-10-03 18:30:00');

        $validator = $this->deliveryValidator('2026-10-03', '18:29');

        $this->assertTrue($validator->fails());
        $this->assertSame(
            'Thời gian giao nhận phải sau thời điểm hiện tại.',
            $validator->errors()->first('pickup_time'),
        );
    }

    public function test_future_delivery_time_passes_validation(): void
    {
        Carbon::setTestNow('2026-10-03 18:30:00');

        $validator = $this->deliveryValidator('2026-10-03', '18:31');

        $this->assertFalse($validator->fails());
    }

    public function test_deleting_delivery_redirects_to_index_and_reports_success(): void
    {
        $delivery = new GiaoNhan;
        $delivery->GiaoNhanID = 15;

        $deliveryService = Mockery::mock(DeliveryService::class);
        $deliveryService->shouldReceive('find')->once()->with(15)->andReturn($delivery);
        $deliveryService->shouldReceive('delete')->once()->with($delivery)->andReturnTrue();
        $this->app->instance(DeliveryService::class, $deliveryService);

        $this->withoutMiddleware()
            ->delete(route('deliveries.destroy', 15))
            ->assertRedirect(route('deliveries.index'))
            ->assertSessionHas('success', 'Đã xóa giao nhận.')
            ->assertSessionMissing('error');
    }

    public function test_failed_delivery_delete_does_not_report_success(): void
    {
        $delivery = new GiaoNhan;
        $delivery->GiaoNhanID = 16;

        $deliveryService = Mockery::mock(DeliveryService::class);
        $deliveryService->shouldReceive('find')->once()->with(16)->andReturn($delivery);
        $deliveryService->shouldReceive('delete')->once()->with($delivery)->andReturnFalse();
        $this->app->instance(DeliveryService::class, $deliveryService);

        $this->withoutMiddleware()
            ->delete(route('deliveries.destroy', 16))
            ->assertRedirect(route('deliveries.index'))
            ->assertSessionHas('error', 'Không thể xóa giao nhận. Vui lòng thử lại.')
            ->assertSessionMissing('success');
    }

    private function deliveryValidator(string $date, string $time): \Illuminate\Validation\Validator
    {
        $request = new LuuGiaoNhanRequest;
        $data = [
            'order_id' => 1,
            'employee_id' => null,
            'method' => 'nhan_do',
            'address' => 'Cửa hàng',
            'pickup_date' => $date,
            'pickup_time' => $time,
        ];
        $validator = Validator::make($data, $request->rules(), $request->messages());
        $request->withValidator($validator);

        return $validator;
    }
}
