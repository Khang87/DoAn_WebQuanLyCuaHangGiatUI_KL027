<?php

namespace Tests\Feature;

use App\Enums\RecordStatus;
use App\Models\DanhMucLoaiDoGiat;
use App\Models\LoaiDoGiat;
use App\Services\GarmentCategoryService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GarmentCategoryManagementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');

        Schema::create('DanhMucLoaiDoGiat', function (Blueprint $table): void {
            $table->bigIncrements('DanhMucID');
            $table->string('TenDanhMuc', 100);
            $table->text('MoTa')->nullable();
            $table->string('TrangThai', 30)->default('Hoạt động');
            $table->dateTime('NgayTao')->useCurrent();
        });

        Schema::create('LoaiDoGiat', function (Blueprint $table): void {
            $table->increments('LoaiDoGiatID');
            $table->string('TenLoaiDoGiat', 150);
            $table->string('MoTa', 255)->nullable();
            $table->string('TrangThai', 30)->default('Hoạt động');
            $table->unsignedBigInteger('DanhMucID');
            $table->foreign('DanhMucID')
                ->references('DanhMucID')
                ->on('DanhMucLoaiDoGiat')
                ->restrictOnDelete();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('LoaiDoGiat');
        Schema::dropIfExists('DanhMucLoaiDoGiat');

        parent::tearDown();
    }

    public function test_existing_category_model_matches_schema_and_relates_garment_types(): void
    {
        $category = new DanhMucLoaiDoGiat;

        $this->assertSame('DanhMucLoaiDoGiat', $category->getTable());
        $this->assertSame('DanhMucID', $category->getKeyName());
        $this->assertFalse($category->usesTimestamps());
        $this->assertSame('DanhMucID', $category->loaiDoGiats()->getForeignKeyName());
    }

    public function test_categories_can_be_created_filtered_updated_and_deleted_without_ddl(): void
    {
        $service = app(GarmentCategoryService::class);
        $clothing = $service->create([
            'TenDanhMuc' => 'Quần áo',
            'MoTa' => 'Trang phục thường ngày',
            'TrangThai' => 'Hoạt động',
        ]);
        $bedding = $service->create([
            'TenDanhMuc' => 'Chăn ga',
            'TrangThai' => 'Tạm ngưng',
        ]);

        $service->update($clothing, ['TenDanhMuc' => 'Quần áo thường ngày']);

        $this->assertSame(
            ['Quần áo thường ngày'],
            $service->getAll(['search' => (string) $clothing->DanhMucID, 'status' => 'Hoạt động'])
                ->getCollection()
                ->pluck('TenDanhMuc')
                ->all(),
        );
        $this->assertTrue($service->delete($bedding));
        $this->assertNull($bedding->fresh());
    }

    public function test_category_list_displays_creation_date_and_sorts_by_it(): void
    {
        DB::table('DanhMucLoaiDoGiat')->insert([
            [
                'DanhMucID' => 1,
                'TenDanhMuc' => 'Danh mục cũ',
                'TrangThai' => 'Hoạt động',
                'NgayTao' => '2026-10-01 08:15:00',
            ],
            [
                'DanhMucID' => 2,
                'TenDanhMuc' => 'Danh mục mới',
                'TrangThai' => 'Hoạt động',
                'NgayTao' => '2026-10-05 16:40:00',
            ],
        ]);

        $categories = app(GarmentCategoryService::class)->getAll(['sort' => 'latest']);
        $html = view('admin.garment-categories.index', [
            'categories' => $categories,
            'statuses' => RecordStatus::databaseOptions(),
        ])->render();

        $this->assertSame(
            ['Danh mục mới', 'Danh mục cũ'],
            $categories->getCollection()->pluck('TenDanhMuc')->all(),
        );
        $this->assertStringContainsString('<th class="fw-bold text-dark">Ngày tạo</th>', $html);
        $this->assertStringContainsString('05/10/2026 16:40', $html);
        $this->assertStringContainsString('01/10/2026 08:15', $html);
    }

    public function test_category_in_use_is_suspended_instead_of_deleted(): void
    {
        $category = DanhMucLoaiDoGiat::query()->create([
            'TenDanhMuc' => 'Quần áo',
            'TrangThai' => 'Hoạt động',
        ]);
        LoaiDoGiat::query()->create([
            'TenLoaiDoGiat' => 'Áo sơ mi',
            'DanhMucID' => $category->DanhMucID,
            'TrangThai' => 'Hoạt động',
        ]);

        $deleted = app(GarmentCategoryService::class)->delete($category);

        $this->assertFalse($deleted);
        $this->assertSame('Tạm ngưng', $category->fresh()->TrangThai);
        $this->assertDatabaseHas('DanhMucLoaiDoGiat', [
            'DanhMucID' => $category->DanhMucID,
            'TrangThai' => 'Tạm ngưng',
        ]);
    }

    public function test_category_management_routes_use_existing_garment_permissions(): void
    {
        $routes = app('router')->getRoutes();

        $this->assertSame(
            'garment-categories',
            $routes->getByName('garment-categories.index')->uri(),
        );
        $this->assertContains(
            'permission:garment_categories.view',
            $routes->getByName('garment-categories.index')->getAction('middleware'),
        );
        $this->assertContains(
            'permission:garment_categories.create',
            $routes->getByName('garment-categories.store')->getAction('middleware'),
        );
    }
}
