<?php

namespace App\Http\Controllers\Staff;

use App\Enums\BookingStatus;
use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\DonHang;
use App\Models\GiaoNhan;
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
        $nhanVienId = $user->nhanVien?->NhanVienID;
        $today = Carbon::today();
        $statusFlow = $this->orderService->getStatusFlow();
        $quickStatusFlow = collect([
            OrderStatus::Pending,
            OrderStatus::Received,
            OrderStatus::Washing,
            OrderStatus::Washed,
            OrderStatus::Delivering,
        ])->mapWithKeys(fn (OrderStatus $status) => [$status->value => $status->label()]);

        $orderCounts = DonHang::query()
            ->select('TrangThai')
            ->selectRaw('COUNT(*) AS total')
            ->whereIn('TrangThai', $quickStatusFlow->keys())
            ->groupBy('TrangThai')
            ->pluck('total', 'TrangThai');

        // --- KPI: Đơn hàng chờ tiếp nhận / kiểm tra đồ ---
        $waitingReceiveCount = (int) $orderCounts->get(OrderStatus::Pending->value, 0);

        // --- KPI: Đơn hàng đang giặt / đang xử lý ---
        $washingCount = (int) $orderCounts->only([
            OrderStatus::Received->value,
            OrderStatus::Washing->value,
        ])->sum();

        // --- KPI: Đơn hàng đã giặt xong (chờ giao/khách đến lấy) ---
        $readyCount = (int) $orderCounts->only([
            OrderStatus::Washed->value,
            OrderStatus::Delivering->value,
        ])->sum();

        // --- KPI: Lượng nhận đồ / giao đồ được phân công hôm nay ---
        $todayDeliveryCounts = GiaoNhan::whereDate('ThoiGianDuKien', $today)
            ->when($nhanVienId, fn ($q) => $q->where('NhanVienID', $nhanVienId))
            ->where('TrangThai', '!=', DeliveryStatus::Cancelled->dbValue())
            ->select('LoaiGiaoNhan')
            ->selectRaw('COUNT(*) AS total')
            ->groupBy('LoaiGiaoNhan')
            ->pluck('total', 'LoaiGiaoNhan');

        $pickupCount = (int) $todayDeliveryCounts->get('NHAN_DO', 0);
        $deliveryCount = (int) $todayDeliveryCounts->get('GIAO_DO', 0);

        // --- Danh sách: Đơn hàng cần xử lý ---
        $processingOrders = DonHang::with([
            'khachHang',
            'nhanVien',
            'booking:BookingID,HinhThucTraDo',
            'giaoNhans:GiaoNhanID,DonHangID,NhanVienID,LoaiGiaoNhan,TrangThai',
        ])
            ->whereIn('TrangThai', [
                OrderStatus::Pending->value,
                OrderStatus::Received->value,
                OrderStatus::Washing->value,
                OrderStatus::Washed->value,
            ])
            ->orderBy('NgayTao', 'asc')
            ->limit(50)
            ->get();

        // --- Lịch nhận đồ / giao đồ hôm nay ---
        $todaySchedule = GiaoNhan::whereDate('ThoiGianDuKien', $today)
            ->when($nhanVienId, fn ($q) => $q->where('NhanVienID', $nhanVienId))
            ->whereNotIn('TrangThai', [
                DeliveryStatus::Cancelled->dbValue(),
                DeliveryStatus::Completed->dbValue(),
            ])
            ->with(['donHang.khachHang', 'nhanVien'])
            ->orderBy('ThoiGianDuKien', 'asc')
            ->get();

        // --- Lịch hẹn sắp tới (Bookings) ---
        $upcomingBookings = Booking::whereDate('NgayHen', '>=', $today)
            ->whereIn('TrangThai', [BookingStatus::Pending->value, BookingStatus::Confirmed->value])
            ->with('khachHang')
            ->orderBy('NgayHen', 'asc')
            ->orderBy('GioHen', 'asc')
            ->limit(10)
            ->get();

        return view('staff.dashboard', [
            'statusFlow' => $statusFlow,
            'quickStatusFlow' => $quickStatusFlow,
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
    public function updateOrderStatus(Request $request, DonHang $order)
    {
        $newStatus = $request->input('status');

        $allowed = [
            OrderStatus::Pending->value,
            OrderStatus::Received->value,
            OrderStatus::Washing->value,
            OrderStatus::Washed->value,
        ];
        if ($order->statusEnum() === OrderStatus::Washed) {
            if (! $order->requiresHomeDelivery()) {
                $allowed[] = OrderStatus::Delivered->value;
            }
        }

        if (! in_array($newStatus, $allowed, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Trạng thái không hợp lệ.',
            ], 400);
        }

        $order = $this->orderService->updateStatus($order, (string) $newStatus);

        return response()->json([
            'success' => true,
            'message' => 'Trạng thái đơn hàng đã được cập nhật.',
            'status' => $order->TrangThai,
            'status_label' => $this->orderService->getStatusFlow()[$order->TrangThai] ?? $order->TrangThai,
        ]);
    }
}
