<?php

namespace App\Http\Controllers\Admin;

use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\DanhGia;
use App\Models\DichVu;
use App\Models\DonHang;
use App\Models\HoaDon;
use App\Models\KhachHang;
use App\Models\ThanhToan;
use App\Services\ReviewService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __construct(
        private ReviewService $reviewService,
    ) {}

    /**
     * Hiển thị trang Dashboard dành cho Quản lý.
     * Bao gồm: KPI tài chính, biểu đồ, bảng dữ liệu nhanh.
     */
    public function index()
    {
        $today = Carbon::today();
        $thisMonth = now()->month;
        $thisYear = now()->year;
        $weekStart = $today->copy()->startOfWeek(Carbon::MONDAY);
        $prevWeekStart = $weekStart->copy()->subWeek();
        $monthStart = $today->copy()->startOfMonth();
        $prevMonthStart = $monthStart->copy()->subMonth();
        $revenueChartStart = Carbon::today()->subMonths(11)->startOfMonth();
        $dailyRevenue = $this->getDailyRevenueTotals($revenueChartStart, $today);

        // --- KPI: Doanh thu hôm nay ---
        $todayRevenue = $this->sumDailyRevenue($dailyRevenue, $today, $today);

        $previousDay = $today->copy()->subDay();
        $prevDayRevenue = $this->sumDailyRevenue($dailyRevenue, $previousDay, $previousDay);

        $todayRevenueChange = $this->percentChange($todayRevenue, $prevDayRevenue);

        // --- KPI: Doanh thu tuần này (từ thứ 2) ---
        $weekRevenue = $this->sumDailyRevenue($dailyRevenue, $weekStart, $today);

        $prevWeekRevenue = $this->sumDailyRevenue(
            $dailyRevenue,
            $prevWeekStart,
            $prevWeekStart->copy()->endOfWeek(Carbon::SUNDAY)
        );

        $weekRevenueChange = $this->percentChange($weekRevenue, $prevWeekRevenue);

        // --- KPI: Doanh thu tháng này ---
        $monthRevenue = $this->sumDailyRevenue($dailyRevenue, $monthStart, $today);

        $prevMonthRevenue = $this->sumDailyRevenue(
            $dailyRevenue,
            $prevMonthStart,
            $prevMonthStart->copy()->endOfMonth()
        );

        $monthRevenueChange = $this->percentChange($monthRevenue, $prevMonthRevenue);

        // --- KPI: Tổng số đơn hàng (theo trạng thái) ---
        $orderCounts = DonHang::query()
            ->selectRaw('"TrangThai", COUNT(*) AS total')
            ->groupBy('TrangThai')
            ->pluck('total', 'TrangThai');

        $statusCounts = [
            'completed' => (int) $orderCounts->only(OrderStatus::settledValues())->sum(),
            'processing' => (int) $orderCounts->only([
                OrderStatus::Pending->value,
                OrderStatus::Received->value,
                OrderStatus::Washing->value,
                OrderStatus::Washed->value,
                OrderStatus::Delivering->value,
            ])->sum(),
            'cancelled' => (int) $orderCounts->get(OrderStatus::Cancelled->value, 0),
        ];

        $totalOrders = (int) $orderCounts->sum();

        // --- KPI: Khách hàng ---
        $totalCustomers = KhachHang::count();
        $newCustomersMonth = KhachHang::whereMonth('NgayTao', $thisMonth)
            ->whereYear('NgayTao', $thisYear)
            ->count();

        // --- KPI: Điểm đánh giá trung bình & tổng số lượt đánh giá ---
        $averageRating = $this->reviewService->getAverageRating();
        $totalReviews = $this->reviewService->getTotalReviews();

        // --- Biểu đồ: Doanh thu 7 ngày gần nhất ---
        $last7DaysRevenue = collect(range(6, 0))->map(function ($daysAgo) use ($dailyRevenue) {
            $date = Carbon::today()->subDays($daysAgo);

            return [
                'date' => $date->format('d/m'),
                'revenue' => $this->sumDailyRevenue($dailyRevenue, $date, $date),
            ];
        })->values();

        // --- Biểu đồ: Doanh thu 12 tháng gần nhất (cuộn tròn, có giới hạn năm) ---
        $last12Months = collect(range(11, 0))->map(function ($monthsAgo) use ($dailyRevenue) {
            $month = Carbon::today()->subMonths($monthsAgo);

            return [
                'label' => 'T'.$month->month,
                'revenue' => $this->sumDailyRevenue(
                    $dailyRevenue,
                    $month->copy()->startOfMonth(),
                    $month->copy()->endOfMonth()
                ),
            ];
        })->values();

        // --- Biểu đồ tròn: Tỷ lệ đơn hàng theo trạng thái ---
        // Nháy kép là bắt buộc: raw SQL không qua wrapper của Eloquent nên
        // PostgreSQL sẽ hạ `TrangThai` xuống `trangthai` và báo thiếu cột.
        $statusDistribution = DonHang::selectRaw('"TrangThai", COUNT(*) as count')
            ->groupBy('TrangThai')
            ->pluck('count', 'TrangThai');

        // --- Biểu đồ cột: Top dịch vụ được đặt nhiều nhất ---
        $topServices = DichVu::withCount('chiTietDonHangs')
            ->orderByDesc('chi_tiet_don_hangs_count')
            ->limit(5)
            ->get()
            ->map(fn (DichVu $service) => [
                'name' => $service->TenDichVu,
                'orders_count' => $service->chi_tiet_don_hangs_count,
            ]);

        // --- Bảng: Đơn hàng mới nhất (top 10) ---
        $recentOrders = DonHang::with(['khachHang', 'nhanVien'])
            ->orderBy('NgayTao', 'desc')
            ->limit(10)
            ->get();

        // --- Bảng: Đánh giá mới nhất đang hiển thị ---
        $latestReviews = DanhGia::with(['khachHang', 'donHang'])
            ->where('TrangThai', 'Hiển thị')
            ->orderBy('NgayDanhGia', 'desc')
            ->limit(10)
            ->get();

        return view('admin.dashboard', [
            'isAdmin' => true,
            'todayRevenue' => $todayRevenue,
            'todayRevenueChange' => $todayRevenueChange,
            'weekRevenue' => $weekRevenue,
            'weekRevenueChange' => $weekRevenueChange,
            'monthRevenue' => $monthRevenue,
            'monthRevenueChange' => $monthRevenueChange,
            'statusCounts' => $statusCounts,
            'totalOrders' => $totalOrders,
            'totalCustomers' => $totalCustomers,
            'newCustomersMonth' => $newCustomersMonth,
            'averageRating' => $averageRating,
            'totalReviews' => $totalReviews,
            'last7DaysRevenue' => $last7DaysRevenue,
            'last12Months' => $last12Months,
            'statusDistribution' => $statusDistribution,
            'topServices' => $topServices,
            'recentOrders' => $recentOrders,
            'latestReviews' => $latestReviews,
        ]);
    }

    /**
     * @return array<string, float>
     */
    private function getDailyRevenueTotals(Carbon $from, Carbon $to): array
    {
        return DonHang::query()
            ->selectRaw('DATE("NgayTao") AS revenue_date, SUM("ThanhTien") AS total')
            ->whereBetween('NgayTao', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->where(function ($query): void {
                $query->whereIn('TrangThai', OrderStatus::settledValues())
                    ->orWhereHas('hoaDons', function ($invoiceQuery): void {
                        $invoiceQuery->where('TrangThai', InvoiceStatus::Paid->value);
                    });
            })
            ->groupByRaw('DATE("NgayTao")')
            ->orderBy('revenue_date')
            ->get()
            ->mapWithKeys(fn ($row): array => [(string) $row->revenue_date => (float) $row->total])
            ->all();
    }

    /**
     * @param  array<string, float>  $dailyRevenue
     */
    private function sumDailyRevenue(array $dailyRevenue, Carbon $from, Carbon $to): float
    {
        $total = 0.0;
        $date = $from->copy()->startOfDay();
        $lastDate = $to->copy()->startOfDay();

        while ($date->lessThanOrEqualTo($lastDate)) {
            $total += $dailyRevenue[$date->toDateString()] ?? 0.0;
            $date->addDay();
        }

        return $total;
    }

    /**
     * Thu tiền mặt cho hóa đơn.
     */
    public function collectCashPayment(Request $request, int $invoice)
    {
        $invoice = HoaDon::with('donHang')->find($invoice);

        if (! $invoice) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy hóa đơn.'], 404);
        }

        if ($invoice->isPaid()) {
            return response()->json(['success' => false, 'message' => 'Hóa đơn đã được thanh toán.'], 400);
        }

        DB::transaction(function () use ($invoice) {
            $invoice->update(['TrangThai' => InvoiceStatus::Paid->value]);

            ThanhToan::create([
                'DonHangID' => $invoice->DonHangID,
                'SoTien' => $invoice->ThanhTien,
                'PhuongThuc' => 'Tiền mặt',
                'TrangThai' => PaymentStatus::Paid->value,
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Thu tiền mặt thành công.',
            'invoice_id' => $invoice->HoaDonID,
        ]);
    }

    /**
     * Tính phần trăm tăng/giảm so với kỳ trước.
     */
    private function percentChange(float $current, float $previous): float
    {
        if ($previous <= 0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    /**
     * API lấy dữ liệu doanh thu cho biểu đồ Dashboard.
     * Hỗ trợ filter: today, 7_days, this_month, this_year
     */
    public function getRevenueChartData(Request $request)
    {
        $filter = $request->get('filter', '7_days');
        $today = Carbon::today();
        $thisYear = now()->year;
        $groupBy = 'day';
        $from = $today->copy()->subDays(6)->startOfDay();
        $to = $today->copy()->endOfDay();
        $labels = [];
        $periodKeys = [];

        switch ($filter) {
            case 'today':
                $groupBy = 'hour';
                $from = $today->copy()->startOfDay();

                for ($hour = 0; $hour <= 23; $hour++) {
                    $labels[] = sprintf('%02d:00', $hour);
                    $periodKeys[] = $hour;
                }
                break;

            case '7_days':
                for ($daysAgo = 6; $daysAgo >= 0; $daysAgo--) {
                    $date = $today->copy()->subDays($daysAgo);
                    $labels[] = $date->format('d/m');
                    $periodKeys[] = $date->toDateString();
                }
                break;

            case 'this_month':
                $from = $today->copy()->startOfMonth();
                $to = $today->copy()->endOfMonth();

                $daysInMonth = $today->copy()->daysInMonth;
                for ($day = 1; $day <= $daysInMonth; $day++) {
                    $date = $today->copy()->setDay($day);
                    $labels[] = $date->format('d/m');
                    $periodKeys[] = $date->toDateString();
                }
                break;

            case 'this_year':
                $groupBy = 'month';
                $from = Carbon::create($thisYear, 1, 1)->startOfYear();
                $to = $from->copy()->endOfYear();

                for ($month = 1; $month <= 12; $month++) {
                    $labels[] = 'Tháng '.$month;
                    $periodKeys[] = $month;
                }
                break;

            default:
                for ($daysAgo = 6; $daysAgo >= 0; $daysAgo--) {
                    $date = $today->copy()->subDays($daysAgo);
                    $labels[] = $date->format('d/m');
                    $periodKeys[] = $date->toDateString();
                }
        }

        $totals = $this->getGroupedRevenueTotals($from, $to, $groupBy);
        $data = array_map(fn (int|string $periodKey): float => $totals[$periodKey] ?? 0.0, $periodKeys);

        return response()->json([
            'labels' => $labels,
            'data' => $data,
            'filter' => $filter,
            'filter_label' => $this->getFilterLabel($filter),
        ]);
    }

    /**
     * @return array<int|string, float>
     */
    private function getGroupedRevenueTotals(Carbon $from, Carbon $to, string $groupBy): array
    {
        $driver = DB::connection()->getDriverName();
        $groupExpression = match ($groupBy) {
            'hour' => $driver === 'sqlite'
                ? 'CAST(strftime(\'%H\', "NgayTao") AS INTEGER)'
                : 'EXTRACT(HOUR FROM "NgayTao")::integer',
            'month' => $driver === 'sqlite'
                ? 'CAST(strftime(\'%m\', "NgayTao") AS INTEGER)'
                : 'EXTRACT(MONTH FROM "NgayTao")::integer',
            default => 'DATE("NgayTao")',
        };

        return DonHang::query()
            ->selectRaw($groupExpression.' AS period_key, SUM("ThanhTien") AS total')
            ->whereBetween('NgayTao', [$from, $to])
            ->where(function ($query): void {
                $query->whereIn('TrangThai', OrderStatus::settledValues())
                    ->orWhereHas('hoaDons', function ($invoiceQuery): void {
                        $invoiceQuery->where('TrangThai', InvoiceStatus::Paid->value);
                    });
            })
            ->groupByRaw($groupExpression)
            ->get()
            ->mapWithKeys(fn ($row): array => [
                $groupBy === 'day' ? (string) $row->period_key : (int) $row->period_key => (float) $row->total,
            ])
            ->all();
    }

    /**
     * Nhãn hiển thị cho từng filter.
     */
    private function getFilterLabel(string $filter): string
    {
        return match ($filter) {
            'today' => 'Doanh thu hôm nay',
            '7_days' => 'Doanh thu 7 ngày gần nhất',
            'this_month' => 'Doanh thu tháng này',
            'this_year' => 'Doanh thu năm '.now()->year,
            default => 'Doanh thu 7 ngày gần nhất',
        };
    }
}
