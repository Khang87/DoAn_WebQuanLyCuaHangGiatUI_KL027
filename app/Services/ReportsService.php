<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\OrderStatus;
use App\Models\Booking;
use App\Models\ChiTietDonHang;
use App\Models\DichVu;
use App\Models\DonHang;
use App\Models\ThanhToan;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;

/**
 * Số liệu cho trang Báo cáo, đọc trên schema tiếng Việt của PostgreSQL.
 *
 * Quy ước ánh xạ từ schema cũ:
 *   orders            -> DonHang
 *   order_items       -> ChiTietDonHang
 *   services          -> DichVu
 *   service_categories-> LoaiDichVu
 *   payments          -> ThanhToan
 *   created_at        -> NgayTao
 *   total_amount      -> ThanhTien
 *   status            -> TrangThai (giá trị tiếng Việt, xem OrderStatus)
 */
class ReportsService
{
    /**
     * Trạng thái được coi là đã thu tiền (nằm trong doanh thu).
     *
     * @var list<string>
     */
    private const REVENUE_STATUSES = [
        OrderStatus::Paid->value,
        OrderStatus::Delivered->value,
    ];

    /**
     * Trạng thái đang xử lý.
     *
     * @var list<string>
     */
    private const PROCESSING_STATUSES = [
        OrderStatus::Pending->value,
        OrderStatus::Received->value,
        OrderStatus::Washing->value,
        OrderStatus::Washed->value,
        OrderStatus::Delivering->value,
    ];

    /**
     * Trạng thái thanh toán được tính vào doanh thu theo phương thức.
     *
     * Bảng `ThanhToan.TrangThai` lưu tiếng Việt, khác với `PaymentStatus`.
     */
    private const PAID_PAYMENT_STATUS = 'Thành công';

    /**
     * Trạng thái booking được tính là "đã xác nhận".
     *
     * Bảng `Booking.TrangThai` lưu PascalCase không dấu, khớp với
     * `BookingStatus::Confirmed`.
     */
    private const CONFIRMED_BOOKING_STATUS = BookingStatus::Confirmed->value;

    /**
     * Lấy khoảng thời gian lọc.
     *
     * @return array{from: Carbon, to: Carbon}
     */
    public function getDateRange(array $filters): array
    {
        $range = $filters['range'] ?? 'this_month';
        $from = $filters['date_from'] ?? null;
        $to = $filters['date_to'] ?? null;

        $now = Carbon::now();

        return match ($range) {
            'today' => [
                'from' => $now->copy()->startOfDay(),
                'to' => $now->copy()->endOfDay(),
            ],
            '7_days' => [
                'from' => $now->copy()->subDays(6)->startOfDay(),
                'to' => $now->copy()->endOfDay(),
            ],
            'this_month' => [
                'from' => $now->copy()->startOfMonth(),
                'to' => $now->copy()->endOfMonth(),
            ],
            'last_month' => [
                'from' => $now->copy()->subMonth()->startOfMonth(),
                'to' => $now->copy()->subMonth()->endOfMonth(),
            ],
            'custom' => [
                'from' => $from ? Carbon::parse($from)->startOfDay() : $now->copy()->startOfMonth(),
                'to' => $to ? Carbon::parse($to)->endOfDay() : $now->copy()->endOfMonth(),
            ],
            default => [
                'from' => $now->copy()->startOfMonth(),
                'to' => $now->copy()->endOfMonth(),
            ],
        };
    }

