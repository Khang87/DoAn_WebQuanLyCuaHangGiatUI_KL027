<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Admin\DashboardController;
use App\Models\User;
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

        Schema::create('NhatKyHeThong', function (Blueprint $table): void {
            $table->increments('NhatKyID');
            $table->string('HanhDong');
            $table->string('BangDuLieu');
            $table->unsignedInteger('BanGhiID');
            $table->json('DuLieuCu')->nullable();
            $table->json('DuLieuMoi')->nullable();
            $table->dateTime('ThoiGian');
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
            $table->string('AvatarUrl')->nullable();
            $table->dateTime('NgayTao')->nullable();
        });

        Schema::create('TaiKhoan', function (Blueprint $table): void {
            $table->increments('TaiKhoanID');
            $table->unsignedInteger('KhachHangID');
            $table->string('AvatarURL')->nullable();
            $table->string('MatKhau');
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
            $table->unsignedInteger('DonHangID');
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

        config(['app.timezone' => 'Asia/Ho_Chi_Minh']);
        Carbon::setTestNow(Carbon::parse('2026-10-04 20:00:00', 'Asia/Ho_Chi_Minh'));
    }

    protected function tearDown(): void
    {
        if (
            config('database.default') === 'sqlite'
            && config('database.connections.sqlite.database') === ':memory:'
        ) {
            DB::disableQueryLog();
            foreach (['DanhGia', 'ChiTietDonHang', 'DichVu', 'NhanVien', 'TaiKhoan', 'KhachHang', 'ThanhToan', 'NhatKyHeThong', 'HoaDon', 'DonHang'] as $table) {
                Schema::dropIfExists($table);
            }
        }
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_revenue_chart_aggregates_each_filter_in_a_single_query(): void
    {
        $hourly = array_fill(0, 24, 0);
        $hourly[16] = 500;
        $hourly[19] = 700;
        $periods = [
            'today' => [array_map(fn (int $hour): string => sprintf('%02d:00', $hour), range(0, 23)), $hourly],
            'this_week' => [['28/09', '29/09', '30/09', '01/10', '02/10', '03/10', '04/10'], [0, 0, 0, 0, 300, 0, 1200]],
            'this_month' => [['01/10', '02/10', '03/10', '04/10'], [0, 300, 0, 1200]],
            'all_time' => [['10/2026'], [1500]],
        ];

        foreach ($periods as $filter => [$labels, $values]) {
            $chart = $this->chart($filter);
            $dashboard = $this->dashboard($filter);

            $this->assertSame($filter, $chart['filter']);
            $this->assertNotEmpty($chart['filter_label']);
            $this->assertSame($labels, $chart['labels'], $filter);
            $this->assertEquals($values, $chart['data'], $filter);
            $this->assertSame($filter, $dashboard['dashboardFilter']);
            $this->assertEquals($chart, $dashboard['revenueChartData']);
            $this->assertSame((float) array_sum($values), $dashboard['totalRevenue']);
            $this->assertIsString($dashboard['periodLabel']);
            $this->assertNotEmpty($dashboard['periodLabel']);
            $this->assertIsString($dashboard['periodDescription']);
            $this->assertNotEmpty($dashboard['periodDescription']);
            $this->assertMetricTotals($dashboard);

            foreach (['revenueFilter', 'todayRevenue', 'weekRevenue', 'monthRevenue', 'todayRevenueChange', 'weekRevenueChange', 'monthRevenueChange'] as $obsoleteField) {
                $this->assertArrayNotHasKey($obsoleteField, $dashboard);
            }
        }
    }

    public function test_default_and_invalid_filters_fall_back_to_this_week_everywhere(): void
    {
        foreach ([null, '', 'unsupported', '7_days', 'this_year', ['today']] as $filter) {
            $chart = $this->chart($filter);
            $dashboard = $this->dashboard($filter);

            $this->assertSame('this_week', $chart['filter']);
            $this->assertSame('this_week', $dashboard['dashboardFilter']);
            $this->assertEquals($chart, $dashboard['revenueChartData']);
            $this->assertSame(['28/09', '29/09', '30/09', '01/10', '02/10', '03/10', '04/10'], $chart['labels']);
            $this->assertEquals([0, 0, 0, 0, 300, 0, 1200], $chart['data']);
            $this->assertSame(1500.0, $dashboard['totalRevenue']);
        }
    }

    public function test_dashboard_metrics_and_charts_are_zero_when_empty(): void
    {
        $this->clearDashboardData();

        foreach (['today' => 24, 'this_week' => 7, 'this_month' => 4, 'all_time' => 0] as $filter => $buckets) {
            $dashboard = $this->dashboard($filter);
            $chart = $this->chart($filter);

            $this->assertSame($filter, $dashboard['dashboardFilter']);
            $this->assertEmptyMetrics($dashboard);
            $this->assertEquals($chart, $dashboard['revenueChartData']);
            $this->assertCount($buckets, $chart['labels']);
            $this->assertEquals(array_fill(0, $buckets, 0), $chart['data']);
            if ($filter === 'all_time') {
                $this->assertSame([], $chart['labels']);
                $this->assertSame([], $chart['data']);
            }
        }
    }

    public function test_empty_periods_do_not_include_historical_activity(): void
    {
        $this->clearDashboardData();
        $this->seedActivity(1, '2025-10-04 09:00:00', 100);
        DB::table('DichVu')->insert(['DichVuID' => 1, 'TenDichVu' => 'Giặt thường']);
        DB::table('ChiTietDonHang')->insert(['DonHangID' => 1, 'DichVuID' => 1]);

        foreach (['today', 'this_week', 'this_month'] as $filter) {
            $this->assertEmptyMetrics($this->dashboard($filter));
            $this->assertEquals(0, array_sum($this->chart($filter)['data']));
        }

        $allTime = $this->dashboard('all_time');
        $this->assertSame(100.0, $allTime['totalRevenue']);
        $this->assertSame(1, $allTime['totalOrders']);
        $this->assertSame(1, $allTime['totalCustomers']);
        $this->assertSame(1, $allTime['totalReviews']);
    }

    public function test_all_metrics_and_latest_rows_share_the_selected_period_and_services_count_distinct_orders(): void
    {
        $this->clearDashboardData();
        $activities = [
            [1, '2025-10-04 09:00:00', 100, 1, OrderStatus::Delivered],
            [2, '2026-09-27 16:59:59', 200, 2, OrderStatus::Pending],
            [3, '2026-09-27 17:00:00', 300, 5, OrderStatus::Received],
            [4, '2026-09-30 17:00:00', 400, 1, OrderStatus::Cancelled],
            [5, '2026-10-03 17:00:00', 500, 5, OrderStatus::Paid],
            [6, '2026-10-04 16:59:59', 600, 3, OrderStatus::Washing],
            [7, '2026-10-04 17:00:00', 700, 4, OrderStatus::Delivering],
        ];
        DB::table('DichVu')->insert([
            ['DichVuID' => 1, 'TenDichVu' => 'Giặt thường'],
            ['DichVuID' => 2, 'TenDichVu' => 'Chưa được đặt'],
            ['DichVuID' => 3, 'TenDichVu' => 'Dịch vụ cũ'],
        ]);
        foreach ($activities as $activity) {
            $this->seedActivity(...$activity);
            DB::table('ChiTietDonHang')->insert(['DonHangID' => $activity[0], 'DichVuID' => 1]);
        }
        DB::table('ChiTietDonHang')->insert([
            ['DonHangID' => 5, 'DichVuID' => 1],
            ['DonHangID' => 5, 'DichVuID' => 1],
            ['DonHangID' => 1, 'DichVuID' => 3],
        ]);
        DB::table('DanhGia')->insert([
            'DanhGiaID' => 99, 'SoSao' => 1, 'TrangThai' => 'Ẩn', 'NgayDanhGia' => '2026-10-04 16:59:59',
        ]);
        $periods = [
            'today' => [[6, 5], 1100, 4.0, ['completed' => 1, 'processing' => 1, 'cancelled' => 0]],
            'this_week' => [[6, 5, 4, 3], 1800, 3.5, ['completed' => 1, 'processing' => 2, 'cancelled' => 1]],
            'this_month' => [[6, 5, 4], 1500, 3.0, ['completed' => 1, 'processing' => 1, 'cancelled' => 1]],
            'all_time' => [[7, 6, 5, 4, 3, 2, 1], 2800, 3.0, ['completed' => 2, 'processing' => 4, 'cancelled' => 1]],
        ];

        foreach ($periods as $filter => [$ids, $revenue, $rating, $statuses]) {
            $dashboard = $this->dashboard($filter);

            $this->assertSame($filter, $dashboard['dashboardFilter']);
            $this->assertSame((float) $revenue, $dashboard['totalRevenue'], $filter);
            $this->assertSame(count($ids), $dashboard['totalOrders'], $filter);
            $this->assertSame(count($ids), $dashboard['totalCustomers'], $filter);
            $this->assertSame(count($ids), $dashboard['totalReviews'], $filter);
            $this->assertSame($rating, $dashboard['averageRating'], $filter);
            $this->assertSame($ids, $dashboard['recentOrders']->pluck('DonHangID')->all(), $filter);
            $this->assertSame($ids, $dashboard['latestReviews']->pluck('DanhGiaID')->all(), $filter);
            $this->assertSame($statuses, $dashboard['statusCounts'], $filter);
            $services = ['Giặt thường' => count($ids)];
            if ($filter === 'all_time') {
                $services['Dịch vụ cũ'] = 1;
            }
            $this->assertEquals($services, $dashboard['topServices']->pluck('orders_count', 'name')->all(), $filter);
            $this->assertEquals($this->chart($filter), $dashboard['revenueChartData']);
            $this->assertMetricTotals($dashboard);
        }
    }

    public function test_local_midnight_bounds_are_inclusive_at_start_and_exclusive_at_next_midnight(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-04 00:30:00', 'Asia/Ho_Chi_Minh'));
        $starts = [
            'today' => '2026-10-03 17:00:00',
            'this_week' => '2026-09-27 17:00:00',
            'this_month' => '2026-09-30 17:00:00',
        ];

        foreach ($starts as $filter => $start) {
            $this->clearDashboardData();
            $this->seedActivity(1, Carbon::parse($start, 'UTC')->subSecond()->toDateTimeString(), 10, 1);
            $this->seedActivity(2, $start, 20, 5);
            $this->seedActivity(3, '2026-10-04 16:59:59.500000', 30, 3);
            $this->seedActivity(4, '2026-10-04 17:00:00', 40, 1);
            DB::table('DichVu')->insert(['DichVuID' => 1, 'TenDichVu' => 'Giặt thường']);
            foreach (range(1, 4) as $id) {
                DB::table('ChiTietDonHang')->insert(['DonHangID' => $id, 'DichVuID' => 1]);
            }

            $dashboard = $this->dashboard($filter);
            $chart = $this->chart($filter);

            $this->assertSame(50.0, $dashboard['totalRevenue'], $filter);
            $this->assertSame(2, $dashboard['totalOrders'], $filter);
            $this->assertSame(2, $dashboard['totalCustomers'], $filter);
            $this->assertSame(2, $dashboard['totalReviews'], $filter);
            $this->assertSame(4.0, $dashboard['averageRating'], $filter);
            $this->assertSame([3, 2], $dashboard['recentOrders']->pluck('DonHangID')->all(), $filter);
            $this->assertSame([3, 2], $dashboard['latestReviews']->pluck('DanhGiaID')->all(), $filter);
            $this->assertEquals(['Giặt thường' => 2], $dashboard['topServices']->pluck('orders_count', 'name')->all(), $filter);
            $this->assertEquals(20, $chart['data'][0], $filter);
            $this->assertEquals(30, $chart['data'][array_key_last($chart['data'])], $filter);
            $this->assertEquals($chart, $dashboard['revenueChartData']);
            $this->assertMetricTotals($dashboard);
        }
    }

    public function test_this_week_starts_on_monday_across_month_and_year_boundaries_not_a_rolling_week(): void
    {
        $periods = [
            '2026-06-01 12:00:00' => ['01/06'],
            '2026-10-01 12:00:00' => ['28/09', '29/09', '30/09', '01/10'],
            '2027-01-01 12:00:00' => ['28/12', '29/12', '30/12', '31/12', '01/01'],
            '2027-01-04 12:00:00' => ['04/01'],
        ];

        foreach ($periods as $now => $labels) {
            $this->clearDashboardData();
            $today = Carbon::parse($now, 'Asia/Ho_Chi_Minh');
            Carbon::setTestNow($today);
            $start = $today->copy()->startOfWeek(Carbon::MONDAY)->utc();
            $this->seedActivity(1, $start->copy()->subSecond()->toDateTimeString(), 10);
            $this->seedActivity(2, $start->toDateTimeString(), 20);
            $this->seedActivity(3, $today->copy()->utc()->toDateTimeString(), 30);
            $this->seedActivity(4, $today->copy()->addDay()->startOfDay()->utc()->toDateTimeString(), 40);

            $chart = $this->chart('this_week');
            $dashboard = $this->dashboard('this_week');
            $values = array_fill(0, count($labels), 0);
            $values[0] = 20;
            $values[array_key_last($values)] += 30;

            $this->assertSame($labels, $chart['labels'], $now);
            $this->assertEquals($values, $chart['data'], $now);
            $this->assertSame(50.0, $dashboard['totalRevenue'], $now);
            $this->assertSame(2, $dashboard['totalOrders'], $now);
            $this->assertSame(2, $dashboard['totalCustomers'], $now);
            $this->assertSame(2, $dashboard['totalReviews'], $now);
            $this->assertSame([3, 2], $dashboard['recentOrders']->pluck('DonHangID')->all(), $now);
            $this->assertSame([3, 2], $dashboard['latestReviews']->pluck('DanhGiaID')->all(), $now);
            $this->assertEquals($chart, $dashboard['revenueChartData']);
            $this->assertMetricTotals($dashboard);
        }
    }

    public function test_all_time_spans_collected_payment_months_with_years_and_zero_filled_gaps(): void
    {
        DB::table('ThanhToan')->delete();
        $payments = [
            [1, '2018-01-01 00:00:00', 9000, PaymentStatus::Pending],
            [2, '2024-09-30 17:00:00', 100, PaymentStatus::Paid],
            [3, '2025-09-30 17:00:00', 200, PaymentStatus::Refunded],
            [4, '2026-10-31 16:59:59', 300, PaymentStatus::Paid],
            [5, '2027-01-01 00:00:00', 400, PaymentStatus::Paid],
            [6, '2030-01-01 00:00:00', 8000, PaymentStatus::Failed],
        ];
        foreach ($payments as [$id, $timestamp, $amount, $status]) {
            $this->seedPayment($id, 1, $timestamp, $amount, $status);
        }
        $labels = [];
        for ($month = Carbon::parse('2024-10-01', 'Asia/Ho_Chi_Minh'); $month->lte('2027-01-01'); $month->addMonth()) {
            $labels[] = $month->format('m/Y');
        }
        $values = array_fill(0, 28, 0);
        $values[0] = 100;
        $values[12] = 200;
        $values[24] = 300;
        $values[27] = 400;

        $chart = $this->chart('all_time');
        $dashboard = $this->dashboard('all_time');

        $this->assertSame($labels, $chart['labels']);
        $this->assertEquals($values, $chart['data']);
        $this->assertSame(1000.0, $dashboard['totalRevenue']);
        $this->assertEquals($chart, $dashboard['revenueChartData']);
        $this->assertMetricTotals($dashboard);
    }

    public function test_all_time_is_empty_when_only_noncollected_payments_exist(): void
    {
        DB::table('ThanhToan')->delete();
        $this->seedPayment(1, 1, '2024-01-01 00:00:00', 1000, PaymentStatus::Pending);
        $this->seedPayment(2, 2, '2027-01-01 00:00:00', 2000, PaymentStatus::Failed);

        $chart = $this->chart('all_time');
        $dashboard = $this->dashboard('all_time');

        $this->assertSame('all_time', $chart['filter']);
        $this->assertSame([], $chart['labels']);
        $this->assertSame([], $chart['data']);
        $this->assertSame(0.0, $dashboard['totalRevenue']);
        $this->assertSame(4, $dashboard['totalOrders']);
        $this->assertEquals($chart, $dashboard['revenueChartData']);
    }

    public function test_payments_customers_and_reviews_use_their_own_timestamps_not_the_order_date(): void
    {
        $this->clearDashboardData();
        $this->seedActivity(1, '2026-06-01 09:00:00', 100);
        $this->seedActivity(2, '2026-10-04 09:00:00', 200);
        DB::table('DonHang')->where('DonHangID', 2)->update(['KhachHangID' => 1]);
        DB::table('DanhGia')->where('DanhGiaID', 1)->update(['NgayDanhGia' => '2026-10-04 10:00:00']);
        DB::table('DanhGia')->where('DanhGiaID', 2)->update(['NgayDanhGia' => '2026-10-03 10:00:00', 'SoSao' => 2]);
        DB::table('DanhGia')->insert([
            ['DanhGiaID' => 3, 'SoSao' => 1, 'TrangThai' => 'Ẩn', 'NgayDanhGia' => '2026-10-04 11:00:00'],
            ['DanhGiaID' => 4, 'SoSao' => 1, 'TrangThai' => 'Hiển thị', 'NgayDanhGia' => '2026-10-04 17:00:00'],
        ]);
        DB::table('ThanhToan')->delete();
        $this->seedPayment(1, 1, '2026-10-04 09:00:00', 50);
        $this->seedPayment(2, 1, '2026-10-04 10:00:00', 25, PaymentStatus::Refunded);
        $this->seedPayment(3, 2, '2026-10-03 09:00:00', 80);
        $this->seedPayment(4, 2, '2026-10-04 09:00:00', 1000, PaymentStatus::Pending);
        $this->seedPayment(5, 2, '2026-10-04 09:00:00', 2000, PaymentStatus::Failed);
        $this->seedPayment(6, 1, '2026-10-04 17:00:00', 5000);
        DB::table('HoaDon')->insert([
            'DonHangID' => 2, 'TrangThai' => InvoiceStatus::Paid->value, 'ThanhTien' => 9999, 'NgayLap' => '2026-10-04 09:00:00',
        ]);

        $dashboard = $this->dashboard('today');
        $chart = $this->chart('today');

        $this->assertSame(75.0, $dashboard['totalRevenue']);
        $this->assertSame(1, $dashboard['totalOrders']);
        $this->assertSame(1, $dashboard['totalCustomers']);
        $this->assertSame(4.0, $dashboard['averageRating']);
        $this->assertSame(1, $dashboard['totalReviews']);
        $this->assertSame([2], $dashboard['recentOrders']->pluck('DonHangID')->all());
        $this->assertSame([1], $dashboard['latestReviews']->pluck('DanhGiaID')->all());
        $this->assertEquals(50, $chart['data'][16]);
        $this->assertEquals(25, $chart['data'][17]);
        $this->assertEquals($chart, $dashboard['revenueChartData']);
        $this->assertMetricTotals($dashboard);
    }

    public function test_legacy_paid_orders_use_operational_history_in_both_status_counts_and_distribution(): void
    {
        foreach ([5 => OrderStatus::Pending, 6 => OrderStatus::Received, 7 => OrderStatus::Washed, 8 => OrderStatus::Delivering, 9 => OrderStatus::Delivered, 10 => OrderStatus::Cancelled] as $id => $status) {
            DB::table('DonHang')->insert([
                'DonHangID' => $id, 'TrangThai' => $status->value, 'ThanhTien' => 100, 'NgayTao' => '2026-10-04 09:00:00',
            ]);
        }
        foreach ([OrderStatus::Delivered, OrderStatus::Washing] as $sequence => $previousStatus) {
            DB::table('NhatKyHeThong')->insert([
                'HanhDong' => 'Thay đổi trạng thái đơn hàng',
                'BangDuLieu' => 'DonHang',
                'BanGhiID' => 4,
                'DuLieuCu' => json_encode(['TrangThai' => $previousStatus->value]),
                'DuLieuMoi' => json_encode(['TrangThai' => OrderStatus::Paid->value]),
                'ThoiGian' => '2026-10-0'.($sequence + 2).' 10:00:00',
            ]);
        }

        $dashboard = $this->dashboard('this_week');

        $this->assertSame(10, $dashboard['totalOrders']);
        $this->assertSame(['completed' => 2, 'processing' => 7, 'cancelled' => 1], $dashboard['statusCounts']);
        $this->assertEquals([
            OrderStatus::Pending->value => 1,
            OrderStatus::Received->value => 1,
            OrderStatus::Washing->value => 3,
            OrderStatus::Washed->value => 1,
            OrderStatus::Delivering->value => 1,
            OrderStatus::Delivered->value => 2,
            OrderStatus::Cancelled->value => 1,
        ], $dashboard['statusDistribution']->all());
        $this->assertFalse($dashboard['statusDistribution']->has(OrderStatus::Paid->value));
        $this->assertMetricTotals($dashboard);
        $paidOrder = $dashboard['recentOrders']->firstWhere('DonHangID', 1);
        $this->assertTrue($paidOrder->isPaid());
        $this->assertSame(OrderStatus::Delivered, $paidOrder->operationalStatusEnum());
        $this->assertSame(OrderStatus::Washing, $dashboard['recentOrders']->firstWhere('DonHangID', 4)->operationalStatusEnum());
        $statusBadges = view('components.admin.order-status-badges', [
            'order' => $paidOrder,
            'isPaid' => $paidOrder->isPaid(),
            'isReceiving' => false,
            'compact' => true,
        ])->render();
        $this->assertStringContainsString('Đã giao', $statusBadges);
        $this->assertStringContainsString('Đã thanh toán', $statusBadges);
    }

    public function test_latest_reviews_are_limited_to_ten_visible_rows_without_limiting_review_metrics(): void
    {
        foreach (range(1, 12) as $id) {
            DB::table('DanhGia')->insert([
                'DanhGiaID' => $id, 'SoSao' => 4, 'TrangThai' => 'Hiển thị',
                'NgayDanhGia' => sprintf('2026-10-04 05:%02d:00', $id),
            ]);
        }
        DB::table('DanhGia')->insert([
            ['DanhGiaID' => 13, 'SoSao' => 1, 'TrangThai' => 'Ẩn', 'NgayDanhGia' => '2026-10-04 12:00:00'],
            ['DanhGiaID' => 14, 'SoSao' => 1, 'TrangThai' => 'Hiển thị', 'NgayDanhGia' => '2026-10-04 17:00:00'],
            ['DanhGiaID' => 15, 'SoSao' => 1, 'TrangThai' => 'Hiển thị', 'NgayDanhGia' => '2026-10-03 16:59:59'],
        ]);

        $dashboard = $this->dashboard('today');

        $this->assertSame(12, $dashboard['totalReviews']);
        $this->assertSame(4.0, $dashboard['averageRating']);
        $this->assertSame(range(12, 3), $dashboard['latestReviews']->pluck('DanhGiaID')->all());
    }

    public function test_recent_orders_display_customer_avatars_without_per_row_queries(): void
    {
        $staff = \Mockery::mock(User::class)->makePartial();
        $staff->shouldReceive('isActive')->andReturn(true);
        $staff->shouldReceive('isOwner')->andReturn(false);
        $staff->shouldReceive('canPermission')->andReturnUsing(
            fn (string $permission): bool => $permission === 'orders.view',
        );
        $this->actingAs($staff);

        DB::table('KhachHang')->insert([
            ['KhachHangID' => 1, 'HoTen' => 'Khách ảnh hồ sơ', 'AvatarUrl' => 'https://example.test/profile.jpg'],
            ['KhachHangID' => 2, 'HoTen' => 'Khách ảnh tài khoản', 'AvatarUrl' => null],
            ['KhachHangID' => 3, 'HoTen' => 'Đặng An', 'AvatarUrl' => null],
        ]);
        DB::table('TaiKhoan')->insert([
            ['TaiKhoanID' => 11, 'KhachHangID' => 1, 'AvatarURL' => 'https://example.test/unused.jpg', 'MatKhau' => 'test-only'],
            ['TaiKhoanID' => 12, 'KhachHangID' => 2, 'AvatarURL' => 'https://example.test/account.jpg', 'MatKhau' => 'test-only'],
        ]);
        foreach ([1, 2, 3] as $id) {
            DB::table('DonHang')->where('DonHangID', $id)->update(['KhachHangID' => $id]);
        }
        DB::flushQueryLog();
        DB::enableQueryLog();

        try {
            $view = app(DashboardController::class)->index(Request::create('/admin/dashboard', 'GET'));
            $orders = $view->getData()['recentOrders'];
            $queries = DB::getQueryLog();
            $accountQueries = array_filter($queries, fn (array $query): bool => str_contains($query['query'], 'from "TaiKhoan"'));
            $this->assertCount(1, $accountQueries);

            foreach ($orders as $order) {
                $this->assertTrue($order->relationLoaded('khachHang'));
                if ($order->khachHang) {
                    $this->assertTrue($order->khachHang->relationLoaded('taiKhoan'));
                    if ($order->khachHang->taiKhoan) {
                        $this->assertArrayNotHasKey('MatKhau', $order->khachHang->taiKhoan->getAttributes());
                    }
                }
            }
            $this->assertNull($orders->firstWhere('DonHangID', 3)->khachHang->taiKhoan);
            $this->assertNull($orders->firstWhere('DonHangID', 4)->khachHang);

            $html = $view->render();
            $this->assertStringContainsString('src="https://example.test/profile.jpg"', $html);
            $this->assertStringContainsString('src="https://example.test/account.jpg"', $html);
            $this->assertStringNotContainsString('src="https://example.test/unused.jpg"', $html);
            $this->assertSame(4, substr_count($html, 'width: 40px; height: 40px;'));
            $this->assertMatchesRegularExpression('/aria-label="Ảnh đại diện của Đặng An">\s*<span aria-hidden="true"\s*>\s*Đ\s*<\/span>/u', $html);
            $this->assertStringContainsString('aria-label="Ảnh đại diện khách hàng"', $html);
            $this->assertCount(count($queries), DB::getQueryLog(), 'Rendering customer avatars must not add queries.');
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
    }

    public function test_dashboard_shows_up_to_fifteen_recent_orders(): void
    {
        $orders = [];
        for ($sequence = 1; $sequence <= 16; $sequence++) {
            $orders[] = [
                'DonHangID' => 100 + $sequence,
                'TrangThai' => OrderStatus::Received->value,
                'ThanhTien' => 1000,
                'NgayTao' => Carbon::now()->subMinutes($sequence)->toDateTimeString(),
            ];
        }

        DB::table('DonHang')->insert($orders);

        $dashboard = app(DashboardController::class)->index(
            Request::create('/admin/dashboard', 'GET', ['filter' => 'all_time'])
        )->getData();

        $this->assertCount(15, $dashboard['recentOrders']);
        $this->assertSame(115, $dashboard['recentOrders']->last()->DonHangID);
    }

    private function dashboard(mixed $filter): array
    {
        return app(DashboardController::class)->index(
            Request::create('/admin/dashboard', 'GET', ['filter' => $filter])
        )->getData();
    }

    private function chart(mixed $filter): array
    {
        return app(DashboardController::class)->getRevenueChartData(
            Request::create('/admin/dashboard/revenue-chart', 'GET', ['filter' => $filter])
        )->getData(true);
    }

    private function clearDashboardData(): void
    {
        foreach (['DanhGia', 'ChiTietDonHang', 'DichVu', 'TaiKhoan', 'KhachHang', 'ThanhToan', 'NhatKyHeThong', 'HoaDon', 'DonHang'] as $table) {
            DB::table($table)->delete();
        }
    }

    private function seedActivity(
        int $id,
        string $utcTimestamp,
        float $amount,
        ?int $stars = null,
        OrderStatus $status = OrderStatus::Received,
    ): void {
        $customerId = DB::table('KhachHang')->insertGetId([
            'HoTen' => 'Khách '.$id,
            'NgayTao' => $utcTimestamp,
        ]);

        DB::table('DonHang')->insert([
            'DonHangID' => $id,
            'TrangThai' => $status->value,
            'ThanhTien' => $amount,
            'NgayTao' => $utcTimestamp,
            'KhachHangID' => $customerId,
        ]);
        DB::table('DanhGia')->insert([
            'DanhGiaID' => $id,
            'DonHangID' => $id,
            'KhachHangID' => $customerId,
            'SoSao' => $stars ?? 4,
            'TrangThai' => 'Hiển thị',
            'NgayDanhGia' => $utcTimestamp,
        ]);
        DB::table('ThanhToan')->insert([
            'DonHangID' => $id,
            'SoTien' => $amount,
            'ThoiGian' => $utcTimestamp,
            'TrangThai' => PaymentStatus::Paid->value,
        ]);
    }

    private function seedPayment(
        int $id,
        int $orderId,
        string $utcTimestamp,
        float $amount,
        PaymentStatus $status = PaymentStatus::Paid,
    ): void {
        DB::table('ThanhToan')->insert([
            'ThanhToanID' => $id,
            'DonHangID' => $orderId,
            'SoTien' => $amount,
            'ThoiGian' => $utcTimestamp,
            'TrangThai' => $status->value,
        ]);
    }

    private function assertMetricTotals(array $dashboard): void
    {
        $this->assertSame((float) array_sum($dashboard['revenueChartData']['data']), $dashboard['totalRevenue']);
        $this->assertSame($dashboard['statusDistribution']->sum(), $dashboard['totalOrders']);
        $this->assertSame(array_sum($dashboard['statusCounts']), $dashboard['totalOrders']);
        $this->assertLessThanOrEqual(15, $dashboard['recentOrders']->count());
        $this->assertLessThanOrEqual(10, $dashboard['latestReviews']->count());
    }

    private function assertEmptyMetrics(array $dashboard): void
    {
        $this->assertSame(0.0, $dashboard['totalRevenue']);
        $this->assertSame(0, $dashboard['totalOrders']);
        $this->assertSame(['completed' => 0, 'processing' => 0, 'cancelled' => 0], $dashboard['statusCounts']);
        $this->assertSame(0, $dashboard['totalCustomers']);
        $this->assertSame(0, $dashboard['totalReviews']);
        $this->assertSame(0.0, $dashboard['averageRating']);
        $this->assertTrue($dashboard['recentOrders']->isEmpty());
        $this->assertTrue($dashboard['latestReviews']->isEmpty());
        $this->assertTrue($dashboard['topServices']->isEmpty());
    }
}
