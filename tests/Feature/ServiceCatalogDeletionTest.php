<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\V1\DichVuController;
use App\Http\Controllers\Api\V1\GarmentController;
use App\Models\DanhMucLoaiDoGiat;
use App\Models\DichVu;
use App\Models\LoaiDichVu;
use App\Models\LoaiDoGiat;
use App\Services\CatalogDeletionService;
use App\Services\GarmentCategoryService;
use App\Services\LoaiDoGiatService;
use App\Services\ServiceCategoryService;
use App\Services\ServiceService;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class ServiceCatalogDeletionTest extends TestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();
        $connection = $app['db']->connection();
        if ($connection->getDriverName() !== 'sqlite' || $connection->getDatabaseName() !== ':memory:') {
            throw new \RuntimeException('Catalog tests require SQLite :memory:.');
        }

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Http::fake();

        Schema::create('LoaiDichVu', function (Blueprint $table): void {
            $table->increments('LoaiDichVuID');
            $table->string('TenLoaiDichVu');
            $table->string('MoTa')->nullable();
            $table->string('TrangThai')->default('Hoạt động');
        });
        Schema::create('DichVu', function (Blueprint $table): void {
            $table->increments('DichVuID');
            $table->unsignedInteger('LoaiDichVuID');
            $table->string('TenDichVu');
            $table->string('MoTa')->nullable();
            $table->integer('ThoiGianDuKien')->nullable();
            $table->string('TrangThai')->default('Hoạt động');
        });

        Schema::create('DanhMucLoaiDoGiat', function (Blueprint $table): void {
            $table->increments('DanhMucID');
            $table->string('TenDanhMuc');
            $table->string('TrangThai')->default('Hoạt động');
        });
        Schema::create('LoaiDoGiat', function (Blueprint $table): void {
            $table->increments('LoaiDoGiatID');
            $table->unsignedInteger('DanhMucID');
            $table->string('TenLoaiDoGiat');
            $table->string('TrangThai')->default('Hoạt động');
            $table->foreign('DanhMucID')->references('DanhMucID')->on('DanhMucLoaiDoGiat')->restrictOnDelete();
        });
        Schema::table('DichVu', function (Blueprint $table): void {
            $table->foreign('LoaiDichVuID')->references('LoaiDichVuID')->on('LoaiDichVu')->restrictOnDelete();
        });
        foreach (['BangGia', 'ChiTietDonHang', 'ChiTietBooking', 'BookingYeuCauDichVu'] as $tableName) {
            Schema::create($tableName, function (Blueprint $table): void {
                $table->increments('id');
                $table->unsignedInteger('DichVuID')->nullable();
                $table->unsignedInteger('LoaiDoGiatID')->nullable();
                $table->decimal('ThanhTien')->default(123000);
                $table->foreign('DichVuID')->references('DichVuID')->on('DichVu')->restrictOnDelete();
                $table->foreign('LoaiDoGiatID')->references('LoaiDoGiatID')->on('LoaiDoGiat')->restrictOnDelete();
            });
        }
        Schema::create('BookingKiemKe', function (Blueprint $table): void {
            $table->increments('BookingKiemKeID');
            $table->json('DuLieuThucTe');
        });
    }

    protected function tearDown(): void
    {
        foreach (['BookingYeuCauDichVu', 'BookingKiemKe', 'ChiTietBooking', 'ChiTietDonHang', 'BangGia', 'DichVu', 'LoaiDichVu', 'LoaiDoGiat', 'DanhMucLoaiDoGiat'] as $table) {
            Schema::dropIfExists($table);
        }

        parent::tearDown();
    }

    public function test_unused_category_is_deleted(): void
    {
        $category = $this->createCategory();

        $this->assertTrue(app(ServiceCategoryService::class)->delete($category));
        $this->assertDatabaseMissing('LoaiDichVu', ['LoaiDichVuID' => $category->LoaiDichVuID]);
    }

    public function test_category_in_use_is_suspended_instead_of_deleted(): void
    {
        $category = $this->createCategory();
        $service = $this->createService($category);
        DB::table('ChiTietDonHang')->insert(['DichVuID' => $service->getKey()]);

        $this->assertFalse(app(ServiceCategoryService::class)->delete($category));
        $this->assertSame('Tạm ngưng', $category->fresh()->TrangThai);
        $this->assertSame('Tạm ngưng', $service->fresh()->TrangThai);
        $this->assertDatabaseCount('ChiTietDonHang', 1);
    }

    public function test_price_only_service_is_hard_deleted_without_deleting_garment(): void
    {
        $service = $this->createService($this->createCategory());
        $garment = $this->createGarment();
        DB::table('BangGia')->insert(['DichVuID' => $service->getKey(), 'LoaiDoGiatID' => $garment->getKey()]);

        $this->assertTrue(app(ServiceService::class)->delete($service));
        $this->assertNull($service->fresh());
        $this->assertNotNull($garment->fresh());
        $this->assertDatabaseCount('BangGia', 0);
    }

    public function test_unused_parent_and_its_unused_children_and_prices_are_deleted(): void
    {
        $category = $this->createCategory();
        $service = $this->createService($category);
        DB::table('BangGia')->insert(['DichVuID' => $service->getKey()]);

        $this->assertTrue(app(ServiceCategoryService::class)->delete($category));
        $this->assertNull($category->fresh());
        $this->assertNull($service->fresh());
        $this->assertDatabaseCount('BangGia', 0);
    }

    public function test_garment_with_history_is_suspended_and_prices_and_totals_are_preserved(): void
    {
        $garment = $this->createGarment();
        DB::table('ChiTietBooking')->insert(['LoaiDoGiatID' => $garment->getKey()]);
        DB::table('BangGia')->insert(['LoaiDoGiatID' => $garment->getKey()]);

        $this->assertFalse(app(LoaiDoGiatService::class)->delete($garment));
        $this->assertSame('Tạm ngưng', $garment->fresh()->TrangThai);
        $this->assertDatabaseHas('ChiTietBooking', ['LoaiDoGiatID' => $garment->getKey(), 'ThanhTien' => 123000]);
        $this->assertDatabaseCount('BangGia', 1);
    }

    public function test_unused_garment_and_price_are_hard_deleted(): void
    {
        $garment = $this->createGarment();
        DB::table('BangGia')->insert(['LoaiDoGiatID' => $garment->getKey()]);

        $this->assertTrue(app(LoaiDoGiatService::class)->delete($garment));
        $this->assertNull($garment->fresh());
        $this->assertDatabaseCount('BangGia', 0);
    }

    public function test_garment_category_with_used_child_suspends_whole_group_only(): void
    {
        $garment = $this->createGarment();
        $category = $garment->danhMuc;
        $unused = LoaiDoGiat::create(['DanhMucID' => $category->getKey(), 'TenLoaiDoGiat' => 'Áo mới', 'TrangThai' => 'Hoạt động']);
        $other = $this->createGarment();
        DB::table('ChiTietDonHang')->insert(['LoaiDoGiatID' => $garment->getKey()]);

        $this->assertFalse(app(GarmentCategoryService::class)->delete($category));
        $this->assertSame('Tạm ngưng', $category->fresh()->TrangThai);
        $this->assertSame('Tạm ngưng', $garment->fresh()->TrangThai);
        $this->assertSame('Tạm ngưng', $unused->fresh()->TrangThai);
        $this->assertSame('Hoạt động', $other->fresh()->TrangThai);
        $this->assertDatabaseCount('ChiTietDonHang', 1);
    }

    public function test_unused_garment_category_and_children_are_hard_deleted(): void
    {
        $garment = $this->createGarment();
        $category = $garment->danhMuc;
        DB::table('BangGia')->insert(['LoaiDoGiatID' => $garment->getKey()]);

        $this->assertTrue(app(GarmentCategoryService::class)->delete($category));
        $this->assertNull($category->fresh());
        $this->assertNull($garment->fresh());
        $this->assertDatabaseCount('BangGia', 0);
    }

    public function test_inspection_only_reference_in_previous_version_preserves_service_and_garment(): void
    {
        $service = $this->createService($this->createCategory());
        $garment = $this->createGarment();
        $snapshot = json_encode(['items' => [], '_previous_versions' => [['items' => [[
            'DichVuID' => (string) $service->getKey(), 'LoaiDoGiatID' => $garment->getKey(),
        ]]]]]);
        DB::table('BookingKiemKe')->insert(['DuLieuThucTe' => $snapshot]);

        $this->assertFalse(app(ServiceService::class)->delete($service));
        $this->assertFalse(app(LoaiDoGiatService::class)->delete($garment));
        $this->assertSame($snapshot, DB::table('BookingKiemKe')->value('DuLieuThucTe'));
    }

    public function test_confirmation_matches_usage_and_delete_rechecks_after_confirmation(): void
    {
        $service = $this->createService($this->createCategory());
        $deletion = app(CatalogDeletionService::class);
        $this->assertStringContainsString('xóa vĩnh viễn', $deletion->confirmation($service));
        DB::table('ChiTietBooking')->insert(['DichVuID' => $service->getKey()]);

        $this->assertStringContainsString("Dịch vụ này đã từng phát sinh trong đơn hàng/Booking nên hệ thống sẽ chuyển sang trạng thái 'Tạm ngưng' để bảo toàn dữ liệu lịch sử.", $deletion->confirmation($service));
        $this->assertFalse(app(ServiceService::class)->delete($service));
        $this->assertNotNull($service->fresh());
    }

    public function test_hard_delete_rolls_back_prices_if_record_deletion_fails(): void
    {
        $service = $this->createService($this->createCategory());
        DB::table('BangGia')->insert(['DichVuID' => $service->getKey()]);
        DB::unprepared('CREATE TRIGGER prevent_catalog_delete BEFORE DELETE ON "DichVu" BEGIN SELECT RAISE(ABORT, \'test failure\'); END');

        try {
            app(ServiceService::class)->delete($service);
            $this->fail('Deletion should fail.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('test failure', $exception->getMessage());
        }
        $this->assertNotNull($service->fresh());
        $this->assertDatabaseCount('BangGia', 1);
    }

    public function test_catalog_api_only_returns_active_service_and_parent_even_with_status_filter(): void
    {
        $active = $this->createService($this->createCategory());
        $inactive = $this->createService($this->createCategory());
        $inactive->update(['TrangThai' => 'Tạm ngưng']);
        $inactiveParent = $this->createCategory();
        $this->createService($inactiveParent);
        $inactiveParent->update(['TrangThai' => 'Tạm ngưng']);

        foreach ([DichVuController::class, GarmentController::class] as $controller) {
            $api = app($controller);
            $response = $api->index(Request::create('/api/catalog'));
            $this->assertSame([$active->getKey()], array_column($response->getData(true)['data'], 'id'));
            $response = $api->index(Request::create('/api/catalog', 'GET', ['status' => 'inactive']));
            $this->assertSame([], $response->getData(true)['data']);
            $this->assertNotContains($inactiveParent->getKey(), array_column($api->categories()->getData(true)['data'], 'id'));
        }
    }

    public function test_cached_garment_options_are_invalidated_when_parent_is_suspended(): void
    {
        $garment = $this->createGarment();
        $service = app(ServiceService::class);
        $this->assertArrayHasKey($garment->getKey(), $service->getGarmentTypes());

        $garment->danhMuc->update(['TrangThai' => 'Tạm ngưng']);

        $this->assertArrayNotHasKey($garment->getKey(), $service->getGarmentTypes());
        $this->assertSame('Hoạt động', $garment->fresh()->TrangThai);
        $this->assertNotNull($garment->fresh()->danhMuc);
    }

    public function test_parent_inspection_reference_preserves_all_children_and_unrelated_ids_do_not_block_deletion(): void
    {
        $category = $this->createCategory();
        $service = $this->createService($category);
        $unused = $this->createService($this->createCategory());
        DB::table('BookingKiemKe')->insert(['DuLieuThucTe' => json_encode(['items' => [['DichVuID' => $service->getKey()]]])]);

        $this->assertFalse(app(ServiceCategoryService::class)->delete($category));
        $this->assertSame('Tạm ngưng', $service->fresh()->TrangThai);
        $this->assertTrue(app(ServiceService::class)->delete($unused));
    }

    public function test_unquantified_booking_request_preserves_both_catalog_groups(): void
    {
        $service = $this->createService($this->createCategory());
        $garment = $this->createGarment();
        DB::table('BookingYeuCauDichVu')->insert(['DichVuID' => $service->getKey(), 'LoaiDoGiatID' => $garment->getKey()]);
        $deletion = app(CatalogDeletionService::class);

        foreach ([$service, $garment, $service->loaiDichVu, $garment->danhMuc] as $record) {
            $this->assertStringContainsString("'Tạm ngưng'", $deletion->confirmation($record));
            $this->assertFalse($deletion->delete($record));
            $this->assertSame('Tạm ngưng', $record->fresh()->TrangThai);
        }
        $this->assertDatabaseCount('BookingYeuCauDichVu', 1);
    }

    private function createGarment(): LoaiDoGiat
    {
        $category = DanhMucLoaiDoGiat::create(['TenDanhMuc' => 'Quần áo', 'TrangThai' => 'Hoạt động']);

        return LoaiDoGiat::create(['DanhMucID' => $category->getKey(), 'TenLoaiDoGiat' => 'Áo', 'TrangThai' => 'Hoạt động']);
    }

    public function test_unused_service_is_deleted(): void
    {
        $service = $this->createService($this->createCategory());

        $this->assertTrue(app(ServiceService::class)->delete($service));
        $this->assertDatabaseMissing('DichVu', ['DichVuID' => $service->DichVuID]);
    }

    public function test_service_referenced_by_booking_detail_is_suspended(): void
    {
        $service = $this->createService($this->createCategory());
        DB::table('ChiTietBooking')->insert(['DichVuID' => $service->DichVuID]);

        $this->assertFalse(app(ServiceService::class)->delete($service));
        $this->assertSame('Tạm ngưng', $service->fresh()->TrangThai);
    }

    public function test_service_edit_form_returns_to_service_detail_and_has_no_cancel_action(): void
    {
        $service = $this->createService($this->createCategory());
        view()->share('errors', new ViewErrorBag);

        $html = view('admin.services.edit', [
            'service' => $service,
            'categories' => LoaiDichVu::query()->get(),
            'errors' => new ViewErrorBag,
        ])->render();

        $this->assertStringContainsString(route('services.show', $service), $html);
        $this->assertSame(1, substr_count($html, 'Quay lại'));
        $this->assertStringContainsString('Lưu thay đổi', $html);
        $this->assertStringNotContainsString('>Hủy</a>', $html);
        $this->assertStringContainsString(route('services.update', $service->getKey()), $html);
        $this->assertStringContainsString('name="_method" value="PUT"', $html);
    }

    private function createCategory(): LoaiDichVu
    {
        return LoaiDichVu::query()->create([
            'TenLoaiDichVu' => 'Giặt sấy',
            'TrangThai' => 'Hoạt động',
        ]);
    }

    private function createService(LoaiDichVu $category): DichVu
    {
        return DichVu::query()->create([
            'LoaiDichVuID' => $category->LoaiDichVuID,
            'TenDichVu' => 'Giặt thường',
            'TrangThai' => 'Hoạt động',
        ]);
    }
}
