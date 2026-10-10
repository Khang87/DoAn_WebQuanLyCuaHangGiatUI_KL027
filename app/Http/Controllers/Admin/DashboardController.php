<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\SettledOrderException;
use App\Http\Controllers\Controller;
use App\Models\DanhGia;
use App\Models\DichVu;
use App\Models\DonHang;
use App\Models\HoaDon;
use App\Models\KhachHang;
use App\Services\CollectedRevenueService;
use App\Services\PaymentService;
use App\Services\ReviewService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DashboardController extends Controller
{
    public function __construct(
        private ReviewService $reviewService,
        private PaymentService $paymentService,
        private CollectedRevenueService $collectedRevenueService,
    ) {}

    /**
     * Hiển thị trang Dashboard dành cho Quản lý.
     * Bao gồm: KPI tài chính, biểu đồ, bảng dữ liệu nhanh.
     */
    public function index()
    {
        $today = Carbon::today(config('app.timezone'));
        $thisMonth = $today->month;
        $thisYear = $today->year;
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
            ->select('TrangThai')
            ->selectRaw('COUNT(*) AS total')
            ->groupBy('TrangThai')
            ->get();

        $statusCounts = ['completed' => 0, 'processing' => 0, 'cancelled' => 0];
        $totalOrders = 0;
        foreach ($orderCounts as $orderCount) {
            $count = (int) $orderCount->total;
            $totalOrders += $count;

            if ($orderCount->TrangThai === OrderStatus::Delivered->value) {
                $statusCounts['completed'] += $count;
            } elseif ($orderCount->TrangThai === OrderStatus::Cancelled->value) {
                $statusCounts['cancelled'] += $count;
            } elseif (in_array($orderCount->TrangThai, [
                OrderStatus::Pending->value,
                OrderStatus::Received->value,
                OrderStatus::Washing->value,
                OrderStatus::Washed->value,
                OrderStatus::Delivering->value,
            ], true)) {
                $statusCounts['processing'] += $count;
            }
        }

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
        $statusDistribution = DonHang::query()
            ->selectRaw('"TrangThai", COUNT(*) as count')
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
        $recentOrders = DonHang::query()
            ->with(['khachHang', 'nhanVien'])
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
        $localTimestamp = $this->localTimestampExpression('"ThoiGian"');
        $localDate = 'DATE('.$localTimestamp.')';

        return $this->collectedRevenueService->query()
            ->selectRaw($localDate.' AS revenue_date, SUM("SoTien") AS total')
            ->whereBetween('ThoiGian', [
                $from->copy()->startOfDay()->utc(),
                $to->copy()->endOfDay()->utc(),
            ])
            ->groupByRaw($localDate)
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

        try {
            $payment = $this->paymentService->create([
                'invoice_id' => $invoice->HoaDonID,
                'amount' => $invoice->ThanhTien,
                'method' => 'cash',
                'status' => PaymentStatus::Paid->value,
                'paid_at' => now(),
            ]);
        } catch (ValidationException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->errors()['amount'][0]
                    ?? $exception->errors()['invoice_id'][0]
                    ?? $exception->errors()['order_id'][0]
                    ?? 'Không thể ghi nhận thanh toán.',
            ], 422);
        } catch (SettledOrderException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 409);
        }

        return response()->json([
            'success' => true,
            'message' => 'Thu tiền mặt thành công.',
            'invoice_id' => $invoice->HoaDonID,
            'payment_id' => $payment->ThanhToanID,
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
        $today = Carbon::today(config('app.timezone'));
        $thisYear = $today->year;
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
                $from = Carbon::create($thisYear, 1, 1, 0, 0, 0, config('app.timezone'))->startOfYear();
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
        $localTimestamp = $this->localTimestampExpression('"ThoiGian"');
        $groupExpression = match ($groupBy) {
            'hour' => $driver === 'sqlite'
                ? 'CAST(strftime(\'%H\', '.$localTimestamp.') AS INTEGER)'
                : 'EXTRACT(HOUR FROM '.$localTimestamp.')::integer',
            'month' => $driver === 'sqlite'
                ? 'CAST(strftime(\'%m\', '.$localTimestamp.') AS INTEGER)'
                : 'EXTRACT(MONTH FROM '.$localTimestamp.')::integer',
            default => 'DATE('.$localTimestamp.')',
        };

        return $this->collectedRevenueService->query()
            ->selectRaw($groupExpression.' AS period_key, SUM("SoTien") AS total')
            ->whereBetween('ThoiGian', [$from->copy()->utc(), $to->copy()->utc()])
            ->groupByRaw($groupExpression)
            ->get()
            ->mapWithKeys(fn ($row): array => [
                $groupBy === 'day' ? (string) $row->period_key : (int) $row->period_key => (float) $row->total,
            ])
            ->all();
    }

    private function localTimestampExpression(string $column): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "datetime({$column}, '+7 hours')"
            : "({$column} + INTERVAL '7 hours')";
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
