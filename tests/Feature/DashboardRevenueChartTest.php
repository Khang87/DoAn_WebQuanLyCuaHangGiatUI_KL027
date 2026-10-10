<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Admin\DashboardController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DashboardRevenueChartTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (
            config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:'
        ) {
            $this->fail('Dashboard chart tests require isolated SQLite in-memory storage.');
        }

        Schema::create('DonHang', function (Blueprint $table): void {
            $table->increments('DonHangID');
            $table->string('TrangThai');
            $table->decimal('ThanhTien', 12, 2);
            $table->dateTime('NgayTao');
            $table->unsignedInteger('KhachHangID')->nullable();
            $table->unsignedInteger('NhanVienID')->nullable();
        });

        Schema::create('HoaDon', function (Blueprint $table): void {
            $table->increments('HoaDonID');
            $table->unsignedInteger('DonHangID');
            $table->string('TrangThai');
            $table->decimal('ThanhTien', 12, 2)->default(0);
            $table->dateTime('NgayLap');
        });

        Schema::create('ThanhToan', function (Blueprint $table): void {
            $table->increments('ThanhToanID');
            $table->unsignedInteger('DonHangID');
            $table->decimal('SoTien', 12, 2);
            $table->string('PhuongThuc')->nullable();
            $table->dateTime('ThoiGian');
            $table->string('TrangThai');
        });

        Schema::create('KhachHang', function (Blueprint $table): void {
            $table->increments('KhachHangID');
            $table->string('HoTen')->nullable();
            $table->string('SoDienThoai')->nullable();
            $table->dateTime('NgayTao')->nullable();
        });

        Schema::create('NhanVien', function (Blueprint $table): void {
            $table->increments('NhanVienID');
            $table->string('HoTen')->nullable();
        });

        Schema::create('DichVu', function (Blueprint $table): void {
            $table->increments('DichVuID');
            $table->string('TenDichVu')->nullable();
        });

        Schema::create('ChiTietDonHang', function (Blueprint $table): void {
            $table->increments('ChiTietDonHangID');
            $table->unsignedInteger('DichVuID');
        });

        Schema::create('DanhGia', function (Blueprint $table): void {
            $table->increments('DanhGiaID');
            $table->unsignedInteger('DonHangID')->nullable();
            $table->unsignedInteger('KhachHangID')->nullable();
            $table->unsignedTinyInteger('SoSao');
            $table->string('TrangThai');
            $table->dateTime('NgayDanhGia');
            $table->string('BinhLuan')->nullable();
        });

        DB::table('DonHang')->insert([
            ['DonHangID' => 1, 'TrangThai' => OrderStatus::Paid->value, 'ThanhTien' => 500, 'NgayTao' => '2026-10-04 09:15:00'],
            ['DonHangID' => 2, 'TrangThai' => OrderStatus::Washing->value, 'ThanhTien' => 700, 'NgayTao' => '2026-10-04 12:30:00'],
            ['DonHangID' => 3, 'TrangThai' => OrderStatus::Washing->value, 'ThanhTien' => 900, 'NgayTao' => '2026-10-04 12:45:00'],
            ['DonHangID' => 4, 'TrangThai' => OrderStatus::Paid->value, 'ThanhTien' => 300, 'NgayTao' => '2026-10-02 15:00:00'],
        ]);

        DB::table('HoaDon')->insert([
            ['HoaDonID' => 1, 'DonHangID' => 1, 'TrangThai' => InvoiceStatus::Paid->value, 'ThanhTien' => 500, 'NgayLap' => '2026-10-04 09:15:00'],
            ['HoaDonID' => 2, 'DonHangID' => 2, 'TrangThai' => InvoiceStatus::Paid->value, 'ThanhTien' => 700, 'NgayLap' => '2026-10-04 12:30:00'],
            ['HoaDonID' => 3, 'DonHangID' => 3, 'TrangThai' => InvoiceStatus::Unpaid->value, 'ThanhTien' => 900, 'NgayLap' => '2026-10-04 12:45:00'],
            ['HoaDonID' => 4, 'DonHangID' => 4, 'TrangThai' => InvoiceStatus::Paid->value, 'ThanhTien' => 300, 'NgayLap' => '2026-10-02 15:00:00'],
        ]);
        DB::table('ThanhToan')->insert([
            ['ThanhToanID' => 1, 'DonHangID' => 1, 'TrangThai' => PaymentStatus::Paid->value, 'SoTien' => 500, 'ThoiGian' => '2026-10-04 09:15:00'],
            ['ThanhToanID' => 2, 'DonHangID' => 2, 'TrangThai' => PaymentStatus::Paid->value, 'SoTien' => 700, 'ThoiGian' => '2026-10-04 12:30:00'],
            ['ThanhToanID' => 3, 'DonHangID' => 4, 'TrangThai' => PaymentStatus::Paid->value, 'SoTien' => 300, 'ThoiGian' => '2026-10-02 15:00:00'],
        ]);

        Carbon::setTestNow('2026-10-04 20:00:00');
    }

    protected function tearDown(): void
    {
        foreach (['DanhGia', 'ChiTietDonHang', 'DichVu', 'NhanVien', 'KhachHang', 'ThanhToan', 'HoaDon'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::dropIfExists('DonHang');
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_revenue_chart_aggregates_each_filter_in_a_single_query(): void
    {
        $controller = app(DashboardController::class);

        foreach (['today', '7_days', 'this_month', 'this_year'] as $filter) {
            DB::connection()->flushQueryLog();
            DB::connection()->enableQueryLog();

            $response = $controller->getRevenueChartData(Request::create('/', 'GET', ['filter' => $filter]));
            $chart = $response->getData(true);

            $this->assertCount(1, DB::connection()->getQueryLog(), "The {$filter} chart should execute one aggregate query.");
            $this->assertCount(count($chart['labels']), $chart['data']);

            if ($filter === 'today') {
                $this->assertSame(24, count($chart['labels']));
                $this->assertEquals(500, $chart['data'][16]);
                $this->assertEquals(700, $chart['data'][19]);
                $this->assertEquals(0, $chart['data'][10]);
            }

            if ($filter === '7_days') {
                $this->assertSame(['28/09', '29/09', '30/09', '01/10', '02/10', '03/10', '04/10'], $chart['labels']);
                $this->assertEquals([0, 0, 0, 0, 300, 0, 1200], $chart['data']);
            }

            if ($filter === 'this_month') {
                $this->assertSame(31, count($chart['labels']));
                $this->assertEquals(300, $chart['data'][1]);
                $this->assertEquals(1200, $chart['data'][3]);
            }

            if ($filter === 'this_year') {
                $this->assertSame(12, count($chart['labels']));
                $this->assertEquals(1500, $chart['data'][9]);
            }
        }
    }

    public function test_dashboard_kpis_use_database_values_and_return_zero_when_empty(): void
    {
        DB::table('ThanhToan')->delete();
        DB::table('HoaDon')->delete();
        DB::table('DonHang')->delete();

        $emptyDashboard = app(DashboardController::class)->index()->getData();

        $this->assertSame(0.0, $emptyDashboard['todayRevenue']);
        $this->assertSame(0.0, $emptyDashboard['weekRevenue']);
        $this->assertSame(0.0, $emptyDashboard['monthRevenue']);
        $this->assertSame(0, $emptyDashboard['totalOrders']);
        $this->assertSame(0, $emptyDashboard['totalCustomers']);
        $this->assertSame(0.0, $emptyDashboard['averageRating']);
        $this->assertSame(0, $emptyDashboard['totalReviews']);

        DB::table('DonHang')->insert([
            ['DonHangID' => 1, 'TrangThai' => OrderStatus::Paid->value, 'ThanhTien' => 500, 'NgayTao' => '2026-10-04 09:15:00'],
            ['DonHangID' => 2, 'TrangThai' => OrderStatus::Washing->value, 'ThanhTien' => 700, 'NgayTao' => '2026-10-04 12:30:00'],
            ['DonHangID' => 3, 'TrangThai' => OrderStatus::Cancelled->value, 'ThanhTien' => 900, 'NgayTao' => '2026-10-04 12:45:00'],
            ['DonHangID' => 4, 'TrangThai' => OrderStatus::Delivered->value, 'ThanhTien' => 600, 'NgayTao' => '2026-10-04 13:00:00'],
        ]);
        DB::table('HoaDon')->insert([
            ['HoaDonID' => 10, 'DonHangID' => 1, 'TrangThai' => InvoiceStatus::Paid->value, 'ThanhTien' => 450, 'NgayLap' => '2026-10-04 09:20:00'],
            ['HoaDonID' => 11, 'DonHangID' => 2, 'TrangThai' => InvoiceStatus::Unpaid->value, 'ThanhTien' => 700, 'NgayLap' => '2026-10-04 12:35:00'],
        ]);
        DB::table('ThanhToan')->insert([
            ['ThanhToanID' => 10, 'DonHangID' => 1, 'TrangThai' => PaymentStatus::Paid->value, 'SoTien' => 450, 'ThoiGian' => '2026-10-04 09:20:00'],
        ]);
        DB::table('KhachHang')->insert([
            ['KhachHangID' => 1, 'HoTen' => 'Customer New', 'NgayTao' => '2026-10-03 10:00:00'],
            ['KhachHangID' => 2, 'HoTen' => 'Customer Existing', 'NgayTao' => '2026-09-03 10:00:00'],
        ]);
        DB::table('DanhGia')->insert([
            ['DanhGiaID' => 1, 'SoSao' => 4, 'TrangThai' => 'Hiển thị', 'NgayDanhGia' => '2026-10-03 10:00:00'],
            ['DanhGiaID' => 2, 'SoSao' => 5, 'TrangThai' => 'Hiển thị', 'NgayDanhGia' => '2026-10-04 10:00:00'],
            ['DanhGiaID' => 3, 'SoSao' => 1, 'TrangThai' => 'Ẩn', 'NgayDanhGia' => '2026-10-04 11:00:00'],
        ]);

        $dashboard = app(DashboardController::class)->index()->getData();

        $this->assertSame(450.0, $dashboard['todayRevenue']);
        $this->assertSame(450.0, $dashboard['weekRevenue']);
        $this->assertSame(450.0, $dashboard['monthRevenue']);
        $this->assertSame(4, $dashboard['totalOrders']);
        $this->assertSame(['completed' => 1, 'processing' => 1, 'cancelled' => 1], $dashboard['statusCounts']);
        $this->assertEqualsCanonicalizing([1, 2, 3, 4], $dashboard['recentOrders']->pluck('DonHangID')->all());
        $this->assertSame(1, (int) $dashboard['statusDistribution']->get(OrderStatus::Washing->value, 0));
        $this->assertSame(2, $dashboard['totalCustomers']);
        $this->assertSame(1, $dashboard['newCustomersMonth']);
        $this->assertSame(4.5, $dashboard['averageRating']);
        $this->assertSame(2, $dashboard['totalReviews']);
    }
}
