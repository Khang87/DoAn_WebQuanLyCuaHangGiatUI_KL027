<?php

namespace Tests\Feature;

use App\Models\DichVu;
use App\Models\LoaiDichVu;
use App\Services\ServiceCategoryService;
use App\Services\ServiceService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ServiceCatalogDeletionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');

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

        foreach (['BangGia', 'ChiTietDonHang', 'ChiTietBooking'] as $tableName) {
            Schema::create($tableName, function (Blueprint $table): void {
                $table->increments('id');
                $table->unsignedInteger('DichVuID');
            });
        }
    }

    protected function tearDown(): void
    {
        foreach (['ChiTietBooking', 'ChiTietDonHang', 'BangGia', 'DichVu', 'LoaiDichVu'] as $table) {
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
        $this->createService($category);

        $this->assertFalse(app(ServiceCategoryService::class)->delete($category));
        $this->assertSame('Tạm ngưng', $category->fresh()->TrangThai);
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
