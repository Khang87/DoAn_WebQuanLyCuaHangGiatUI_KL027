<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\OrderStatus;
use App\Exceptions\SettledOrderException;
use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\DonHangResource;
use App\Models\DonHang;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DonHangController extends ApiController
{
    public function __construct(
        private OrderService $orderService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('orders.view');

        $paginator = DonHang::query()
            ->with(['khachHang', 'nhanVien', 'chiTietDonHangs.dichVu', 'chiTietDonHangs.loaiDoGiat', 'khuyenMai'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();
                $query->where('MaDonHang', 'like', "%{$search}%")
                    ->orWhereHas('khachHang', fn ($q) => $q->where('HoTen', 'like', "%{$search}%"));
            })
            ->when($request->filled('customer_id'), fn ($query) => $query->where('KhachHangID', $request->integer('customer_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('TrangThai', $request->string('status')->toString()))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('NgayTao', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('NgayTao', '<=', $request->date('date_to')))
            ->when($request->boolean('locked'), fn ($query) => $query->whereIn('TrangThai', OrderStatus::settledValues()))
            ->latest('NgayTao')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return $this->paginatedResponse($request, DonHangResource::collection($paginator), $paginator);
    }

    public function show(Request $request, DonHang $order): JsonResponse
    {
        Gate::authorize('orders.view');

        $order->load(['khachHang', 'nhanVien', 'chiTietDonHangs.dichVu', 'chiTietDonHangs.loaiDoGiat', 'khuyenMai', 'hoaDons']);

        return $this->itemResponse($request, new DonHangResource($order));
    }

    /**
     * Đổi trạng thái đơn. Chỉ quản lý được phép, và đơn đã quyết toán trả 409.
     */
    public function updateStatus(Request $request, DonHang $order): JsonResponse
    {
        Gate::authorize('orders.update_status');

        $data = $request->validate([
            'status' => ['required', 'in:'.implode(',', OrderStatus::values())],
        ]);

        try {
            $order = $this->orderService->updateStatus($order, $data['status']);
        } catch (SettledOrderException $e) {
            return $this->errorResponse($e->getMessage(), 409);
        }

        return response()->json([
            'success' => true,
            'message' => 'Trạng thái đơn hàng đã được cập nhật.',
            'data' => (new DonHangResource($order->load('khachHang')))->resolve($request),
        ]);
    }
}
