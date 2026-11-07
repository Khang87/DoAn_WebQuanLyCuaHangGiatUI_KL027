<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\User;
use App\Services\ReportsService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class ReportsServiceTest extends TestCase
{
    private const TEST_TABLES = [
        'DonHang',
        'HoaDon',
        'NhatKyHeThong',
        'ThanhToan',
        'Booking',
        'KhachHang',
        'TaiKhoan',
        'LoaiDichVu',
        'DichVu',
        'LoaiDoGiat',
        'DonViTinh',
        'ChiTietDonHang',
        'ThongBao',
    ];

    private bool $testSchemaCreated = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (
            config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:'
        ) {
            $this->fail('ReportsService tests require the isolated SQLite in-memory connection.');
        }

        $this->createTestSchema();
        $this->testSchemaCreated = true;
        $this->seedReportFixtures();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        if ($this->testSchemaCreated) {
            foreach (array_reverse(self::TEST_TABLES) as $table) {
                Schema::dropIfExists($table);
            }
        }

        parent::tearDown();
    }

    public function test_kpis_use_successful_collected_payment_revenue_and_count_new_bookings(): void
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
            $metrics['completed_orders'] + $metrics['processing_orders'] + $metrics['cancelled_orders']
        );
        $this->assertSame(1, $metrics['new_bookings']);
        $this->assertEquals(255000, $metrics['total_revenue']);
        $this->assertEquals(255000, $metrics['avg_order_value']);
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
        $metrics = $service->getKpiMetrics($filters);
        $this->assertCount(5, DB::connection()->getQueryLog());
        $this->assertSame(
            $metrics['total_orders'],
            $metrics['completed_orders'] + $metrics['processing_orders'] + $metrics['cancelled_orders']
        );

        DB::connection()->flushQueryLog();
        $chart = $service->getRevenueChartData($filters);
        $this->assertCount(1, DB::connection()->getQueryLog());
        $this->assertSame(['10/01/2026'], $chart['labels']);
        $this->assertEquals([255000], $chart['revenue']);
    }

    public function test_charts_use_successful_payment_dates_and_calculate_service_shares(): void
    {
        $service = app(ReportsService::class);
        $filters = [
            'range' => 'custom',
            'date_from' => '2026-01-10',
            'date_to' => '2026-01-10',
        ];

        $chart = $service->getRevenueChartData($filters);
        $composition = $service->getServiceComposition($filters);

        $this->assertSame(['10/01/2026'], $chart['labels']);
        $this->assertEquals([255000], $chart['revenue']);
        $this->assertSame(['Giặt sấy', 'Giặt hấp'], $composition['labels']);
        $this->assertEquals([100000, 25000], $composition['revenue']);
        $this->assertEquals([80, 20], $composition['percentages']);
    }

    public function test_revenue_trend_keeps_daily_amounts_in_order_and_zero_fills_days_without_payments(): void
    {
        DB::table('ThanhToan')->insert([
            ['DonHangID' => 2, 'SoTien' => 400000, 'PhuongThuc' => 'Tiền mặt', 'ThoiGian' => '2026-01-11 05:00:00', 'TrangThai' => PaymentStatus::Paid->value],
            ['DonHangID' => 2, 'SoTien' => 100000, 'PhuongThuc' => 'Tiền mặt', 'ThoiGian' => '2026-01-12 05:00:00', 'TrangThai' => PaymentStatus::Paid->value],
            ['DonHangID' => 2, 'SoTien' => 900000, 'PhuongThuc' => 'Tiền mặt', 'ThoiGian' => '2026-01-13 05:00:00', 'TrangThai' => PaymentStatus::Pending->value],
            ['DonHangID' => 2, 'SoTien' => 800000, 'PhuongThuc' => 'Tiền mặt', 'ThoiGian' => '2026-01-13 06:00:00', 'TrangThai' => PaymentStatus::Failed->value],
        ]);
        $filters = ['range' => 'custom', 'date_from' => '2026-01-10', 'date_to' => '2026-01-13'];
        $service = app(ReportsService::class);
        $chart = $service->getRevenueChartData($filters);

        $this->assertSame(['10/01/2026', '11/01/2026', '12/01/2026', '13/01/2026'], $chart['labels']);
        $this->assertEquals([255000, 400000, 100000, 0], $chart['revenue']);
        $this->assertEquals($service->getKpiMetrics($filters)['total_revenue'], array_sum($chart['revenue']));
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
            'ThanhTien' => 50000,
            'NgayLap' => '2026-01-09 18:00:00',
            'TrangThai' => InvoiceStatus::Paid->value,
        ]);
        DB::table('ThanhToan')->insert([
            'ThanhToanID' => 8,
            'DonHangID' => 2,
            'SoTien' => 50000,
            'PhuongThuc' => 'Tiền mặt',
            'MaGiaoDich' => 'RPC-fixture-boundary',
            'ThoiGian' => '2026-01-09 18:00:00',
            'TrangThai' => PaymentStatus::Paid->value,
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

        $this->assertEquals(305000, $metrics['total_revenue']);
        $this->assertSame(['10/01/2026'], $chart['labels']);
        $this->assertEquals([305000], $chart['revenue']);
    }

    public function test_custom_reports_include_utc_and_vietnam_local_orders_and_payments_on_the_local_day(): void
    {
        DB::table('DonHang')->insert([
            [
                'DonHangID' => 5,
                'MaDonHang' => 'DH-005',
                'BookingID' => 105,
                'KhachHangID' => 1,
                'TrangThai' => OrderStatus::Washing->value,
                'ThanhTien' => 11000,
                'NgayTao' => '2026-01-09 18:00:00',
            ],
            [
                'DonHangID' => 6,
                'MaDonHang' => 'DH-006',
                'BookingID' => null,
                'KhachHangID' => 1,
                'TrangThai' => OrderStatus::Washing->value,
                'ThanhTien' => 12000,
                'NgayTao' => '2026-01-10 01:00:00',
            ],
        ]);
        DB::table('ThanhToan')->insert([
            [
                'DonHangID' => 5,
                'SoTien' => 11000,
                'PhuongThuc' => 'Tiền mặt',
                'MaGiaoDich' => 'RPC-local-day',
                'ThoiGian' => '2026-01-09 18:00:00',
                'TrangThai' => PaymentStatus::Paid->value,
            ],
            [
                'DonHangID' => 6,
                'SoTien' => 12000,
                'PhuongThuc' => 'Tiền mặt',
                'MaGiaoDich' => 'TM-local-day',
                'ThoiGian' => '2026-01-10 01:00:00',
                'TrangThai' => PaymentStatus::Paid->value,
            ],
        ]);

        $filters = [
            'range' => 'custom',
            'date_from' => '2026-01-10',
            'date_to' => '2026-01-10',
        ];
        $service = app(ReportsService::class);

        $this->assertEquals(278000, $service->getKpiMetrics($filters)['total_revenue']);
        $this->assertSame(6, $service->getRecentOrders($filters)->total());
        $this->assertCount(6, $service->getOrdersForExport($filters));
        $this->assertEquals(
            [278000],
            $service->getRevenueChartData($filters)['revenue'],
        );
    }

    public function test_all_time_filter_includes_existing_data_and_groups_chart_by_month(): void
    {
        $service = app(ReportsService::class);
        $metrics = $service->getKpiMetrics(['range' => 'all_time']);
        $chart = $service->getRevenueChartData(['range' => 'all_time']);

        $this->assertNull($service->getDateRange(['range' => 'all_time'])['from']);
        $this->assertEquals(255000, $metrics['total_revenue']);
        $this->assertSame(['01/2026'], $chart['labels']);
        $this->assertEquals([255000], $chart['revenue']);
        $this->assertEquals(255000, $service->getKpiMetrics([])['total_revenue']);
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

        $this->assertSame(['11/01/2026'], $revenue['labels']);
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

    public function test_payment_method_totals_include_only_successful_collected_payments(): void
    {
        $payments = app(ReportsService::class)->getRevenueByPaymentMethod([
            'range' => 'custom',
            'date_from' => '2026-01-10',
            'date_to' => '2026-01-10',
        ]);

        $this->assertCount(2, $payments);
        $this->assertSame('Tiền mặt', $payments[0]->method);
        $this->assertEquals(230000, $payments[0]->total_amount);
        $this->assertSame(4, $payments[0]->transaction_count);
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

    public function test_recent_order_avatars_use_profile_account_and_initial_fallback_without_per_row_queries(): void
    {
        $this->seedAvatarFixtures();
        $connection = DB::connection();
        $connection->flushQueryLog();
        $connection->enableQueryLog();

        try {
            $orders = app(ReportsService::class)->getRecentOrders(['range' => 'all_time']);

            $this->assertCount(4, $orders);
            $this->assertCount(7, $connection->getQueryLog());
            $connection->flushQueryLog();

            $avatars = [];
            $customers = [];
            foreach ($orders as $order) {
                $this->assertTrue($order->relationLoaded('khachHang'));
                $customer = $order->khachHang;
                $this->assertTrue($customer->relationLoaded('taiKhoan'));
                $this->assertArrayHasKey('AvatarUrl', $customer->getAttributes());
                $customers[$customer->KhachHangID] = $customer;
                $avatars[$customer->KhachHangID] = (string) $this->blade(
                    '<x-admin.customer-avatar :customer="$customer" :size="40" />',
                    compact('customer'),
                );
            }

            $this->assertSame('https://example.test/profile.jpg', $customers[1]->getAttributes()['AvatarUrl']);
            $this->assertSame('https://example.test/account.jpg', $customers[2]->taiKhoan->AvatarURL);
            $this->assertNull($customers[3]->taiKhoan);
            $this->assertSame('', $customers[4]->taiKhoan->AvatarURL);
            $this->assertStringContainsString('src="https://example.test/profile.jpg"', $avatars[1]);
            $this->assertStringNotContainsString('https://example.test/unused.jpg', $avatars[1]);
            $this->assertStringContainsString('src="https://example.test/account.jpg"', $avatars[2]);
            foreach ([3 => 'Đ', 4 => 'L'] as $customerId => $initial) {
                $this->assertStringNotContainsString('<img', $avatars[$customerId]);
                $this->assertMatchesRegularExpression('/<span aria-hidden="true"\s*>\s*'.$initial.'\s*<\/span>/u', $avatars[$customerId]);
            }
            foreach ($avatars as $avatar) {
                $this->assertStringContainsString('width: 40px; height: 40px;', $avatar);
                $this->assertStringNotContainsString('assets/images/user_1.jpg', $avatar);
            }
            $this->assertSame([], $connection->getQueryLog(), 'Rendering report customer avatars must not issue per-row queries.');
        } finally {
            $connection->disableQueryLog();
            $connection->flushQueryLog();
        }
    }

    public function test_reports_page_shows_customer_avatars_beside_names(): void
    {
        $this->seedAvatarFixtures();

        $this->actingAs($this->reportOwner())->get('/reports')
            ->assertOk()
            ->assertSee('d-flex align-items-center gap-2', false)
            ->assertSee('width: 40px; height: 40px;', false)
            ->assertSee('src="https://example.test/profile.jpg"', false)
            ->assertSee('src="https://example.test/account.jpg"', false)
            ->assertDontSee('https://example.test/unused.jpg', false)
            ->assertSee('Ảnh đại diện của Đặng An')
            ->assertSee('Ảnh đại diện của Lê Bình')
            ->assertSee('<span>Nguyễn An</span>', false)
            ->assertSee('<span>Trần Bình</span>', false)
            ->assertSee('<span>Đặng An</span>', false)
            ->assertSee('<span>Lê Bình</span>', false);
    }

    public function test_recent_orders_show_operational_and_paid_statuses_and_keep_ten_per_page(): void
    {
        $orders = app(ReportsService::class)->getRecentOrders([
            'range' => 'custom',
            'date_from' => '2026-01-10',
            'date_to' => '2026-01-10',
        ]);

        $paidOrder = $orders->getCollection()->firstWhere('DonHangID', 1);
        $this->assertTrue($paidOrder->isPaid());
        $this->assertSame(OrderStatus::Delivered, $paidOrder->operationalStatusEnum());
        $this->assertSame(10, $orders->perPage());

        $statusBadges = view('components.admin.order-status-badges', [
            'order' => $paidOrder,
            'isPaid' => $paidOrder->isPaid(),
            'isReceiving' => false,
            'compact' => true,
        ])->render();

        $this->assertStringContainsString('Đã giao', $statusBadges);
        $this->assertStringContainsString('Đã thanh toán', $statusBadges);
    }

    public function test_invalid_filter_shape_falls_back_to_the_default_period(): void
    {
        $this->actingAs($this->reportOwner())
            ->get('/reports?filter%5B%5D=today')
            ->assertOk()
            ->assertSee('data-active-filter="all_time"', false);
    }

    public function test_reports_page_renders_for_owner_with_isolated_fixtures(): void
    {
        $response = $this->actingAs($this->reportOwner())->get('/reports')
            ->assertOk()
            ->assertSee('Báo cáo', false)
            ->assertSee('Giá trị dịch vụ (Chưa gồm phí ship &amp; giảm giá)', false)
            ->assertSee('Số lượng đặt')
            ->assertSee('Số giao dịch')
            ->assertSee('order-status-badges d-inline-flex align-items-center flex-nowrap gap-1 text-nowrap', false)
            ->assertSee('formatter: value => formatCurrency(value)', false);

        $this->assertMatchesRegularExpression(
            '/<th(?=[^>]*style="width: 130px;")(?=[^>]*class="fw-bold text-dark text-center text-nowrap")[^>]*>Số lượng đặt<\\/th>/',
            $response->getContent(),
        );
        $this->assertMatchesRegularExpression(
            '/<th(?=[^>]*style="width: 130px;")(?=[^>]*class="fw-bold text-dark text-center text-nowrap")[^>]*>Số giao dịch<\\/th>/',
            $response->getContent(),
        );

        preg_match(
            '/<!-- Top 5 Services -->(.*?)<!-- Revenue by Payment Method -->/s',
            $response->getContent(),
            $topServicesSection,
        );

        $this->assertNotEmpty($topServicesSection);
        $this->assertStringContainsString(
            '<span class="fw-semibold">Giặt thường</span>',
            $topServicesSection[1],
        );
        $this->assertMatchesRegularExpression(
            '/<span class="fw-semibold">Giặt thường<\\/span>\\s*<\\/td>\\s*<td class="text-center">4<\\/td>/',
            $topServicesSection[1],
        );
        $this->assertStringNotContainsString('Nhiều ĐVT', $topServicesSection[1]);
        $this->assertStringNotContainsString('(kg)', $topServicesSection[1]);
    }

    public function test_reports_export_contains_two_requested_sheets_and_excel_typed_values(): void
    {
        $additionalOrders = [];
        for ($index = 1; $index <= 11; $index++) {
            $additionalOrders[] = [
                'MaDonHang' => 'DH-EXPORT-'.$index,
                'KhachHangID' => 1,
                'TrangThai' => OrderStatus::Pending->value,
                'ThanhTien' => 0,
                'NgayTao' => '2026-01-10 08:00:00',
            ];
        }
        DB::table('DonHang')->insert($additionalOrders);

        $response = $this->actingAs($this->reportOwner())
            ->get('/reports/export?filter=all_time')
            ->assertOk()
            ->assertDownload();

        $temporaryFile = tempnam(sys_get_temp_dir(), 'reports-export-');
        $this->assertNotFalse($temporaryFile);
        if ($temporaryFile === false) {
            return;
        }

        file_put_contents($temporaryFile, $response->streamedContent());

        try {
            $workbook = IOFactory::load($temporaryFile);
            $this->assertSame(['Tong_Quan_Thong_Ke', 'Danh_Sach_Don_Hang'], $workbook->getSheetNames());

            $summary = $workbook->getSheet(0);
            $this->assertSame('Sky Laundry', $summary->getCell('B1')->getValue());
            $this->assertSame('BÁO CÁO & THỐNG KÊ KINH DOANH GIẶT ỦI', $summary->getCell('B2')->getValue());
            $this->assertSame('Toàn thời gian', $summary->getCell('B3')->getValue());
            $this->assertStringNotContainsString('UTC', $summary->getCell('B3')->getValue());
            $this->assertEquals(255000, $summary->getCell('B6')->getValue());
            $this->assertSame(DataType::TYPE_NUMERIC, $summary->getCell('B6')->getDataType());
            $this->assertSame('#,##0', $summary->getStyle('B6')->getNumberFormat()->getFormatCode());
            $this->assertSame('Dịch vụ', $summary->getCell('B15')->getValue());
            $this->assertSame('center', $summary->getStyle('B5')->getAlignment()->getHorizontal());
            $this->assertSame('center', $summary->getStyle('A15')->getAlignment()->getHorizontal());
            $this->assertSame('center', $summary->getStyle('A16')->getAlignment()->getHorizontal());
            $this->assertSame('right', $summary->getStyle('B6')->getAlignment()->getHorizontal());
            $this->assertSame('1E3A8A', $summary->getStyle('A14')->getFill()->getStartColor()->getRGB());
            $this->assertSame('E2E8F0', $summary->getStyle('A18')->getFill()->getStartColor()->getRGB());
            $this->assertEquals(100000, $summary->getCell('E16')->getValue());
            $this->assertEquals(255000, $summary->getCell('D25')->getValue());
            $this->assertEquals(1, $summary->getCell('E25')->getValue());

            $orders = $workbook->getSheet(1);
            $this->assertSame('STT', $orders->getCell('A1')->getValue());
            $this->assertSame(16, $orders->getHighestRow());
            $this->assertNotSame('Tổng cộng doanh thu', $orders->getCell('E16')->getValue());
            $this->assertSame('#,##0', $orders->getStyle('F2')->getNumberFormat()->getFormatCode());
            $this->assertSame('dd/mm/yyyy hh:mm', $orders->getStyle('G2')->getNumberFormat()->getFormatCode());
            $this->assertSame('10/01/2026 16:00', $orders->getCell('G5')->getFormattedValue());
            $this->assertGreaterThan(10, $orders->getColumnDimension('B')->getWidth());
            $this->assertSame('center', $orders->getStyle('A1')->getAlignment()->getHorizontal());
            $this->assertSame('center', $orders->getStyle('A2')->getAlignment()->getHorizontal());
            $this->assertSame('right', $orders->getStyle('F2')->getAlignment()->getHorizontal());
            $this->assertSame('1E3A8A', $orders->getStyle('A1')->getFont()->getColor()->getRGB());
            $this->assertSame('A1:G1', $orders->getAutoFilter()->getRange());
        } finally {
            if (isset($workbook)) {
                $workbook->disconnectWorksheets();
            }
            unlink($temporaryFile);
        }
    }

    public function test_total_order_status_badges_match_dashboard_styles_and_full_labels(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-10 12:00:00', 'Asia/Ho_Chi_Minh'));

        $this->actingAs($this->reportOwner())->get('/reports?filter=today')
            ->assertOk()
            ->assertSee('<span class="badge bg-success-subtle text-success-emphasis border border-success rounded-pill">1 Hoàn thành</span>', false)
            ->assertSee('<span class="badge bg-primary-subtle text-primary-emphasis border border-primary-subtle fw-semibold rounded-pill">2 Đang xử lý</span>', false)
            ->assertSee('<span class="badge bg-danger-subtle text-danger-emphasis border border-danger rounded-pill">1 Đã hủy</span>', false)
            ->assertDontSee('badge-breakdown', false);
    }

    public function test_revenue_trend_defaults_to_a_line_with_time_and_money_axes_only(): void
    {
        $this->actingAs($this->reportOwner())->get('/reports')
            ->assertOk()
            ->assertSee('<option value="line" selected>Đường</option>', false)
            ->assertSee("title: { text: 'Thời gian' }", false)
            ->assertSee("title: { text: 'Doanh thu (VNĐ)' }", false)
            ->assertDontSee("name: 'Số đơn'", false)
            ->assertDontSee('const orderValues', false);
    }

    public function test_revenue_trend_shows_an_empty_state_when_payments_have_no_revenue(): void
    {
        DB::table('ThanhToan')->update(['SoTien' => 0]);
        $this->actingAs($this->reportOwner())->get('/reports')
            ->assertOk()
            ->assertSee('Chưa có doanh thu trong khoảng thời gian này')
            ->assertDontSee('id="revenueChart"', false);
    }

    public function test_service_composition_chart_keeps_percentage_labels_for_small_slices(): void
    {
        $this->actingAs($this->reportOwner())->get('/reports')
            ->assertOk()
            ->assertSee('minAngleToShowLabel: 0', false);
    }

    public function test_reports_page_renders_with_all_supported_date_filters(): void
    {
        $this->actingAs($this->reportOwner());
        foreach (['today', 'this_week', 'this_month', 'all_time'] as $filter) {
            $this->get('/reports?filter='.$filter)
                ->assertOk()
                ->assertSee('data-active-filter="'.$filter.'"', false)
                ->assertSee(htmlspecialchars(route('reports.export', ['filter' => $filter]), ENT_QUOTES, 'UTF-8'), false)
                ->assertSee('Xuất Excel');
        }
    }

    public function test_recent_order_links_are_hidden_without_order_view_permission(): void
    {
        $viewer = Mockery::mock(User::class)->makePartial();
        $viewer->shouldReceive('isActive')->andReturn(true);
        $viewer->shouldReceive('isOwner')->andReturn(false);
        $viewer->shouldReceive('roleSlug')->andReturn('manager');
        $viewer->shouldReceive('canPermission')->andReturnUsing(
            fn (string $permission): bool => $permission === 'reports.view'
        );

        $html = $this->actingAs($viewer)->get('/reports')->assertOk()->getContent();

        $this->assertStringContainsString('DH-001', $html);
        $this->assertStringNotContainsString(route('orders.show', 1), $html);
        $this->assertStringNotContainsString('>Thao tác</th>', $html);
    }

    public function test_quick_periods_export_excel_with_the_selected_period(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-10 12:00:00', 'Asia/Ho_Chi_Minh'));
        $staff = $this->reportOwner();
        $expectedLabels = [
            'today' => 'Hôm nay (10/01/2026 – 10/01/2026)',
            'this_week' => 'Tuần này (05/01/2026 – 10/01/2026)',
            'this_month' => 'Tháng này (01/01/2026 – 31/01/2026)',
        ];

        foreach ($expectedLabels as $filter => $expectedLabel) {
            $response = $this->actingAs($staff)
                ->get('/reports/export?filter='.$filter)
                ->assertOk()
                ->assertDownload();

            $temporaryFile = tempnam(sys_get_temp_dir(), 'reports-period-export-');
            $this->assertNotFalse($temporaryFile);
            if ($temporaryFile === false) {
                return;
            }

            file_put_contents($temporaryFile, $response->streamedContent());

            try {
                $workbook = IOFactory::load($temporaryFile);
                $this->assertSame($expectedLabel, $workbook->getSheet(0)->getCell('B3')->getValue());
            } finally {
                if (isset($workbook)) {
                    $workbook->disconnectWorksheets();
                    unset($workbook);
                }
                unlink($temporaryFile);
            }
        }
    }

    public function test_all_time_date_range_filters_reports_and_is_preserved_in_excel_export(): void
    {
        $staff = $this->reportOwner();
        $query = 'filter=all_time&date_from=2026-01-10&date_to=2026-01-10';

        $this->actingAs($staff)
            ->get('/reports?'.$query)
            ->assertOk()
            ->assertSee('data-active-filter="all_time"', false)
            ->assertDontSee('Khoảng ngày tùy chọn')
            ->assertDontSee('<option value="custom"', false)
            ->assertSee('Số liệu · Từ 10/01/2026 đến 10/01/2026')
            ->assertSee('class="period-filter-layout"', false)
            ->assertSee('class="period-date-range-fields "', false)
            ->assertSee('value="2026-01-10"', false)
            ->assertSee('Từ ngày', false)
            ->assertSee('Đến ngày', false)
            ->assertSee(htmlspecialchars(route('reports.export', [
                'filter' => 'all_time',
                'date_from' => '2026-01-10',
                'date_to' => '2026-01-10',
            ]), ENT_QUOTES, 'UTF-8'), false);

        $response = $this->get('/reports/export?'.$query)->assertOk()->assertDownload();
        $temporaryFile = tempnam(sys_get_temp_dir(), 'reports-date-range-export-');
        $this->assertNotFalse($temporaryFile);
        if ($temporaryFile === false) {
            return;
        }

        file_put_contents($temporaryFile, $response->streamedContent());

        try {
            $workbook = IOFactory::load($temporaryFile);
            $summary = $workbook->getSheet(0);

            $this->assertSame('Từ 10/01/2026 đến 10/01/2026', $summary->getCell('B3')->getValue());
            $this->assertEquals(255000, $summary->getCell('B6')->getValue());
        } finally {
            if (isset($workbook)) {
                $workbook->disconnectWorksheets();
            }
            unlink($temporaryFile);
        }
    }

    public function test_all_time_date_range_requires_both_valid_ordered_dates(): void
    {
        $this->actingAs($this->reportOwner())
            ->get('/reports?filter=all_time&date_from=2026-01-11&date_to=2026-01-10')
            ->assertSessionHasErrors(['date_to']);

        $this->get('/reports?filter=all_time&date_from=2026-01-10')
            ->assertSessionHasErrors(['date_to']);
    }

    public function test_all_time_without_dates_remains_unbounded(): void
    {
        $dates = app(ReportsService::class)->getDateRange(['range' => 'all_time']);

        $this->assertNull($dates['from']);
        $this->assertNull($dates['to']);

        $this->actingAs($this->reportOwner())
            ->get('/reports?filter=all_time')
            ->assertOk()
            ->assertSee('data-active-filter="all_time"', false)
            ->assertSee('id="period-date-range-fields"', false)
            ->assertSee('id="period-date-from"', false)
            ->assertSee('id="period-date-to"', false);
    }

    public function test_this_week_starts_on_monday_of_the_vietnamese_calendar(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-10 12:00:00', 'Asia/Ho_Chi_Minh'));

        $dates = app(ReportsService::class)->getDateRange(['range' => 'this_week']);

        $this->assertSame('this_week', $dates['range']);
        $this->assertSame('2026-01-05 00:00:00', $dates['local_from']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-01-04 17:00:00', $dates['from']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-01-10 16:59:59', $dates['to']->format('Y-m-d H:i:s'));
    }

    /**
     * Test-only schema for the in-memory SQLite connection; no migrations or
     * application/Supabase schema are touched.
     */
    private function createTestSchema(): void
    {
        Schema::create('NhatKyHeThong', function (Blueprint $table): void {
            $table->increments('NhatKyID');
            $table->string('HanhDong');
            $table->string('BangDuLieu');
            $table->unsignedInteger('BanGhiID');
            $table->json('DuLieuCu')->nullable();
            $table->json('DuLieuMoi')->nullable();
            $table->dateTime('ThoiGian');
        });

        Schema::create('ThongBao', function (Blueprint $table): void {
            $table->increments('ThongBaoID');
            $table->integer('TaiKhoanID')->nullable();
            $table->boolean('DaDoc')->default(false);
            $table->timestamp('ThoiGianGui')->nullable();
        });
        Schema::create('DonHang', function (Blueprint $table): void {
            $table->increments('DonHangID');
            $table->string('MaDonHang');
            $table->integer('BookingID')->nullable();
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
            $table->string('MaGiaoDich')->nullable();
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
            $table->string('AvatarUrl')->nullable();
        });

        Schema::create('TaiKhoan', function (Blueprint $table): void {
            $table->increments('TaiKhoanID');
            $table->integer('KhachHangID');
            $table->string('AvatarURL')->nullable();
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
            ['DonHangID' => 1, 'MaDonHang' => 'DH-001', 'BookingID' => 101, 'KhachHangID' => 1, 'TrangThai' => OrderStatus::Paid->value, 'ThanhTien' => 125000, 'NgayTao' => '2026-01-10 09:00:00'],
            ['DonHangID' => 2, 'MaDonHang' => 'DH-002', 'BookingID' => 102, 'KhachHangID' => 1, 'TrangThai' => OrderStatus::Washing->value, 'ThanhTien' => 50000, 'NgayTao' => '2026-01-10 10:00:00'],
            ['DonHangID' => 4, 'MaDonHang' => 'DH-004', 'BookingID' => 104, 'KhachHangID' => 1, 'TrangThai' => OrderStatus::Washed->value, 'ThanhTien' => 50000, 'NgayTao' => '2026-01-10 10:30:00'],
            ['DonHangID' => 3, 'MaDonHang' => 'DH-003', 'BookingID' => 103, 'KhachHangID' => 1, 'TrangThai' => OrderStatus::Cancelled->value, 'ThanhTien' => 30000, 'NgayTao' => '2026-01-10 11:00:00'],
        ]);

        DB::table('HoaDon')->insert([
            ['HoaDonID' => 1, 'DonHangID' => 1, 'ThanhTien' => 125000, 'NgayLap' => '2026-01-10 12:00:00', 'TrangThai' => InvoiceStatus::Paid->value],
            ['HoaDonID' => 2, 'DonHangID' => 3, 'ThanhTien' => 30000, 'NgayLap' => '2026-01-10 12:00:00', 'TrangThai' => InvoiceStatus::Cancelled->value],
        ]);

        DB::table('ThanhToan')->insert([
            ['ThanhToanID' => 1, 'DonHangID' => 1, 'SoTien' => 100000, 'PhuongThuc' => 'Tiền mặt', 'MaGiaoDich' => 'RPC-fixture-1', 'ThoiGian' => '2026-01-10 12:05:00', 'TrangThai' => PaymentStatus::Paid->value],
            ['ThanhToanID' => 2, 'DonHangID' => 1, 'SoTien' => 25000, 'PhuongThuc' => 'Chuyển khoản', 'MaGiaoDich' => 'RPC-fixture-2', 'ThoiGian' => '2026-01-10 12:06:00', 'TrangThai' => PaymentStatus::Paid->value],
            ['ThanhToanID' => 3, 'DonHangID' => 3, 'SoTien' => 30000, 'PhuongThuc' => 'Tiền mặt', 'MaGiaoDich' => 'RPC-fixture-3', 'ThoiGian' => '2026-01-10 12:07:00', 'TrangThai' => PaymentStatus::Paid->value],
            ['ThanhToanID' => 4, 'DonHangID' => 2, 'SoTien' => 50000, 'PhuongThuc' => 'Tiền mặt', 'MaGiaoDich' => 'RPC-fixture', 'ThoiGian' => '2026-01-09 18:00:00', 'TrangThai' => PaymentStatus::Paid->value],
            ['ThanhToanID' => 5, 'DonHangID' => 2, 'SoTien' => 50000, 'PhuongThuc' => 'Tiền mặt', 'MaGiaoDich' => 'RPC-fixture-5', 'ThoiGian' => '2026-01-10 12:08:00', 'TrangThai' => PaymentStatus::Pending->value],
            ['ThanhToanID' => 6, 'DonHangID' => 2, 'SoTien' => 50000, 'PhuongThuc' => 'Tiền mặt', 'MaGiaoDich' => 'RPC-fixture-6', 'ThoiGian' => '2026-01-10 12:09:00', 'TrangThai' => PaymentStatus::Failed->value],
            ['ThanhToanID' => 7, 'DonHangID' => 2, 'SoTien' => 50000, 'PhuongThuc' => 'Tiền mặt', 'MaGiaoDich' => 'RPC-fixture-7', 'ThoiGian' => '2026-01-10 12:10:00', 'TrangThai' => PaymentStatus::Refunded->value],
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

    private function seedAvatarFixtures(): void
    {
        DB::table('KhachHang')->where('KhachHangID', 1)->update([
            'AvatarUrl' => 'https://example.test/profile.jpg',
        ]);
        DB::table('KhachHang')->insert([
            ['KhachHangID' => 2, 'HoTen' => 'Trần Bình', 'AvatarUrl' => null],
            ['KhachHangID' => 3, 'HoTen' => 'Đặng An', 'AvatarUrl' => null],
            ['KhachHangID' => 4, 'HoTen' => 'Lê Bình', 'AvatarUrl' => ''],
        ]);
        DB::table('TaiKhoan')->insert([
            ['TaiKhoanID' => 11, 'KhachHangID' => 1, 'AvatarURL' => 'https://example.test/unused.jpg'],
            ['TaiKhoanID' => 12, 'KhachHangID' => 2, 'AvatarURL' => 'https://example.test/account.jpg'],
            ['TaiKhoanID' => 14, 'KhachHangID' => 4, 'AvatarURL' => ''],
        ]);
        foreach ([2, 3, 4] as $id) {
            DB::table('DonHang')->where('DonHangID', $id)->update(['KhachHangID' => $id]);
        }
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
