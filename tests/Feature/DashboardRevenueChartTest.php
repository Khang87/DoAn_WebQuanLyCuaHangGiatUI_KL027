<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
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
        });

        Schema::create('HoaDon', function (Blueprint $table): void {
            $table->increments('HoaDonID');
            $table->unsignedInteger('DonHangID');
            $table->string('TrangThai');
        });

        DB::table('DonHang')->insert([
            ['DonHangID' => 1, 'TrangThai' => OrderStatus::Paid->value, 'ThanhTien' => 500, 'NgayTao' => '2026-10-04 09:15:00'],
            ['DonHangID' => 2, 'TrangThai' => OrderStatus::Washing->value, 'ThanhTien' => 700, 'NgayTao' => '2026-10-04 12:30:00'],
            ['DonHangID' => 3, 'TrangThai' => OrderStatus::Washing->value, 'ThanhTien' => 900, 'NgayTao' => '2026-10-04 12:45:00'],
            ['DonHangID' => 4, 'TrangThai' => OrderStatus::Paid->value, 'ThanhTien' => 300, 'NgayTao' => '2026-10-02 15:00:00'],
        ]);

        DB::table('HoaDon')->insert([
            ['HoaDonID' => 1, 'DonHangID' => 2, 'TrangThai' => InvoiceStatus::Paid->value],
            ['HoaDonID' => 2, 'DonHangID' => 3, 'TrangThai' => InvoiceStatus::Unpaid->value],
        ]);

        Carbon::setTestNow('2026-10-04 20:00:00');
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('HoaDon');
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
                $this->assertEquals(500, $chart['data'][9]);
                $this->assertEquals(700, $chart['data'][12]);
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
}
