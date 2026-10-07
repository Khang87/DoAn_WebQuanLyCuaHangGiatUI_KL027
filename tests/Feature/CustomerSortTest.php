<?php

namespace Tests\Feature;

use App\Services\CustomerService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CustomerSortTest extends TestCase
{
    private bool $testSchemaCreated = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (
            config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:'
        ) {
            $this->fail('Customer sort tests require isolated SQLite in-memory storage.');
        }

        Schema::create('KhachHang', function (Blueprint $table): void {
            $table->increments('KhachHangID');
            $table->string('HoTen');
            $table->string('SoDienThoai')->nullable();
            $table->string('Email')->nullable();
            $table->string('DiaChi')->nullable();
            $table->dateTime('NgayTao')->nullable();
            $table->string('TrangThai')->nullable();
        });
        $this->testSchemaCreated = true;

        Schema::create('DiemTichLuy', function (Blueprint $table): void {
            $table->increments('DiemTichLuyID');
            $table->unsignedInteger('KhachHangID')->unique();
            $table->integer('DiemHienTai')->default(0);
            $table->dateTime('NgayCapNhat')->nullable();
        });

        DB::table('KhachHang')->insert([
            ['KhachHangID' => 1, 'HoTen' => 'Không có điểm'],
            ['KhachHangID' => 2, 'HoTen' => 'Có điểm cao'],
            ['KhachHangID' => 3, 'HoTen' => 'Điểm bằng 0'],
            ['KhachHangID' => 4, 'HoTen' => 'Có điểm thấp'],
        ]);

        DB::table('DiemTichLuy')->insert([
            ['DiemTichLuyID' => 1, 'KhachHangID' => 2, 'DiemHienTai' => 40],
            ['DiemTichLuyID' => 2, 'KhachHangID' => 3, 'DiemHienTai' => 0],
            ['DiemTichLuyID' => 3, 'KhachHangID' => 4, 'DiemHienTai' => 10],
        ]);
    }

    protected function tearDown(): void
    {
        if ($this->testSchemaCreated) {
            Schema::dropIfExists('DiemTichLuy');
            Schema::dropIfExists('KhachHang');
        }

        parent::tearDown();
    }

    public function test_points_sort_orders_customers_with_missing_points_as_zero(): void
    {
        $customers = app(CustomerService::class)->getAll(['sort' => 'points_desc']);

        $this->assertSame([2, 4, 1, 3], $customers->getCollection()->pluck('KhachHangID')->all());
        $this->assertSame(0, (int) ($customers->getCollection()->firstWhere('KhachHangID', 1)->diemTichLuy?->DiemHienTai ?? 0));
    }

    public function test_points_ascending_sort_is_stable_for_equal_point_totals(): void
    {
        $customers = app(CustomerService::class)->getAll(['sort' => 'points_asc']);

        $this->assertSame([1, 3, 4, 2], $customers->getCollection()->pluck('KhachHangID')->all());
    }

    public function test_customers_can_be_sorted_by_id_in_ascending_order(): void
    {
        DB::table('KhachHang')->where('KhachHangID', 1)->update(['NgayTao' => '2026-01-01']);
        DB::table('KhachHang')->where('KhachHangID', 4)->update(['NgayTao' => '2026-12-01']);

        $customers = app(CustomerService::class)->getAll(['sort' => 'id_asc']);

        $this->assertSame([1, 2, 3, 4], $customers->getCollection()->pluck('KhachHangID')->all());
    }
}
