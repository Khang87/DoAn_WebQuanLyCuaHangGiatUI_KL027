<?php

namespace App\Http\Controllers\Admin;

use App\Enums\InvoiceStatus;
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
use App\Support\OrderStatusRollup;
use App\Support\TimePeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DashboardController extends Controller
{
    public function __construct(
        private PaymentService $paymentService,
        private CollectedRevenueService $collectedRevenueService,
    ) {}

    public function index(Request $request)
    {
        $period = $this->resolveDashboardPeriod($request);
        $dashboardFilter = $period['filter'];
        $from = $period['from'];
        $until = $period['until'];
        $periodLabel = $period['date_from'] !== null
            ? 'Từ '.Carbon::parse($period['date_from'])->format('d/m/Y').' đến '.Carbon::parse($period['date_to'])->format('d/m/Y')
            : TimePeriod::label($dashboardFilter);
        $periodDescription = $period['date_from'] !== null
            ? 'Giờ Việt Nam (UTC+7)'
            : TimePeriod::description($dashboardFilter);

        $revenueChartData = $this->buildRevenueChartData($dashboardFilter, $from, $until);
        $totalRevenue = (float) array_sum($revenueChartData['data']);
        $orders = $this->ordersInPeriod(DonHang::query(), $from, $until);
        $statusRollup = OrderStatusRollup::counts($orders);
        $statusDistribution = $statusRollup['distribution'];
        $totalOrders = $statusRollup['total'];
        $statusCounts = [
            'completed' => $statusRollup['completed'],
            'processing' => $statusRollup['processing'],
            'cancelled' => $statusRollup['cancelled'],
        ];
        $totalCustomers = $this->inPeriod(KhachHang::query(), 'NgayTao', $from, $until)->count();
        $reviews = $this->inPeriod(DanhGia::query(), 'NgayDanhGia', $from, $until)
            ->where('TrangThai', 'Hiển thị');
        $reviewStats = (clone $reviews)->selectRaw('COUNT(*) AS total, AVG("SoSao") AS average')->first();
        $averageRating = (float) ($reviewStats->average ?? 0);
        $totalReviews = (int) $reviewStats->total;

        $topServices = DichVu::query()
            ->withCount(['chiTietDonHangs as orders_count' => fn (Builder $details) => $details
                ->select(DB::raw('COUNT(DISTINCT "DonHangID")'))
                ->whereHas('donHang', fn (Builder $query) => $this->ordersInPeriod($query, $from, $until))])
            ->whereHas('chiTietDonHangs.donHang', fn (Builder $query) => $this->ordersInPeriod($query, $from, $until))
            ->orderByDesc('orders_count')->orderBy('DichVuID')->limit(5)->get()
            ->map(fn (DichVu $service): array => [
                'name' => $service->TenDichVu,
                'orders_count' => (int) $service->orders_count,
            ]);

        $recentOrders = (clone $orders)
            ->with(['khachHang.taiKhoan:TaiKhoanID,KhachHangID,AvatarURL', 'nhanVien', 'statusChanges'])
            ->withExists([
                'hoaDons as has_paid_invoice' => fn ($invoiceQuery) => $invoiceQuery
                    ->where('TrangThai', InvoiceStatus::Paid->value),
            ])
            ->orderByDesc('NgayTao')->orderByDesc('DonHangID')->limit(15)->get();
        $latestReviews = (clone $reviews)->with(['khachHang', 'donHang'])
            ->orderByDesc('NgayDanhGia')->orderByDesc('DanhGiaID')->limit(10)->get();

        return view('admin.dashboard', [
            'isAdmin' => true,
            'dashboardFilter' => $dashboardFilter,
            'periodLabel' => $periodLabel,
            'periodDescription' => $periodDescription,
            'dateFrom' => $period['date_from'] ?? '',
            'dateTo' => $period['date_to'] ?? '',
            'totalRevenue' => $totalRevenue,
            'statusCounts' => $statusCounts,
            'totalOrders' => $totalOrders,
            'totalCustomers' => $totalCustomers,
            'averageRating' => $averageRating,
            'totalReviews' => $totalReviews,
            'revenueChartData' => $revenueChartData,
            'statusDistribution' => $statusDistribution,
            'topServices' => $topServices,
            'recentOrders' => $recentOrders,
            'latestReviews' => $latestReviews,
        ]);
    }

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

    public function getRevenueChartData(Request $request)
    {
        $period = $this->resolveDashboardPeriod($request);

        return response()->json($this->buildRevenueChartData($period['filter'], $period['from'], $period['until']));
    }

    private function buildRevenueChartData(string $filter, ?Carbon $from, ?Carbon $until): array
    {
        $groupBy = $filter === TimePeriod::ALL_TIME && $from !== null
            ? 'day'
            : match ($filter) {
                TimePeriod::TODAY => 'hour',
                TimePeriod::ALL_TIME => 'month',
                default => 'day',
            };
        $totals = $this->getGroupedRevenueTotals($from, $until, $groupBy);
        $labels = [];
        $periodKeys = [];

        if ($groupBy === 'hour') {
            for ($hour = 0; $hour < 24; $hour++) {
                $labels[] = sprintf('%02d:00', $hour);
                $periodKeys[] = $hour;
            }
        } elseif ($groupBy === 'month' && $totals !== []) {
            $date = Carbon::createFromFormat('!Y-m', array_key_first($totals), config('app.timezone'));
            $lastMonth = array_key_last($totals);
            while ($date->format('Y-m') <= $lastMonth) {
                $labels[] = $date->format('m/Y');
                $periodKeys[] = $date->format('Y-m');
                $date->addMonth();
            }
        } elseif ($groupBy === 'day') {
            for ($date = $from->copy(); $date->lt($until); $date->addDay()) {
                $labels[] = $date->format('d/m/Y');
                $periodKeys[] = $date->toDateString();
            }
        }

        return [
            'labels' => $labels,
            'data' => array_map(fn (int|string $key): float => $totals[$key] ?? 0.0, $periodKeys),
            'filter' => $filter,
            'filter_label' => 'Doanh thu - '.TimePeriod::label($filter),
        ];
    }

    /**
     * Supabase stores UTC wall time in timestamp-without-time-zone columns.
     */
    private function inPeriod(Builder $query, string $column, ?Carbon $from, ?Carbon $until): Builder
    {
        return $from
            ? $query->where($column, '>=', $from->copy()->utc())->where($column, '<', $until->copy()->utc())
            : $query;
    }

    private function ordersInPeriod(Builder $query, ?Carbon $from, ?Carbon $until): Builder
    {
        if ($from === null || $until === null) {
            return $query;
        }

        return $query->where(function (Builder $period) use ($from, $until): void {
            $period->where(function (Builder $utc) use ($from, $until): void {
                $utc->whereNotNull('BookingID')
                    ->where('NgayTao', '>=', $from->copy()->utc())
                    ->where('NgayTao', '<', $until->copy()->utc());
            })->orWhere(function (Builder $local) use ($from, $until): void {
                $local->whereNull('BookingID')
                    ->where('NgayTao', '>=', $from)
                    ->where('NgayTao', '<', $until);
            });
        });
    }

    private function paymentsInPeriod(Builder $query, ?Carbon $from, ?Carbon $until): Builder
    {
        if ($from === null || $until === null) {
            return $query;
        }

        return $query->where(function (Builder $period) use ($from, $until): void {
            $period->where(function (Builder $utc) use ($from, $until): void {
                $utc->where('MaGiaoDich', 'like', 'RPC-%')
                    ->where('ThoiGian', '>=', $from->copy()->utc())
                    ->where('ThoiGian', '<', $until->copy()->utc());
            })->orWhere(function (Builder $local) use ($from, $until): void {
                $local->where(function (Builder $source): void {
                    $source->whereNull('MaGiaoDich')
                        ->orWhere('MaGiaoDich', 'not like', 'RPC-%');
                })->where('ThoiGian', '>=', $from)
                    ->where('ThoiGian', '<', $until);
            });
        });
    }

    private function getGroupedRevenueTotals(?Carbon $from, ?Carbon $until, string $groupBy): array
    {
        $sqlite = DB::connection()->getDriverName() === 'sqlite';
        $localTimestamp = $sqlite
            ? 'CASE WHEN "MaGiaoDich" LIKE \'RPC-%\' THEN datetime("ThoiGian", \'+7 hours\') ELSE "ThoiGian" END'
            : 'CASE WHEN "MaGiaoDich" LIKE \'RPC-%\' THEN ("ThoiGian" + INTERVAL \'7 hours\') ELSE "ThoiGian" END';
        $groupExpression = match ($groupBy) {
            'hour' => $sqlite
                ? 'CAST(strftime(\'%H\', '.$localTimestamp.') AS INTEGER)'
                : 'EXTRACT(HOUR FROM '.$localTimestamp.')::integer',
            'month' => $sqlite
                ? 'strftime(\'%Y-%m\', '.$localTimestamp.')'
                : 'TO_CHAR('.$localTimestamp.', \'YYYY-MM\')',
            default => 'DATE('.$localTimestamp.')',
        };

        return $this->paymentsInPeriod($this->collectedRevenueService->query(), $from, $until)
            ->selectRaw($groupExpression.' AS period_key, SUM("SoTien") AS total')
            ->groupByRaw($groupExpression)->orderBy('period_key')->get()
            ->mapWithKeys(fn ($row): array => [
                $groupBy === 'hour' ? (int) $row->period_key : (string) $row->period_key => (float) $row->total,
            ])->all();
    }

    /**
     * @return array{filter: string, from: Carbon|null, until: Carbon|null, date_from: string|null, date_to: string|null}
     */
    private function resolveDashboardPeriod(Request $request): array
    {
        $filter = TimePeriod::normalize($request->query('filter'));
        $validated = $request->validate([
            'date_from' => ['nullable', 'date_format:Y-m-d', 'required_with:date_to'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'required_with:date_from', 'after_or_equal:date_from'],
        ]);
        $dateFrom = $filter === TimePeriod::ALL_TIME ? ($validated['date_from'] ?? null) : null;
        $dateTo = $filter === TimePeriod::ALL_TIME ? ($validated['date_to'] ?? null) : null;

        if ($dateFrom !== null && $dateTo !== null) {
            $from = Carbon::createFromFormat('!Y-m-d', $dateFrom, config('app.timezone'));
            $until = Carbon::createFromFormat('!Y-m-d', $dateTo, config('app.timezone'))->addDay();

            return compact('filter', 'from', 'until') + [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ];
        }

        [$from, $until] = TimePeriod::bounds($filter);

        return compact('filter', 'from', 'until') + [
            'date_from' => null,
            'date_to' => null,
        ];
    }
}
