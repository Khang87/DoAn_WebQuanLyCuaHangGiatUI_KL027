<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Promotion;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    /**
     * Hiển thị trang dashboard
     */
    public function index()
    {
        $isAdmin = auth()->user()->isAdmin();
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

        // Staff & Admin shared data
        $todayDeliveries = Delivery::whereDate('pickup_date', today())
            ->whereNotIn('status', ['cancelled'])
            ->with('customer')
            ->latest()
            ->take(5)
            ->get();

        $pendingInvoices = Invoice::where('status', 'unpaid')
            ->with('order.customer')
            ->latest()
            ->take(5)
            ->get();

        $newCustomersToday = Customer::whereDate('created_at', today())->count();

        // Orders needing processing (pending + washing status)
        $processingOrders = $orders->whereIn('status', ['pending', 'washing'])->take(7);

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
            'isAdmin' => $isAdmin,
            'todayDeliveries' => $todayDeliveries,
            'pendingInvoices' => $pendingInvoices,
            'newCustomersToday' => $newCustomersToday,
            'processingOrders' => $processingOrders,
        ]);
    }

    public function collectCashPayment(Request $request, int $id)
    {
        $invoice = Invoice::with('order')->find($id);

        if (!$invoice) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy hóa đơn.'], 404);
        }

        if ($invoice->status === 'paid') {
            return response()->json(['success' => false, 'message' => 'Hóa đơn đã được thanh toán.'], 400);
        }

        \DB::transaction(function () use ($invoice) {
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
}
