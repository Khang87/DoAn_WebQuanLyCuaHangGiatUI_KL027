<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\OrderStatus;
use App\Exceptions\SettledOrderException;
use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends ApiController
{
    public function __construct(
        private OrderService $orderService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = Order::query()
            ->with(['customer', 'employee', 'items.service', 'items.garment', 'promotion'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();
                $query->where('code', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($q) => $q->where('name', 'like', "%{$search}%"));
            })
            ->when($request->filled('customer_id'), fn ($query) => $query->where('customer_id', $request->integer('customer_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('created_at', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('created_at', '<=', $request->date('date_to')))
            ->when($request->boolean('locked'), fn ($query) => $query->where('status', 'completed'))
            ->latest()
            ->paginate($this->perPage($request))
            ->withQueryString();

        return $this->paginatedResponse($request, OrderResource::collection($paginator), $paginator);
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        $order->load(['customer', 'employee', 'items.service', 'items.garment', 'promotion', 'invoice']);

        return $this->itemResponse($request, new OrderResource($order));
    }

    /**
     * Đổi trạng thái đơn. Chỉ quản lý được phép, và đơn đã quyết toán trả 409.
     */
    public function updateStatus(Request $request, Order $order): JsonResponse
    {
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
            'data' => (new OrderResource($order->load('customer')))->resolve($request),
        ]);
    }
}
