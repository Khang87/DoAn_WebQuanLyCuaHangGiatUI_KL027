<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Review;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __construct(
        private \App\Services\ReviewService $reviewService,
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

        // --- KPI: Doanh thu hôm nay ---
        $todayRevenue = $this->revenueBetween($today->copy()->startOfDay(), $today->copy()->endOfDay());

        $prevDayRevenue = $this->revenueBetween(
            $today->copy()->subDay()->startOfDay(),
            $today->copy()->subDay()->endOfDay()
        );

        $todayRevenueChange = $this->percentChange($todayRevenue, $prevDayRevenue);

        // --- KPI: Doanh thu tuần này (từ thứ 2) ---
        $weekStart = $today->copy()->startOfWeek(Carbon::MONDAY);
        $weekRevenue = $this->revenueBetween($weekStart, $today->copy()->endOfDay());

        $prevWeekStart = $weekStart->copy()->subWeek();
        $prevWeekRevenue = $this->revenueBetween($prevWeekStart, $prevWeekStart->copy()->endOfWeek(Carbon::SUNDAY));

        $weekRevenueChange = $this->percentChange($weekRevenue, $prevWeekRevenue);

        // --- KPI: Doanh thu tháng này ---
        $monthStart = $today->copy()->startOfMonth();
        $monthRevenue = $this->revenueBetween($monthStart, $today->copy()->endOfDay());

        $prevMonthStart = $monthStart->copy()->subMonth();
        $prevMonthRevenue = $this->revenueBetween($prevMonthStart, $prevMonthStart->copy()->endOfMonth());

        $monthRevenueChange = $this->percentChange($monthRevenue, $prevMonthRevenue);

        // --- KPI: Tổng số đơn hàng (theo trạng thái) ---
        $statusCounts = [
            'completed' => Order::where('status', 'completed')->count(),
            'processing' => Order::whereIn('status', ['pending', 'received', 'sorting', 'processing', 'washed', 'delivering'])->count(),
            'cancelled' => Order::where('status', 'cancelled')->count(),
        ];

        $totalOrders = Order::count();

        // --- KPI: Khách hàng ---
        $totalCustomers = Customer::count();
        $newCustomersMonth = Customer::whereMonth('created_at', $thisMonth)
            ->whereYear('created_at', $thisYear)
            ->count();

        // --- KPI: Điểm đánh giá trung bình & tổng số lượt đánh giá ---
        $averageRating = $this->reviewService->getAverageRating();
        $totalReviews = $this->reviewService->getTotalReviews();

        // --- Biểu đồ: Doanh thu 7 ngày gần nhất ---
        $last7DaysRevenue = collect(range(6, 0))->map(function ($daysAgo) {
            $date = Carbon::today()->subDays($daysAgo);

            return [
                'date' => $date->format('d/m'),
                'revenue' => $this->revenueBetween($date->copy()->startOfDay(), $date->copy()->endOfDay()),
            ];
        })->values();

        // --- Biểu đồ: Doanh thu 12 tháng gần nhất (cuộn tròn, có giới hạn năm) ---
        $last12Months = collect(range(11, 0))->map(function ($monthsAgo) {
            $month = Carbon::today()->subMonths($monthsAgo);

            return [
                'label' => 'T' . $month->month,
                'revenue' => $this->revenueBetween(
                    $month->copy()->startOfMonth(),
                    $month->copy()->endOfMonth()
                ),
            ];
        })->values();

        // --- Biểu đồ tròn: Tỷ lệ đơn hàng theo trạng thái ---
        $statusDistribution = Order::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        // --- Biểu đồ cột: Top dịch vụ được đặt nhiều nhất ---
        $topServices = Service::withCount('orders')
            ->orderByDesc('orders_count')
            ->limit(5)
            ->get()
            ->map(fn (Service $service) => [
                'name' => $service->name,
                'orders_count' => $service->orders_count,
            ]);

        // --- Bảng: Đơn hàng mới nhất (top 10) ---
        $recentOrders = Order::with(['customer', 'service', 'employee'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // --- Bảng: Đánh giá mới nhất cần phản hồi ---
        $latestReviews = Review::with(['customer', 'order'])
            ->whereNull('shop_response')
            ->orderBy('created_at', 'desc')
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
     * Tổng doanh thu từ đơn hàng đã hoàn thành hoặc đã thanh toán trong khoảng thời gian.
     */
    private function revenueBetween(Carbon $from, Carbon $to): float
    {
        return (float) Order::whereBetween('created_at', [$from, $to])
            ->where(function ($query) {
                $query->where('status', 'completed')
                    ->orWhereHas('invoice', function ($q) {
                        $q->where('status', 'paid');
                    });
            })
            ->sum('total_amount');
    }

    /**
     * Thu tiền mặt cho hóa đơn.
     */
    public function collectCashPayment(Request $request, int $invoice)
    {
        $invoice = Invoice::with('order')->find($invoice);

        if (!$invoice) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy hóa đơn.'], 404);
        }

        if ($invoice->status === 'paid') {
            return response()->json(['success' => false, 'message' => 'Hóa đơn đã được thanh toán.'], 400);
        }

        DB::transaction(function () use ($invoice) {
            $invoice->update(['status' => 'paid']);

            Payment::create([
                'order_id' => $invoice->order_id,
                'amount' => $invoice->total,
                'method' => 'cash',
                'status' => 'paid',
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Thu tiền mặt thành công.',
            'invoice_id' => $invoice->id,
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

        $labels = [];
        $data = [];

        switch ($filter) {
            case 'today':
                // Doanh thu theo giờ trong ngày hôm nay (0-23h)
                for ($hour = 0; $hour <= 23; $hour++) {
                    $from = $today->copy()->setTime($hour, 0, 0);
                    $to = $today->copy()->setTime($hour, 59, 59);
                    $labels[] = sprintf('%02d:00', $hour);
                    $data[] = $this->revenueBetween($from, $to);
                }
                break;

            case '7_days':
                // Doanh thu 7 ngày gần nhất
                for ($daysAgo = 6; $daysAgo >= 0; $daysAgo--) {
                    $date = $today->copy()->subDays($daysAgo);
                    $labels[] = $date->format('d/m');
                    $data[] = $this->revenueBetween($date->copy()->startOfDay(), $date->copy()->endOfDay());
                }
                break;

            case 'this_month':
                // Doanh thu theo ngày trong tháng hiện tại
                $daysInMonth = $today->copy()->daysInMonth;
                for ($day = 1; $day <= $daysInMonth; $day++) {
                    $date = $today->copy()->setDay($day);
                    $labels[] = $date->format('d/m');
                    $data[] = $this->revenueBetween($date->copy()->startOfDay(), $date->copy()->endOfDay());
                }
                break;

            case 'this_year':
                // Doanh thu 12 tháng trong năm hiện tại
                for ($month = 1; $month <= 12; $month++) {
                    $date = Carbon::create($thisYear, $month, 1);
                    $labels[] = 'Tháng ' . $month;
                    $data[] = $this->revenueBetween($date->copy()->startOfMonth(), $date->copy()->endOfMonth());
                }
                break;

            default:
                // Mặc định 7 ngày
                for ($daysAgo = 6; $daysAgo >= 0; $daysAgo--) {
                    $date = $today->copy()->subDays($daysAgo);
                    $labels[] = $date->format('d/m');
                    $data[] = $this->revenueBetween($date->copy()->startOfDay(), $date->copy()->endOfDay());
                }
        }

        return response()->json([
            'labels' => $labels,
            'data' => $data,
            'filter' => $filter,
            'filter_label' => $this->getFilterLabel($filter),
        ]);
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
            'this_year' => 'Doanh thu năm ' . now()->year,
            default => 'Doanh thu 7 ngày gần nhất',
        };
    }
}

