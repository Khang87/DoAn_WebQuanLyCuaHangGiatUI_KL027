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
use Illuminate\Support\ViewErrorBag;
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
            $this->fail('Delivery scheduling tests require isolated SQLite in-memory storage.');
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

    public function test_store_fulfillment_accepts_no_address_but_home_requires_an_address(): void
    {
        Carbon::setTestNow('2026-10-07 10:00:00');
        $request = new LuuGiaoNhanRequest;
        $data = ['order_id' => 1, 'method' => 'nhan_do', 'fulfillment' => 'Tại cửa hàng',
            'pickup_date' => '2026-10-08', 'pickup_time' => '10:00'];
        $this->assertFalse(Validator::make($data, $request->rules())->fails());
        $data['fulfillment'] = 'Tại nhà';
        $this->assertArrayHasKey('address', Validator::make($data, $request->rules())->errors()->toArray());
    }

    public function test_edit_form_selects_delivery_type_from_loai_giao_nhan_and_shows_fulfillment_separately(): void
    {
        $delivery = new GiaoNhan;
        $delivery->forceFill(['GiaoNhanID' => 1, 'LoaiGiaoNhan' => 'GIAO_DO', 'HinhThuc' => 'Tại cửa hàng']);
        $html = view('admin.deliveries.edit', ['delivery' => $delivery, 'orders' => collect(), 'employees' => collect(), 'errors' => new ViewErrorBag])->render();
        $this->assertStringContainsString('Loại giao nhận', $html);
        $this->assertMatchesRegularExpression('/value="giao_do"\s+selected/', $html);
        $this->assertMatchesRegularExpression('/value="Tại cửa hàng"\s+selected/', $html);
        $this->assertStringContainsString('name="fulfillment"', $html);
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
