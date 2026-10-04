<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Models\User;
use App\Services\ReportsService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class ReportsServiceTest extends TestCase
{
    private const TEST_TABLES = [
        'DonHang',
        'HoaDon',
        'ThanhToan',
        'Booking',
        'KhachHang',
        'LoaiDichVu',
        'DichVu',
        'LoaiDoGiat',
        'DonViTinh',
        'ChiTietDonHang',
    ];

    private bool $testSchemaCreated = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (
            config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:'
        ) {
            $this->markTestSkipped('ReportsService tests require the isolated SQLite in-memory connection.');
        }

        $this->createTestSchema();
        $this->testSchemaCreated = true;
        $this->seedReportFixtures();
    }

    protected function tearDown(): void
    {
        if ($this->testSchemaCreated) {
            foreach (array_reverse(self::TEST_TABLES) as $table) {
                Schema::dropIfExists($table);
            }
        }

        parent::tearDown();
    }

    public function test_kpis_use_paid_invoice_revenue_and_count_new_bookings(): void
    {
        $metrics = app(ReportsService::class)->getKpiMetrics([
            'range' => 'custom',
            'date_from' => '2026-01-10',
            'date_to' => '2026-01-10',
        ]);

        $this->assertSame(4, $metrics['total_orders']);
        $this->assertSame(1, $metrics['completed_orders']);
        $this->assertSame(2, $metrics['processing_orders']);
        $this->assertSame(1, $metrics['cancelled_orders']);
        $this->assertSame(
            $metrics['total_orders'],
            $metrics['completed_orders'] + $metrics['processing_orders'] + $metrics['cancelled_orders'],
        );
        $this->assertSame(1, $metrics['new_bookings']);
        $this->assertEquals(125000, $metrics['total_revenue']);
        $this->assertEquals(125000, $metrics['avg_order_value']);
    }

    public function test_kpis_and_revenue_chart_use_bounded_aggregate_query_counts(): void
    {
        $service = app(ReportsService::class);
        $filters = [
            'range' => 'custom',
            'date_from' => '2026-01-10',
            'date_to' => '2026-01-10',
        ];

        DB::connection()->flushQueryLog();
        DB::connection()->enableQueryLog();
        $service->getKpiMetrics($filters);
        $this->assertCount(3, DB::connection()->getQueryLog());

        DB::connection()->flushQueryLog();
        $chart = $service->getRevenueChartData($filters);
        $this->assertCount(1, DB::connection()->getQueryLog());
        $this->assertSame(['10/01'], $chart['labels']);
        $this->assertEquals([125000], $chart['revenue']);
    }

    public function test_charts_use_paid_invoice_dates_and_calculate_service_shares(): void
    {
        $service = app(ReportsService::class);
        $filters = [
            'range' => 'custom',
            'date_from' => '2026-01-10',
            'date_to' => '2026-01-10',
        ];

        $chart = $service->getRevenueChartData($filters);
        $composition = $service->getServiceComposition($filters);

        $this->assertSame(['10/01'], $chart['labels']);
        $this->assertEquals([125000], $chart['revenue']);
        $this->assertSame(['Giặt sấy', 'Giặt hấp'], $composition['labels']);
        $this->assertEquals([100000, 25000], $composition['revenue']);
        $this->assertEquals([80, 20], $composition['percentages']);
    }

    public function test_custom_date_filters_use_vietnamese_calendar_days_against_utc_timestamps(): void
    {
        $service = app(ReportsService::class);
        $dates = $service->getDateRange([
            'range' => 'custom',
            'date_from' => '2026-01-10',
            'date_to' => '2026-01-10',
        ]);

        $this->assertSame('2026-01-09 17:00:00', $dates['from']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-01-10 16:59:59', $dates['to']->format('Y-m-d H:i:s'));
        $this->assertSame('Asia/Ho_Chi_Minh', $dates['local_from']->timezoneName);

        DB::table('HoaDon')->insert([
            'HoaDonID' => 3,
            'DonHangID' => 2,
            'ThanhTien' => 5000,
            'NgayLap' => '2026-01-09 18:00:00',
            'TrangThai' => InvoiceStatus::Paid->value,
        ]);

        $metrics = $service->getKpiMetrics([
            'range' => 'custom',
            'date_from' => '2026-01-10',
            'date_to' => '2026-01-10',
        ]);
        $chart = $service->getRevenueChartData([
            'range' => 'custom',
            'date_from' => '2026-01-10',
            'date_to' => '2026-01-10',
        ]);

        $this->assertEquals(130000, $metrics['total_revenue']);
        $this->assertSame(['10/01'], $chart['labels']);
        $this->assertEquals([130000], $chart['revenue']);
    }

    public function test_all_time_filter_includes_existing_data_and_groups_chart_by_month(): void
    {
        $service = app(ReportsService::class);
        $metrics = $service->getKpiMetrics(['range' => 'all_time']);
        $chart = $service->getRevenueChartData(['range' => 'all_time']);

        $this->assertNull($service->getDateRange(['range' => 'all_time'])['from']);
        $this->assertEquals(125000, $metrics['total_revenue']);
        $this->assertSame(['01/2026'], $chart['labels']);
        $this->assertEquals([125000], $chart['revenue']);
        $this->assertEquals(125000, $service->getKpiMetrics([])['total_revenue']);
    }

    public function test_charts_return_empty_composition_and_zero_revenue_for_empty_periods(): void
    {
        $filters = [
            'range' => 'custom',
            'date_from' => '2026-01-11',
            'date_to' => '2026-01-11',
        ];
        $service = app(ReportsService::class);

        $revenue = $service->getRevenueChartData($filters);
        $composition = $service->getServiceComposition($filters);

        $this->assertSame(['11/01'], $revenue['labels']);
        $this->assertSame([0], $revenue['revenue']);
        $this->assertSame([], $composition['labels']);
        $this->assertSame([], $composition['revenue']);
        $this->assertSame([], $composition['percentages']);
    }

    public function test_top_services_are_ranked_by_paid_revenue_with_quantity_and_unit(): void
    {
        $services = app(ReportsService::class)->getTopServices([
            'range' => 'custom',
            'date_from' => '2026-01-10',
            'date_to' => '2026-01-10',
        ], 1);

        $this->assertCount(1, $services);
        $this->assertSame('Giặt thường', $services[0]->name);
        $this->assertSame('Nhiều ĐVT', $services[0]->unit);
        $this->assertEquals(4, $services[0]->total_qty);
        $this->assertEquals(100000, $services[0]->total_revenue);
    }

    public function test_payment_method_totals_include_only_successful_payments_for_paid_invoices(): void
    {
        $payments = app(ReportsService::class)->getRevenueByPaymentMethod([
            'range' => 'custom',
            'date_from' => '2026-01-10',
            'date_to' => '2026-01-10',
        ]);

        $this->assertCount(2, $payments);
        $this->assertSame('Tiền mặt', $payments[0]->method);
        $this->assertEquals(100000, $payments[0]->total_amount);
        $this->assertSame(1, $payments[0]->transaction_count);
        $this->assertSame('Chuyển khoản', $payments[1]->method);
        $this->assertEquals(25000, $payments[1]->total_amount);
    }

    public function test_recent_orders_eager_load_customer_and_service_details(): void
    {
        $orders = app(ReportsService::class)->getRecentOrders([
            'range' => 'custom',
            'date_from' => '2026-01-10',
            'date_to' => '2026-01-10',
        ]);

        $this->assertCount(4, $orders);
        $latestOrder = $orders->getCollection()->first();
        $this->assertSame('DH-003', $latestOrder->code);
        $this->assertSame('Nguyễn An', $latestOrder->customer->name);
        $this->assertTrue($latestOrder->relationLoaded('chiTietDonHangs'));
    }

    public function test_invalid_range_shape_is_rejected_with_a_validation_error(): void
    {
        $response = $this->actingAs($this->reportOwner())
            ->get('/reports?range%5B%5D=today');

        $response->assertRedirect();
        $response->assertSessionHasErrors('range');
    }

    /**
     * Test-only schema for the in-memory SQLite connection; no migrations or
     * application/Supabase schema are touched.
     */
    private function createTestSchema(): void
    {
        Schema::create('DonHang', function (Blueprint $table): void {
            $table->increments('DonHangID');
            $table->string('MaDonHang');
            $table->integer('KhachHangID');
            $table->string('TrangThai');
            $table->decimal('ThanhTien', 18, 2)->default(0);
            $table->timestamp('NgayTao');
        });

        Schema::create('HoaDon', function (Blueprint $table): void {
            $table->increments('HoaDonID');
            $table->integer('DonHangID');
            $table->decimal('ThanhTien', 18, 2);
            $table->timestamp('NgayLap');
            $table->string('TrangThai');
        });

        Schema::create('ThanhToan', function (Blueprint $table): void {
            $table->increments('ThanhToanID');
            $table->integer('DonHangID');
            $table->decimal('SoTien', 18, 2);
            $table->string('PhuongThuc');
            $table->timestamp('ThoiGian');
            $table->string('TrangThai');
        });

        Schema::create('Booking', function (Blueprint $table): void {
            $table->increments('BookingID');
            $table->timestamp('NgayTao');
            $table->string('TrangThai');
        });

        Schema::create('KhachHang', function (Blueprint $table): void {
            $table->increments('KhachHangID');
            $table->string('HoTen');
        });

        Schema::create('LoaiDichVu', function (Blueprint $table): void {
            $table->increments('LoaiDichVuID');
            $table->string('TenLoaiDichVu');
        });

        Schema::create('DichVu', function (Blueprint $table): void {
            $table->increments('DichVuID');
            $table->integer('LoaiDichVuID');
            $table->string('TenDichVu');
        });

        Schema::create('LoaiDoGiat', function (Blueprint $table): void {
            $table->increments('LoaiDoGiatID');
            $table->string('TenLoaiDoGiat');
        });

        Schema::create('DonViTinh', function (Blueprint $table): void {
            $table->increments('DonViTinhID');
            $table->string('KyHieu');
        });

        Schema::create('ChiTietDonHang', function (Blueprint $table): void {
            $table->increments('ChiTietDonHangID');
            $table->integer('DonHangID');
            $table->integer('DichVuID');
            $table->integer('LoaiDoGiatID');
            $table->integer('DonViTinhID');
            $table->decimal('SoLuong', 10, 2)->nullable();
            $table->decimal('KhoiLuong', 10, 2)->nullable();
            $table->decimal('ThanhTien', 18, 2);
        });
    }

    private function seedReportFixtures(): void
    {
        DB::table('KhachHang')->insert([
            'KhachHangID' => 1,
            'HoTen' => 'Nguyễn An',
        ]);

        DB::table('DonHang')->insert([
            ['DonHangID' => 1, 'MaDonHang' => 'DH-001', 'KhachHangID' => 1, 'TrangThai' => OrderStatus::Paid->value, 'ThanhTien' => 125000, 'NgayTao' => '2026-01-10 09:00:00'],
            ['DonHangID' => 2, 'MaDonHang' => 'DH-002', 'KhachHangID' => 1, 'TrangThai' => OrderStatus::Washing->value, 'ThanhTien' => 50000, 'NgayTao' => '2026-01-10 10:00:00'],
            ['DonHangID' => 4, 'MaDonHang' => 'DH-004', 'KhachHangID' => 1, 'TrangThai' => OrderStatus::Washed->value, 'ThanhTien' => 50000, 'NgayTao' => '2026-01-10 10:30:00'],
            ['DonHangID' => 3, 'MaDonHang' => 'DH-003', 'KhachHangID' => 1, 'TrangThai' => OrderStatus::Cancelled->value, 'ThanhTien' => 30000, 'NgayTao' => '2026-01-10 11:00:00'],
        ]);

        DB::table('HoaDon')->insert([
            ['HoaDonID' => 1, 'DonHangID' => 1, 'ThanhTien' => 125000, 'NgayLap' => '2026-01-10 12:00:00', 'TrangThai' => InvoiceStatus::Paid->value],
            ['HoaDonID' => 2, 'DonHangID' => 3, 'ThanhTien' => 30000, 'NgayLap' => '2026-01-10 12:00:00', 'TrangThai' => InvoiceStatus::Cancelled->value],
        ]);

        DB::table('ThanhToan')->insert([
            ['ThanhToanID' => 1, 'DonHangID' => 1, 'SoTien' => 100000, 'PhuongThuc' => 'Tiền mặt', 'ThoiGian' => '2026-01-10 12:05:00', 'TrangThai' => 'Thành công'],
            ['ThanhToanID' => 2, 'DonHangID' => 1, 'SoTien' => 25000, 'PhuongThuc' => 'Chuyển khoản', 'ThoiGian' => '2026-01-10 12:06:00', 'TrangThai' => 'Thành công'],
            ['ThanhToanID' => 3, 'DonHangID' => 3, 'SoTien' => 30000, 'PhuongThuc' => 'Tiền mặt', 'ThoiGian' => '2026-01-10 12:07:00', 'TrangThai' => 'Thành công'],
        ]);

        DB::table('Booking')->insert([
            ['BookingID' => 1, 'NgayTao' => '2026-01-10 08:00:00', 'TrangThai' => BookingStatus::Pending->value],
            ['BookingID' => 2, 'NgayTao' => '2026-01-10 08:30:00', 'TrangThai' => BookingStatus::Confirmed->value],
        ]);

        DB::table('LoaiDichVu')->insert([
            ['LoaiDichVuID' => 1, 'TenLoaiDichVu' => 'Giặt sấy'],
            ['LoaiDichVuID' => 2, 'TenLoaiDichVu' => 'Giặt hấp'],
        ]);

        DB::table('DichVu')->insert([
            ['DichVuID' => 1, 'LoaiDichVuID' => 1, 'TenDichVu' => 'Giặt thường'],
            ['DichVuID' => 2, 'LoaiDichVuID' => 2, 'TenDichVu' => 'Giặt hấp'],
        ]);

        DB::table('LoaiDoGiat')->insert([
            ['LoaiDoGiatID' => 1, 'TenLoaiDoGiat' => 'Áo'],
            ['LoaiDoGiatID' => 2, 'TenLoaiDoGiat' => 'Quần'],
        ]);

        DB::table('DonViTinh')->insert([
            ['DonViTinhID' => 1, 'KyHieu' => 'kg'],
            ['DonViTinhID' => 2, 'KyHieu' => 'cái'],
        ]);

        DB::table('ChiTietDonHang')->insert([
            ['ChiTietDonHangID' => 1, 'DonHangID' => 1, 'DichVuID' => 1, 'LoaiDoGiatID' => 1, 'DonViTinhID' => 1, 'SoLuong' => null, 'KhoiLuong' => 3, 'ThanhTien' => 100000],
            ['ChiTietDonHangID' => 2, 'DonHangID' => 1, 'DichVuID' => 2, 'LoaiDoGiatID' => 2, 'DonViTinhID' => 2, 'SoLuong' => 1, 'KhoiLuong' => null, 'ThanhTien' => 25000],
            ['ChiTietDonHangID' => 3, 'DonHangID' => 1, 'DichVuID' => 1, 'LoaiDoGiatID' => 1, 'DonViTinhID' => 2, 'SoLuong' => 1, 'KhoiLuong' => null, 'ThanhTien' => 0],
        ]);
    }

    private function reportOwner(): User
    {
        $owner = Mockery::mock(User::class)->makePartial();
        $owner->shouldReceive('isActive')->andReturn(true);
        $owner->shouldReceive('isOwner')->andReturn(true);
        $owner->shouldReceive('roleSlug')->andReturn('owner');
        $owner->shouldReceive('canPermission')->with('reports.view')->andReturn(true);

        return $owner;
    }
}
