<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\InvoiceStatus;
use App\Models\Booking;
use App\Models\ChiTietDonHang;
use App\Models\DonHang;
use App\Support\OrderStatusRollup;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Facades\DB;

/**
 * Số liệu cho trang Báo cáo, đọc trên schema tiếng Việt của PostgreSQL.
 *
 * Doanh thu lấy từ một giao dịch thu đủ, đã thành công; số lượng và phân loại
 * đơn lấy từ DonHang. Tên bảng/cột tuân thủ schema PascalCase.
 */
class ReportsService
{
    private const REPORT_TIMEZONE = 'Asia/Ho_Chi_Minh';

    public function __construct(
        private CollectedRevenueService $collectedRevenueService,
    ) {}

    /**
     * Lấy khoảng thời gian lọc.
     *
     * @return array{from: Carbon|null, to: Carbon|null, local_from: Carbon|null, local_to: Carbon|null, range: string}
     */
    public function getDateRange(array $filters): array
    {
        $range = $filters['range'] ?? 'all_time';
        $from = $filters['date_from'] ?? null;
        $to = $filters['date_to'] ?? null;

        $now = Carbon::now(self::REPORT_TIMEZONE);

        if ($range === 'all_time') {
            return [
                'from' => null,
                'to' => null,
                'local_from' => null,
                'local_to' => null,
                'range' => $range,
            ];
        }

        $localDates = match ($range) {
            'today' => [
                'from' => $now->copy()->startOfDay(),
                'to' => $now->copy()->endOfDay(),
            ],
            'this_week' => [
                'from' => $now->copy()->startOfWeek(Carbon::MONDAY),
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
                'from' => $from ? Carbon::parse($from, self::REPORT_TIMEZONE)->startOfDay() : $now->copy()->startOfMonth(),
                'to' => $to ? Carbon::parse($to, self::REPORT_TIMEZONE)->endOfDay() : $now->copy()->endOfMonth(),
            ],
            default => [
                'from' => $now->copy()->startOfMonth(),
                'to' => $now->copy()->endOfMonth(),
            ],
        };

        return [
            // UTC-created records use naive UTC columns; local writers are filtered separately.
            'from' => $localDates['from']->copy()->utc(),
            'to' => $localDates['to']->copy()->utc(),
            'local_from' => $localDates['from'],
            'local_to' => $localDates['to'],
            'range' => $range,
        ];
    }

