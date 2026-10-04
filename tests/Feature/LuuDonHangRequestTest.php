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
            $this->markTestSkipped('Order request validation tests require isolated SQLite in-memory storage.');
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

    public function test_unknown_promotion_code_is_reported_as_a_validation_error(): void
    {
        $request = new LuuDonHangRequest;
        $request->replace(['promotion_code' => 'UNKNOWN']);
        $validator = Validator::make([], []);

        $request->withValidator($validator);

        $this->assertTrue($validator->fails());
        $this->assertSame(
            'Mã khuyến mãi không tồn tại.',
            $validator->errors()->first('promotion_code'),
        );
    }

    public function test_promotion_code_must_match_the_selected_promotion_id(): void
    {
        DB::table('KhuyenMai')->insert([
            'KhuyenMaiID' => 3,
            'MaKhuyenMai' => 'SAVE10',
        ]);
        $request = new LuuDonHangRequest;
        $request->replace([
            'KhuyenMaiID' => 4,
            'promotion_code' => 'save10',
        ]);
        $validator = Validator::make([], []);

        $request->withValidator($validator);

        $this->assertSame(
            'Mã voucher không khớp với chương trình đã chọn.',
            $validator->errors()->first('KhuyenMaiID'),
        );
    }

    private function orderRequestRules(int $orderId): array
    {
        $route = new Route(['PUT'], '/orders/{order}', []);
        $route->bind(Request::create('/orders/'.$orderId, 'PUT'));

        $request = new LuuDonHangRequest;
        $request->setRouteResolver(static fn (): Route => $route);

        return $request->rules();
    }
}