    /**
     * Chỉ số KPI cho dashboard báo cáo.
     */
    public function getKpiMetrics(array $filters): array
    {
        $dates = $this->getDateRange($filters);
        $from = $dates['from'];
        $to = $dates['to'];

        $base = DonHang::whereBetween('NgayTao', [$from, $to]);

        $totalRevenue = (float) (clone $base)
            ->whereIn('TrangThai', self::REVENUE_STATUSES)
            ->sum('ThanhTien');

        $totalOrders = (clone $base)->count();

        $completedOrders = (clone $base)
            ->whereIn('TrangThai', self::REVENUE_STATUSES)
            ->count();

        $processingOrders = (clone $base)
            ->whereIn('TrangThai', self::PROCESSING_STATUSES)
            ->count();

        $cancelledOrders = (clone $base)
            ->where('TrangThai', OrderStatus::Cancelled->value)
            ->count();

        $newBookings = Booking::whereBetween('NgayTao', [$from, $to])
            ->where('TrangThai', self::CONFIRMED_BOOKING_STATUS)
            ->count();

        $avgOrderValue = $completedOrders > 0 ? round($totalRevenue / $completedOrders, 2) : 0;

        return [
            'total_revenue' => $totalRevenue,
            'total_orders' => $totalOrders,
            'completed_orders' => $completedOrders,
            'processing_orders' => $processingOrders,
            'cancelled_orders' => $cancelledOrders,
            'new_bookings' => $newBookings,
            'avg_order_value' => $avgOrderValue,
        ];
    }

    /**
     * Dữ liệu biểu đồ doanh thu: theo ngày nếu khoảng ≤ 31 ngày, nếu không theo tuần.
     */
    public function getRevenueChartData(array $filters): array
    {
        $dates = $this->getDateRange($filters);
        $from = $dates['from'];
        $to = $dates['to'];

        $groupByDay = $from->diffInDays($to) <= 31;

        $query = DonHang::whereBetween('NgayTao', [$from, $to])
            ->whereIn('TrangThai', self::REVENUE_STATUSES);

        if ($groupByDay) {
            // `DATE(...)` của PostgreSQL trên cột nguyên bản, không cần ép kiểu.
            $query->selectRaw('DATE("NgayTao") as date, SUM("ThanhTien") as revenue, COUNT(*) as order_count')
                ->groupBy('date')
                ->orderBy('date');
        } else {
            // Tương đương YEARWEEK() của MySQL: tuần ISO gồm năm và số tuần.
            $query->selectRaw('EXTRACT(ISOYEAR FROM "NgayTao")::int * 100 + EXTRACT(WEEK FROM "NgayTao")::int as week, SUM("ThanhTien") as revenue, COUNT(*) as order_count')
                ->groupBy('week')
                ->orderBy('week');
        }

        $data = $query->get();

        $labels = [];
        $revenue = [];
        $orders = [];

        if ($groupByDay) {
            foreach (CarbonPeriod::create($from, $to) as $date) {
                $item = $data->firstWhere('date', $date->format('Y-m-d'));

                $labels[] = $date->format('d/m');
                $revenue[] = $item ? (float) $item->revenue : 0;
                $orders[] = $item ? (int) $item->order_count : 0;
            }
        } else {
            $current = $from->copy()->startOfWeek();

            while ($current->lte($to)) {
                $item = $data->firstWhere('week', (int) $current->format('oW'));

                $labels[] = $current->format('d/m').'-'.$current->copy()->endOfWeek()->format('d/m');
                $revenue[] = $item ? (float) $item->revenue : 0;
                $orders[] = $item ? (int) $item->order_count : 0;

                $current->addWeek();
            }
        }

        return [
            'labels' => $labels,
            'revenue' => $revenue,
            'orders' => $orders,
        ];
    }

    /**
     * Cơ cấu doanh thu theo nhóm dịch vụ (biểu đồ tròn).
     */
    public function getServiceComposition(array $filters): array
    {
        $dates = $this->getDateRange($filters);
        $from = $dates['from'];
        $to = $dates['to'];

        $data = ChiTietDonHang::query()
            ->join('DonHang', 'DonHang.DonHangID', '=', 'ChiTietDonHang.DonHangID')
            ->join('DichVu', 'DichVu.DichVuID', '=', 'ChiTietDonHang.DichVuID')
            ->leftJoin('LoaiDichVu', 'LoaiDichVu.LoaiDichVuID', '=', 'DichVu.LoaiDichVuID')
            ->whereBetween('DonHang.NgayTao', [$from, $to])
            ->whereIn('DonHang.TrangThai', self::REVENUE_STATUSES)
            ->selectRaw('COALESCE("LoaiDichVu"."TenLoaiDichVu", \'Khác\') as category, SUM("ChiTietDonHang"."ThanhTien") as revenue, COUNT(*) as count')
            ->groupBy('category')
            ->orderByDesc('revenue')
            ->get();

        return [
            'labels' => $data->pluck('category')->toArray(),
            'revenue' => $data->pluck('revenue')->map(fn ($v) => (float) $v)->toArray(),
            'counts' => $data->pluck('count')->map(fn ($v) => (int) $v)->toArray(),
        ];
    }

