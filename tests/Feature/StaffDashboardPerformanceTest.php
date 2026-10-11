<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Http\Controllers\Staff\DashboardController;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StaffDashboardPerformanceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            $this->fail('Dashboard tests require isolated SQLite in-memory storage.');
        }
        Schema::create('DonHang', function (Blueprint $table): void {
            $table->increments('DonHangID');
            $table->string('TrangThai');
            $table->integer('KhachHangID')->nullable();
            $table->integer('NhanVienID')->nullable();
            $table->dateTime('NgayTao')->nullable();
        });
        Schema::create('GiaoNhan', function (Blueprint $table): void {
            $table->increments('GiaoNhanID');
            $table->integer('DonHangID');
            $table->integer('NhanVienID')->nullable();
            $table->dateTime('ThoiGianDuKien')->nullable();
            $table->string('TrangThai');
            $table->string('LoaiGiaoNhan');
        });
        Schema::create('Booking', function (Blueprint $table): void {
            $table->increments('BookingID');
            $table->date('NgayHen')->nullable();
            $table->time('GioHen')->nullable();
            $table->string('TrangThai');
        });
        $this->actingAs(new User(['TaiKhoanID' => 99, 'TrangThai' => 'Hoạt động']));
    }

    public function test_operational_kpis_use_one_aggregate_and_exclude_settled_cancelled_orders(): void
    {
        foreach (OrderStatus::cases() as $index => $status) {
            DB::table('DonHang')->insert(array_fill(0, $index + 1, ['TrangThai' => $status->value]));
        }
        $data = $this->dashboardData();

        $this->assertSame(1, $data['waitingReceiveCount']);
        $this->assertSame(5, $data['washingCount']);
        $this->assertSame(9, $data['readyCount']);
        $this->assertCount(10, $data['processingOrders']);
    }

    public function test_empty_dashboard_kpis_are_integer_zero_with_one_aggregate(): void
    {
        $data = $this->dashboardData();

        $this->assertSame(0, $data['waitingReceiveCount']);
        $this->assertSame(0, $data['washingCount']);
        $this->assertSame(0, $data['readyCount']);
        $this->assertCount(0, $data['processingOrders']);
    }

    private function dashboardData(): array
    {
        DB::connection()->flushQueryLog();
        DB::connection()->enableQueryLog();
        try {
            $data = app(DashboardController::class)->index()->getData();
            $aggregates = array_filter(DB::connection()->getQueryLog(), static fn (array $query): bool => str_contains(strtolower($query['query']), 'count(')
                && str_contains($query['query'], '"DonHang"')
            );
            $this->assertCount(1, $aggregates, 'The three operational KPIs share one status aggregate.');

            return $data;
        } finally {
            DB::connection()->disableQueryLog();
        }
    }
}
