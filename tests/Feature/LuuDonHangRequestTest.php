<?php

namespace Tests\Feature;

use App\Http\Requests\Admin\LuuDonHangRequest;
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
        $this->testSchemaCreated = true;

        DB::table('DonHang')->insert([
            ['DonHangID' => 7, 'MaDonHang' => 'DH-007'],
            ['DonHangID' => 8, 'MaDonHang' => 'DH-008'],
        ]);
    }

    protected function tearDown(): void
    {
        if ($this->testSchemaCreated) {
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

    private function orderRequestRules(int $orderId): array
    {
        $route = new Route(['PUT'], '/orders/{order}', []);
        $route->bind(Request::create('/orders/'.$orderId, 'PUT'));

        $request = new LuuDonHangRequest;
        $request->setRouteResolver(static fn (): Route => $route);

        return $request->rules();
    }
}
