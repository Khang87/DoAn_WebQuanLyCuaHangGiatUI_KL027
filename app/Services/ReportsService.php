<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Service;
use App\Models\ServiceCategory;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ReportsService
{
    /**
     * Get date range from filter parameters.
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
     * Get KPI metrics for the dashboard.
     */
    public function getKpiMetrics(array $filters): array
    {
        $dates = $this->getDateRange($filters);
        $from = $dates['from'];
        $to = $dates['to'];

        // Total revenue from completed/paid orders
        $totalRevenue = Order::whereBetween('created_at', [$from, $to])
            ->whereIn('status', [OrderStatus::Completed->value, OrderStatus::ReadyForPickup->value])
            ->sum('total_amount');

        // Total orders with breakdown
        $totalOrders = Order::whereBetween('created_at', [$from, $to])->count();
        $completedOrders = Order::whereBetween('created_at', [$from, $to])
            ->whereIn('status', [OrderStatus::Completed->value, OrderStatus::ReadyForPickup->value])
            ->count();
        $processingOrders = Order::whereBetween('created_at', [$from, $to])
            ->whereIn('status', [OrderStatus::Pending->value, OrderStatus::Processing->value])
            ->count();
        $cancelledOrders = Order::whereBetween('created_at', [$from, $to])
            ->where('status', OrderStatus::Cancelled->value)
            ->count();

        // New bookings (confirmed)
        $newBookings = Booking::whereBetween('created_at', [$from, $to])
            ->where('status', BookingStatus::Confirmed->value)
            ->count();

        // Average Order Value
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
     * Get revenue chart data (daily for the period).
     */
    public function getRevenueChartData(array $filters): array
    {
        $dates = $this->getDateRange($filters);
        $from = $dates['from'];
        $to = $dates['to'];

        $daysDiff = $from->diffInDays($to);

        // Determine grouping: daily if <= 31 days, weekly if > 31 days
        $groupByDay = $daysDiff <= 31;

        $query = Order::whereBetween('created_at', [$from, $to])
            ->whereIn('status', [OrderStatus::Completed->value, OrderStatus::ReadyForPickup->value])
            ->selectRaw('DATE(created_at) as date, SUM(total_amount) as revenue, COUNT(*) as order_count');

        if ($groupByDay) {
            $query->groupBy('date')->orderBy('date');
        } else {
            $query->selectRaw('YEARWEEK(created_at) as week, SUM(total_amount) as revenue, COUNT(*) as order_count')
                ->groupBy('week')
                ->orderBy('week');
        }

        $data = $query->get();

        $labels = [];
        $revenue = [];
        $orders = [];

        if ($groupByDay) {
            $period = CarbonPeriod::create($from, $to);
            foreach ($period as $date) {
                $key = $date->format('Y-m-d');
                $labels[] = $date->format('d/m');
                $item = $data->firstWhere('date', $key);
                $revenue[] = $item ? (float) $item->revenue : 0;
                $orders[] = $item ? (int) $item->order_count : 0;
            }
        } else {
            // Weekly labels
            $startWeek = $from->copy()->startOfWeek();
            $endWeek = $to->copy()->endOfWeek();
            $weeks = [];
            $current = $startWeek->copy();
            while ($current->lte($endWeek)) {
                $weeks[] = $current->copy();
                $current->addWeek();
            }
            foreach ($weeks as $week) {
                $key = (int) $week->format('oW');
                $labels[] = $week->format('d/m') . '-' . $week->copy()->endOfWeek()->format('d/m');
                $item = $data->firstWhere('week', $key);
                $revenue[] = $item ? (float) $item->revenue : 0;
                $orders[] = $item ? (int) $item->order_count : 0;
            }
        }

        return [
            'labels' => $labels,
            'revenue' => $revenue,
            'orders' => $orders,
        ];
    }

    /**
     * Get service category composition (pie chart).
     */
    public function getServiceComposition(array $filters): array
    {
        $dates = $this->getDateRange($filters);
        $from = $dates['from'];
        $to = $dates['to'];

        $data = OrderItem::join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('services', 'order_items.service_id', '=', 'services.id')
            ->leftJoin('service_categories', 'services.service_category_id', '=', 'service_categories.id')
            ->whereBetween('orders.created_at', [$from, $to])
            ->whereIn('orders.status', [OrderStatus::Completed->value, OrderStatus::ReadyForPickup->value])
            ->selectRaw('COALESCE(service_categories.name, "Khác") as category, SUM(order_items.subtotal) as revenue, COUNT(*) as count')
            ->groupBy('category')
            ->orderByDesc('revenue')
            ->get();

        return [
            'labels' => $data->pluck('category')->toArray(),
            'revenue' => $data->pluck('revenue')->map(fn($v) => (float) $v)->toArray(),
            'counts' => $data->pluck('count')->map(fn($v) => (int) $v)->toArray(),
        ];
    }

    /**
     * Get top 5 services by revenue.
     */
    public function getTopServices(array $filters, int $limit = 5): Collection
    {
        $dates = $this->getDateRange($filters);
        $from = $dates['from'];
        $to = $dates['to'];

        return OrderItem::join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('services', 'order_items.service_id', '=', 'services.id')
            ->whereBetween('orders.created_at', [$from, $to])
            ->whereIn('orders.status', [OrderStatus::Completed->value, OrderStatus::ReadyForPickup->value])
            ->selectRaw('services.id, services.name, services.unit, SUM(order_items.quantity) as total_qty, SUM(order_items.subtotal) as total_revenue')
            ->groupBy('services.id', 'services.name', 'services.unit')
            ->orderByDesc('total_revenue')
            ->limit($limit)
            ->get();
    }

    /**
     * Get revenue by payment method.
     */
    public function getRevenueByPaymentMethod(array $filters): Collection
    {
        $dates = $this->getDateRange($filters);
        $from = $dates['from'];
        $to = $dates['to'];

        return Payment::join('orders', 'payments.order_id', '=', 'orders.id')
            ->whereBetween('payments.created_at', [$from, $to])
            ->where('payments.status', PaymentStatus::Paid->value)
            ->selectRaw('payments.method, SUM(payments.amount) as total_amount, COUNT(*) as transaction_count')
            ->groupBy('payments.method')
            ->orderByDesc('total_amount')
            ->get();
    }

    /**
     * Get recent orders for table.
     */
    public function getRecentOrders(array $filters): LengthAwarePaginator
    {
        $dates = $this->getDateRange($filters);
        $from = $dates['from'];
        $to = $dates['to'];

        return Order::with(['customer', 'items.service'])
            ->whereBetween('created_at', [$from, $to])
            ->orderByDesc('created_at')
            ->paginate(10)
            ->withQueryString();
    }
}