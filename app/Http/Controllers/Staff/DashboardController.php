<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Delivery;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function __construct(
        private OrderService $orderService,
    ) {}

    /**
     * Hiển thị trang Dashboard dành cho Nhân viên.
     * Chỉ hiển thị thông tin vận hành, KHÔNG có dữ liệu tài chính.
     */
    public function index()
    {
        $user = auth()->user();
        $today = Carbon::today();
        $statusFlow = $this->orderService->getStatusFlow();

        // --- KPI: Đơn hàng chờ tiếp nhận / kiểm tra đồ ---
        $waitingReceiveCount = Order::where('status', 'pending')->count();

        // --- KPI: Đơn hàng đang giặt / đang xử lý ---
        $washingCount = Order::whereIn('status', ['received', 'sorting', 'processing'])
            ->count();

        // --- KPI: Đơn hàng đã giặt xong (chờ giao/khách đến lấy) ---
        $readyCount = Order::whereIn('status', ['washed', 'delivering'])->count();

        // --- KPI: Lượng nhận đồ / giao đồ được phân công hôm nay ---
        $todayDeliveries = Delivery::whereDate('pickup_date', $today)
            ->where('employee_id', $user->id)
            ->whereNotIn('status', ['cancelled'])
            ->with(['customer', 'order'])
            ->get();

        $pickupCount = $todayDeliveries->where('method', 'nhan_do')->count();
        $deliveryCount = $todayDeliveries->where('method', 'giao_do')->count();

        // --- Danh sách: Đơn hàng cần xử lý ---
        $processingOrders = Order::with(['customer', 'service', 'employee'])
            ->whereIn('status', ['pending', 'received', 'sorting', 'processing'])
            ->orderBy('created_at', 'asc')
            ->limit(50)
            ->get();

        // --- Lịch nhận đồ / giao đồ hôm nay ---
        $todaySchedule = Delivery::whereDate('pickup_date', $today)
            ->where('employee_id', $user->id)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->with(['customer', 'order'])
            ->orderBy('pickup_time', 'asc')
            ->get();

        // --- Lịch hẹn sắp tới (Bookings) ---
        $upcomingBookings = Booking::whereDate('scheduled_date', '>=', $today)
            ->whereIn('status', ['pending', 'confirmed'])
            ->with(['customer', 'staff'])
            ->orderBy('scheduled_date', 'asc')
            ->orderBy('scheduled_time', 'asc')
            ->limit(10)
            ->get();

        return view('staff.dashboard', [
            'statusFlow' => $statusFlow,
            'waitingReceiveCount' => $waitingReceiveCount,
            'washingCount' => $washingCount,
            'readyCount' => $readyCount,
            'pickupCount' => $pickupCount,
            'deliveryCount' => $deliveryCount,
            'processingOrders' => $processingOrders,
            'todaySchedule' => $todaySchedule,
            'upcomingBookings' => $upcomingBookings,
        ]);
    }

    /**
     * Cập nhật trạng thái đơn hàng nhanh từ dashboard nhân viên (AJAX).
     */
    public function updateOrderStatus(Request $request, Order $order)
    {
        $newStatus = $request->input('status');

        $allowed = ['pending', 'received', 'sorting', 'processing', 'washed', 'delivering'];

        if (!in_array($newStatus, $allowed)) {
            return response()->json([
                'success' => false,
                'message' => 'Trạng thái không hợp lệ.',
            ], 400);
        }

        // Đơn đã quyết toán (hoàn thành hoặc đã thanh toán) là chỉ đọc.
        if ($order->isLocked()) {
            return response()->json([
                'success' => false,
                'message' => 'Đơn hàng '.$order->code.' đã quyết toán nên không thể đổi trạng thái.',
            ], 409);
        }

        $order->update(['status' => $newStatus]);

        return response()->json([
            'success' => true,
            'message' => 'Trạng thái đơn hàng đã được cập nhật.',
            'status' => $order->status,
            'status_label' => $this->orderService->getStatusFlow()[$order->status] ?? $order->status,
        ]);
    }
}