    /**
     * Top dịch vụ theo doanh thu.
     *
     * Trả về `Support\Collection` vì kết quả đã được đổi tên ở tầng PHP.
     */
    public function getTopServices(array $filters, int $limit = 5): BaseCollection
    {
        $dates = $this->getDateRange($filters);
        $from = $dates['from'];
        $to = $dates['to'];

        // Query gốc là `ChiTietDonHang` nên accessor của chính model này được áp
        // dụng: `getUnitAttribute()` che mất cột `unit` và đọc quan hệ chưa load.
        // Vì vậy lấy ra tên cột trung tính rồi đổi tên ở tầng PHP cho chắc chắn.
        return ChiTietDonHang::query()
            ->join('DonHang', 'DonHang.DonHangID', '=', 'ChiTietDonHang.DonHangID')
            ->join('DichVu', 'DichVu.DichVuID', '=', 'ChiTietDonHang.DichVuID')
            ->leftJoin('DonViTinh', 'DonViTinh.DonViTinhID', '=', 'ChiTietDonHang.DonViTinhID')
            ->whereBetween('DonHang.NgayTao', [$from, $to])
            ->whereIn('DonHang.TrangThai', self::REVENUE_STATUSES)
            // `SoLuong` có thể NULL với dịch vụ tính theo cân (chỉ có `KhoiLuong`),
            // nên COALESCE để tổng số lượng luôn là số chứ không rơi về NULL.
            ->selectRaw('"DichVu"."TenDichVu" as service_name, "DonViTinh"."KyHieu" as unit_symbol, COALESCE(SUM("ChiTietDonHang"."SoLuong"), SUM("ChiTietDonHang"."KhoiLuong"), 0) as total_qty, SUM("ChiTietDonHang"."ThanhTien") as total_revenue')
            ->groupBy('DichVu.TenDichVu', 'DonViTinh.KyHieu')
            ->orderByDesc('total_revenue')
            ->limit($limit)
            ->get()
            // Trả về object thuần với đúng key view dùng: name, unit, total_qty,
            // total_revenue.
            ->map(fn ($row) => (object) [
                'name' => $row->service_name,
                'unit' => $row->unit_symbol,
                'total_qty' => (float) $row->total_qty,
                'total_revenue' => (float) $row->total_revenue,
            ]);
    }

    /**
     * Doanh thu theo phương thức thanh toán.
     */
    public function getRevenueByPaymentMethod(array $filters): Collection
    {
        $dates = $this->getDateRange($filters);
        $from = $dates['from'];
        $to = $dates['to'];

        return ThanhToan::query()
            ->whereBetween('ThoiGian', [$from, $to])
            ->where('TrangThai', self::PAID_PAYMENT_STATUS)
            // `PhuongThuc` lưu tiếng Việt, view tự có nhãn tra theo mã.
            ->selectRaw('"PhuongThuc" as method, SUM("SoTien") as total_amount, COUNT(*) as transaction_count')
            ->groupBy('PhuongThuc')
            ->orderByDesc('total_amount')
            ->get()
            // Cột `numeric` của PostgreSQL trả về chuỗi, ép về số cho view format.
            ->each(function ($row) {
                $row->setAttribute('total_amount', (float) $row->total_amount);
                $row->setAttribute('transaction_count', (int) $row->transaction_count);
            });
    }

    /**
     * Danh sách đơn hàng mới nhất.
     */
    public function getRecentOrders(array $filters): LengthAwarePaginator
    {
        $dates = $this->getDateRange($filters);
        $from = $dates['from'];
        $to = $dates['to'];

        return DonHang::with(['khachHang', 'chiTietDonHangs.dichVu'])
            ->whereBetween('NgayTao', [$from, $to])
            ->orderByDesc('NgayTao')
            ->paginate(10)
            ->withQueryString();
    }
}