    /**
     * Chỉ số KPI cho dashboard báo cáo.
     */
    public function getKpiMetrics(array $filters): array
    {
        $dates = $this->getDateRange($filters);
        $from = $dates['from'];
        $to = $dates['to'];

        $base = DonHang::query();
        $paidPayments = $this->collectedRevenueService->query();

        $this->ordersInRange($base, $dates);
        $this->paymentsInRange($paidPayments, $dates);

        $totalRevenue = (float) (clone $paidPayments)->sum('SoTien');

        $orderCounts = OrderStatusRollup::counts($base);

        $totalOrders = $orderCounts['total'];
        $completedOrders = $orderCounts['completed'];
        $processingOrders = $orderCounts['processing'];
        $cancelledOrders = $orderCounts['cancelled'];

        $bookings = Booking::query()->where('TrangThai', BookingStatus::Pending->value);

        if ($from !== null && $to !== null) {
            $bookings->whereBetween('NgayTao', [$from, $to]);
        }

        $newBookings = $bookings->count();

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

        $localFrom = $dates['local_from'];
        $localTo = $dates['local_to'];
        $groupBy = $dates['range'] === 'all_time'
            ? 'month'
            : ($localFrom->diffInDays($localTo) <= 31 ? 'day' : 'week');

        $query = $this->collectedRevenueService->query();

        $this->paymentsInRange($query, $dates);

        $localTimestamp = $this->localTimestampExpression('"ThoiGian"');

        if ($groupBy === 'day') {
            $localDateExpression = $this->localDateExpression($localTimestamp);
            $query->selectRaw($localDateExpression.' as date, SUM("SoTien") as revenue, COUNT(DISTINCT "DonHangID") as order_count')
                ->groupBy('date')
                ->orderBy('date');
        } elseif ($groupBy === 'week') {
            $localWeekExpression = $this->localWeekExpression($localTimestamp);
            $query->selectRaw($localWeekExpression.' as week, SUM("SoTien") as revenue, COUNT(DISTINCT "DonHangID") as order_count')
                ->groupBy('week')
                ->orderBy('week');
        } else {
            $localMonthExpression = $this->localMonthExpression($localTimestamp);
            $query->selectRaw($localMonthExpression.' as month, SUM("SoTien") as revenue, COUNT(DISTINCT "DonHangID") as order_count')
                ->groupBy('month')
                ->orderBy('month');
        }

        $data = $query->get();
        $dataByDate = $data->keyBy('date');
        $dataByWeek = $data->keyBy('week');

        $labels = [];
        $revenue = [];
        $orders = [];

        if ($groupBy === 'day') {
            foreach (CarbonPeriod::create($localFrom, $localTo) as $date) {
                $item = $dataByDate->get($date->format('Y-m-d'));

                $labels[] = $date->format('d/m/Y');
                $revenue[] = $item ? (float) $item->revenue : 0;
                $orders[] = $item ? (int) $item->order_count : 0;
            }
        } elseif ($groupBy === 'week') {
            $current = $localFrom->copy()->startOfWeek();

            while ($current->lte($localTo)) {
                $item = $dataByWeek->get((int) $current->format('oW'));

                $labels[] = $current->format('d/m/Y').'-'.$current->copy()->endOfWeek()->format('d/m/Y');
                $revenue[] = $item ? (float) $item->revenue : 0;
                $orders[] = $item ? (int) $item->order_count : 0;

                $current->addWeek();
            }
        } else {
            foreach ($data as $item) {
                $labels[] = Carbon::parse((string) $item->month.'-01', self::REPORT_TIMEZONE)->format('m/Y');
                $revenue[] = (float) $item->revenue;
                $orders[] = (int) $item->order_count;
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
            ->leftJoin('LoaiDoGiat', 'LoaiDoGiat.LoaiDoGiatID', '=', 'ChiTietDonHang.LoaiDoGiatID')
            ->whereIn('DonHang.DonHangID', $this->collectedRevenueService->query()->select('DonHangID'))
            ->selectRaw('COALESCE("LoaiDichVu"."TenLoaiDichVu", "DichVu"."TenDichVu", "LoaiDoGiat"."TenLoaiDoGiat", \'Khác\') as category, SUM("ChiTietDonHang"."ThanhTien") as revenue, COUNT(*) as count')
            ->groupBy('category')
            ->orderByDesc('revenue')
            ->when($from !== null && $to !== null, fn ($query) => $query->whereIn(
                'DonHang.DonHangID',
                $this->paymentsInRange($this->collectedRevenueService->query(), $dates)->select('DonHangID'),
            ))
            ->get();

        $totalRevenue = (float) $data->sum('revenue');

        return [
            'labels' => $data->pluck('category')->toArray(),
            'revenue' => $data->pluck('revenue')->map(fn ($v) => (float) $v)->toArray(),
            'counts' => $data->pluck('count')->map(fn ($v) => (int) $v)->toArray(),
            'percentages' => $data->pluck('revenue')
                ->map(fn ($value) => $totalRevenue > 0 ? round((float) $value / $totalRevenue * 100, 1) : 0)
                ->toArray(),
        ];
    }

    /**
     * Top dịch vụ theo tổng số lượng đặt.
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
            ->whereIn('DonHang.DonHangID', $this->collectedRevenueService->query()->select('DonHangID'))
            ->selectRaw('"DichVu"."TenDichVu" as service_name, CASE WHEN COUNT(DISTINCT "DonViTinh"."KyHieu") > 1 THEN \'Nhiều ĐVT\' ELSE MAX("DonViTinh"."KyHieu") END as unit_symbol, SUM(COALESCE("ChiTietDonHang"."SoLuong", "ChiTietDonHang"."KhoiLuong", 0)) as total_qty, SUM("ChiTietDonHang"."ThanhTien") as total_revenue')
            ->groupBy('DichVu.DichVuID', 'DichVu.TenDichVu')
            ->orderByDesc('total_qty')
            ->orderByDesc('total_revenue')
            ->limit($limit)
            ->when($from !== null && $to !== null, fn ($query) => $query->whereIn(
                'DonHang.DonHangID',
                $this->paymentsInRange($this->collectedRevenueService->query(), $dates)->select('DonHangID'),
            ))
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
    public function getRevenueByPaymentMethod(array $filters): BaseCollection
    {
        $dates = $this->getDateRange($filters);
        $from = $dates['from'];
        $to = $dates['to'];

        $query = $this->collectedRevenueService->query()
            ->selectRaw('COALESCE(NULLIF(TRIM("ThanhToan"."PhuongThuc"), \'\'), \'Khác\') as method, SUM("ThanhToan"."SoTien") as total_amount, COUNT("ThanhToan"."ThanhToanID") as transaction_count')
            ->groupBy('method');

        if ($from !== null && $to !== null) {
            $this->paymentsInRange($query, $dates);
        }

        return $query
            ->orderByDesc('total_amount')
            ->get()
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

        $query = DonHang::query()
            ->select([
                'DonHangID',
                'MaDonHang',
                'BookingID',
                'KhachHangID',
                'TrangThai',
                'ThanhTien',
                'NgayTao',
            ])
            ->withExists([
                'hoaDons as has_paid_invoice' => fn ($invoiceQuery) => $invoiceQuery
                    ->where('TrangThai', InvoiceStatus::Paid->value),
            ]);

        $this->ordersInRange($query, $dates);

        return $query
            ->with([
                'khachHang:KhachHangID,HoTen,AvatarUrl',
                'khachHang.taiKhoan:TaiKhoanID,KhachHangID,AvatarURL',
                'chiTietDonHangs:ChiTietDonHangID,DonHangID,DichVuID',
                'chiTietDonHangs.dichVu:DichVuID,TenDichVu',
                'statusChanges',
            ])
            ->orderByDesc('NgayTao')
            ->paginate(10)
            ->withQueryString();
    }

    /**
     * Lấy toàn bộ đơn trong kỳ để xuất báo cáo, không giới hạn phân trang.
     *
     * @return BaseCollection<int, DonHang>
     */
    public function getOrdersForExport(array $filters): BaseCollection
    {
        $dates = $this->getDateRange($filters);

        $query = DonHang::query()
            ->select([
                'DonHangID',
                'MaDonHang',
                'BookingID',
                'KhachHangID',
                'TrangThai',
                'ThanhTien',
                'NgayTao',
            ])
            ->with([
                'khachHang:KhachHangID,HoTen',
                'chiTietDonHangs:ChiTietDonHangID,DonHangID,DichVuID',
                'chiTietDonHangs.dichVu:DichVuID,TenDichVu',
            ]);

        $this->ordersInRange($query, $dates);

        return $query->orderByDesc('NgayTao')->get();
    }

    /**
     * Order timestamps from booking conversion use UTC database defaults; manually
     * entered web orders use the application's Vietnam-local clock.
     *
     * @param  array{from: Carbon|null, to: Carbon|null, local_from: Carbon|null, local_to: Carbon|null, range: string}  $dates
     */
    private function ordersInRange(Builder $query, array $dates): Builder
    {
        if ($dates['from'] === null || $dates['to'] === null) {
            return $query;
        }

        return $query->where(function (Builder $range) use ($dates): void {
            $range->where(function (Builder $utc) use ($dates): void {
                $utc->whereNotNull('DonHang.BookingID')
                    ->whereBetween('DonHang.NgayTao', [$dates['from'], $dates['to']]);
            })->orWhere(function (Builder $local) use ($dates): void {
                $local->whereNull('DonHang.BookingID')
                    ->whereBetween('DonHang.NgayTao', [$dates['local_from'], $dates['local_to']]);
            });
        });
    }

    /**
     * RPC payments use UTC defaults; web-entered payments store Vietnam-local time.
     *
     * @param  array{from: Carbon|null, to: Carbon|null, local_from: Carbon|null, local_to: Carbon|null, range: string}  $dates
     */
    private function paymentsInRange(Builder $query, array $dates): Builder
    {
        if ($dates['from'] === null || $dates['to'] === null) {
            return $query;
        }

        return $query->where(function (Builder $range) use ($dates): void {
            $range->where(function (Builder $utc) use ($dates): void {
                $utc->where('ThanhToan.MaGiaoDich', 'like', 'RPC-%')
                    ->whereBetween('ThanhToan.ThoiGian', [$dates['from'], $dates['to']]);
            })->orWhere(function (Builder $local) use ($dates): void {
                $local->where(function (Builder $source): void {
                    $source->whereNull('ThanhToan.MaGiaoDich')
                        ->orWhere('ThanhToan.MaGiaoDich', 'not like', 'RPC-%');
                })->whereBetween('ThanhToan.ThoiGian', [$dates['local_from'], $dates['local_to']]);
            });
        });
    }

    private function localTimestampExpression(string $column): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "CASE WHEN \"MaGiaoDich\" LIKE 'RPC-%' THEN datetime({$column}, '+7 hours') ELSE {$column} END"
            : "CASE WHEN \"MaGiaoDich\" LIKE 'RPC-%' THEN ({$column} + INTERVAL '7 hours') ELSE {$column} END";
    }

    private function localDateExpression(string $timestamp): string
    {
        return "DATE({$timestamp})";
    }

    private function localWeekExpression(string $timestamp): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "(CAST(strftime('%Y', {$timestamp}) AS INTEGER) * 100 + CAST(strftime('%W', {$timestamp}) AS INTEGER))"
            : "(EXTRACT(ISOYEAR FROM {$timestamp})::int * 100 + EXTRACT(WEEK FROM {$timestamp})::int)";
    }

    private function localMonthExpression(string $timestamp): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', {$timestamp})"
            : "TO_CHAR({$timestamp}, 'YYYY-MM')";
    }
}
