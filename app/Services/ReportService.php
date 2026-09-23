<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ReportService
{
    public function getRevenueData(string $period = 'month'): array
    {
        $format = match ($period) {
            'day' => '%Y-%m-%d',
            'week' => '%Y-%W',
            'year' => '%Y',
            default => '%Y-%m',
        };

        $query = Order::selectRaw("SUM(total_amount) as total, strftime('{$format}', created_at) as period")
            ->groupBy('period');

        return $query->orderBy('period')->get()->map(fn ($item) => [
            'period' => $item->period,
            'total' => (float) $item->total,
        ])->toArray();
    }

    public function getOrderStatusCounts(): array
    {
        return Order::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();
    }

    public function getTopCustomers(int $limit = 10): Collection
    {
        return Order::selectRaw('customer_id, SUM(total_amount) as total_spent, COUNT(*) as order_count')
            ->where('status', '!=', 'cancelled')
            ->groupBy('customer_id')
            ->orderByDesc('total_spent')
            ->limit($limit)
            ->with('customer')
            ->get()
            ->filter(fn ($item) => $item->customer !== null);
    }

    public function getOrderCountsByService(): array
    {
        return Order::selectRaw('service_id, COUNT(*) as count')
            ->groupBy('service_id')
            ->pluck('count', 'service_id')
            ->toArray();
    }

    public function getRevenueSummary(): array
    {
        return [
            'total_revenue' => (float) Order::where('status', '!=', 'cancelled')->sum('total_amount'),
            'total_orders' => Order::count(),
            'completed_orders' => Order::where('status', 'completed')->count(),
            'cancelled_orders' => Order::where('status', 'cancelled')->count(),
            'pending_orders' => Order::where('status', 'pending')->count(),
            'processing_orders' => Order::where('status', 'processing')->count(),
            'today_revenue' => (float) Order::whereDate('created_at', today())->sum('total_amount'),
            'month_revenue' => (float) Order::whereMonth('created_at', now()->month)->sum('total_amount'),
        ];
    }

    public function getMonthlyRevenue(): array
    {
        return collect(range(1, 12))->map(function ($month) {
            $revenue = Order::whereMonth('created_at', $month)
                ->where('status', '!=', 'cancelled')
                ->sum('total_amount');

            return round($revenue / 1000000, 2);
        })->values()->toArray();
    }
}
