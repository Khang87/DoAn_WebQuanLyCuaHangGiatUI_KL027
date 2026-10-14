<?php

namespace Tests\Feature;

use App\Http\Requests\Admin\LuuDonHangRequest;
use App\Services\OrderService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class LuuDonHangRequestTest extends TestCase
{
    private bool $testSchemaCreated = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (
            config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:'
        ) {
            $this->fail('Order request validation tests require isolated SQLite in-memory storage.');
        }

        Schema::create('DonHang', function (Blueprint $table): void {
            $table->increments('DonHangID');
            $table->string('MaDonHang')->unique();
        });
        Schema::create('KhuyenMai', function (Blueprint $table): void {
            $table->increments('KhuyenMaiID');
            $table->string('MaKhuyenMai')->unique();
        });
        $this->testSchemaCreated = true;

        DB::table('DonHang')->insert([
            ['DonHangID' => 7, 'MaDonHang' => 'DH-007'],
            ['DonHangID' => 8, 'MaDonHang' => 'DH-008'],
        ]);
    }

    protected function tearDown(): void
    {
        if ($this->testSchemaCreated) {
            Schema::dropIfExists('KhuyenMai');
            Schema::dropIfExists('DonHang');
        }

        parent::tearDown();
    }

    public function test_update_allows_the_existing_order_code_for_the_current_order(): void
    {
        $validator = Validator::make(
            ['MaDonHang' => 'DH-007'],
            ['MaDonHang' => $this->orderRequestRules(7)['MaDonHang']],
        );

        $this->assertTrue($validator->passes());
    }

    public function test_update_rejects_an_order_code_used_by_a_different_order(): void
    {
        $validator = Validator::make(
            ['MaDonHang' => 'DH-008'],
            ['MaDonHang' => $this->orderRequestRules(7)['MaDonHang']],
        );

        $this->assertTrue($validator->fails());
    }

    public function test_order_code_is_optional_for_automatic_generation(): void
    {
        $validator = Validator::make([], [
            'MaDonHang' => $this->orderRequestRules(7)['MaDonHang'],
        ]);

        $this->assertTrue($validator->passes());
    }

    public function test_next_generated_order_code_uses_the_next_order_id_with_four_digits(): void
    {
        $this->assertSame('DH0009', app(OrderService::class)->nextOrderCode());
    }

    public function test_manual_order_requests_reject_vouchers_on_create_and_update(): void
    {
        foreach (['POST', 'PUT'] as $method) {
            $request = LuuDonHangRequest::create('/orders/7', $method);
            $rules = $request->rules();
            foreach ([['KhuyenMaiID' => 3], ['promotion_code' => 'SAVE10']] as $input) {
                $validator = Validator::make($input, array_intersect_key($rules, array_flip(['KhuyenMaiID', 'promotion_code'])));
                $this->assertTrue($validator->fails());
                $this->assertTrue($validator->errors()->has(array_key_first($input)));
            }
            $this->assertTrue(Validator::make([], array_intersect_key($rules, array_flip(['KhuyenMaiID', 'promotion_code'])))->passes());
        }
    }

    public function test_order_update_cannot_change_points_but_creation_can_select_them(): void
    {
        $createRules = $this->orderRequestRules(0, 'POST');
        $updateRules = $this->orderRequestRules(7);
        $rewardFields = ['DiemSuDung', 'use_points'];

        $this->assertTrue(Validator::make(
            ['DiemSuDung' => 25, 'use_points' => 1],
            array_intersect_key($createRules, array_flip($rewardFields)),
        )->passes());

        $validator = Validator::make(
            ['DiemSuDung' => 25, 'use_points' => 1],
            array_intersect_key($updateRules, array_flip($rewardFields)),
        );
        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('DiemSuDung'));
        $this->assertTrue($validator->errors()->has('use_points'));
    }

    private function orderRequestRules(int $orderId, string $method = 'PUT'): array
    {
        $route = new Route([$method], '/orders/{order}', []);
        $route->bind(Request::create('/orders/'.$orderId, $method));

        $request = new LuuDonHangRequest;
        $request->setMethod($method);
        $request->setRouteResolver(static fn (): Route => $route);

        return $request->rules();
    }
}
