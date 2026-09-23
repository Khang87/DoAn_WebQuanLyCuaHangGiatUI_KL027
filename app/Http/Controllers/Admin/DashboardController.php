<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Promotion;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    /**
     * Hiển thị trang dashboard
     */
    public function index()
    {
        $orders = Order::with(['customer', 'service'])->latest()->get();
        $recentOrders = $orders->take(5);
        $recentCustomers = Customer::latest()->take(5)->get();
        $statusCounts = [
            'pending' => $orders->where('status', 'pending')->count(),
            'processing' => $orders->where('status', 'processing')->count(),
            'completed' => $orders->where('status', 'completed')->count(),
            'cancelled' => $orders->where('status', 'cancelled')->count(),
        ];
        $monthlyRevenue = collect(range(1, 12))->map(fn ($month) => (float) $orders
            ->filter(fn ($order) => $order->created_at?->month === $month)
            ->sum('total_amount'));

        return view('admin.dashboard', [
            'totalOrders' => $orders->count(),
            'revenue' => $orders->sum('total_amount'),
            'newCustomers' => Customer::where('created_at', '>=', Carbon::now()->subDays(30))->count(),
            'pendingOrders' => $statusCounts['pending'],
            'recentOrders' => $recentOrders,
            'recentCustomers' => $recentCustomers,
            'activePromotions' => Promotion::where('status', 'active')->where(function ($query) {
                $query->whereNull('expires_at')->orWhereDate('expires_at', '>=', today());
            })->latest()->take(5)->get(),
            'statusCounts' => $statusCounts,
            'monthlyRevenue' => $monthlyRevenue,
        ]);
    }
}
