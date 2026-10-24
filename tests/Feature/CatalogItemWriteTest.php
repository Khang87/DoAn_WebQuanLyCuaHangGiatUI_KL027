<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Http\Controllers\Admin\ChiTietDonHangController;
use App\Http\Requests\Admin\LuuChiTietDonHangRequest;
use App\Models\ChiTietDonHang;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\Support\BookingInspectionFixture;
use Tests\TestCase;

class CatalogItemWriteTest extends TestCase
{
    use BookingInspectionFixture;

    public function createApplication()
    {
        $app = parent::createApplication();
        $this->assertSame('sqlite', $app['config']->get('database.default'));
        $this->assertSame(':memory:', $app['config']->get('database.connections.sqlite.database'));
        $this->assertEmpty($app['config']->get('database.connections.sqlite.url'));
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        Http::preventStrayRequests();
        Http::fake();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSchema();
        Schema::table('ThanhToan', fn (Blueprint $table) => $table->string('TrangThai')->nullable());
        $this->createRequestCatalog();
        $this->actingAsBookingEmployee();
        foreach ([1, 2] as $id) {
            DB::table('DonHang')->insert([
                'DonHangID' => $id, 'MaDonHang' => 'DH'.$id,
                'KhachHangID' => 9, 'TrangThai' => OrderStatus::Pending->value,
            ]);
        }
    }

    private function itemData(int $orderId = 1): array
    {
        return [
            'DonHangID' => $orderId, 'DichVuID' => 1, 'LoaiDoGiatID' => 2,
            'DonViTinhID' => 2, 'DonGia' => 15000, 'SoLuong' => 2, 'KhoiLuong' => 1,
        ];
    }

    private function validatedRequest(int $orderId = 1, ?int $lineId = null): LuuChiTietDonHangRequest
    {
        $request = LuuChiTietDonHangRequest::create('/order-items/'.($lineId ?? ''), $lineId ? 'PUT' : 'POST', $this->itemData($orderId));
        if ($lineId !== null) {
            $route = new Route(['PUT'], 'order-items/{order_item}', fn () => null);
            $route->bind($request);
            $request->setRouteResolver(fn () => $route);
        }
        $request->setContainer($this->app)->setRedirector($this->app['redirect']);
        $request->validateResolved();

        return $request;
    }

    public function test_store_rechecks_catalog_after_request_validation(): void
    {
        $request = $this->validatedRequest();
        DB::table('DichVu')->where('DichVuID', 1)->update(['TrangThai' => 'Tạm ngưng']);

        $response = app(ChiTietDonHangController::class)->store($request);

        $this->assertSame(route('order-items.create'), $response->getTargetUrl());
        $this->assertTrue(session()->has('error'));
        $this->assertDatabaseCount('ChiTietDonHang', 0);
        $this->assertSame(0, DB::transactionLevel());
        Http::assertNothingSent();
    }

    public function test_active_item_can_be_created_and_its_suspended_tuple_retained(): void
    {
        $controller = app(ChiTietDonHangController::class);
        $response = $controller->store($this->validatedRequest());
        $this->assertSame(route('order-items.index'), $response->getTargetUrl());
        $line = ChiTietDonHang::query()->sole();
        $this->assertSame(30000.0, (float) $line->ThanhTien);

        DB::table('DanhMucLoaiDoGiat')->where('DanhMucID', 1)->update(['TrangThai' => 'Tạm ngưng']);
        $response = $controller->update($this->validatedRequest(1, $line->getKey()), $line->getKey());

        $this->assertSame(route('order-items.index'), $response->getTargetUrl());
        $this->assertTrue(session()->has('success'));
        $this->assertDatabaseCount('ChiTietDonHang', 1);
        $this->assertSame(30000.0, (float) $line->fresh()->ThanhTien);
        Http::assertNothingSent();
    }

    public function test_update_cannot_move_a_suspended_tuple_to_another_order_after_validation(): void
    {
        $line = ChiTietDonHang::create($this->itemData() + ['ThanhTien' => 30000]);
        $request = $this->validatedRequest(2, $line->getKey());
        DB::table('LoaiDichVu')->where('LoaiDichVuID', 1)->update(['TrangThai' => 'Tạm ngưng']);

        $response = app(ChiTietDonHangController::class)->update($request, $line->getKey());

        $this->assertSame(route('order-items.edit', $line->getKey()), $response->getTargetUrl());
        $this->assertTrue(session()->has('error'));
        $this->assertSame(1, (int) $line->fresh()->DonHangID);
        $this->assertSame(30000.0, (float) $line->fresh()->ThanhTien);
        $this->assertSame(0, DB::transactionLevel());
        Http::assertNothingSent();
    }
}
