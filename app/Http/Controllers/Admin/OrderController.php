<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OrderRequest;
use App\Http\Requests\Admin\OrderItemRequest;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function __construct(
        private OrderService $orderService,
    ) {}

    public function index(Request $request)
    {
        $orders = $this->orderService->getAll([
            'search' => $request->input('search'),
            'customer_id' => $request->input('customer_id'),
            'status' => $request->input('status'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
        ]);

        $customers = \App\Models\Customer::orderBy('name')->get();
        $statusFlow = $this->orderService->getStatusFlow();

        return view('admin.orders.index', compact('orders', 'customers', 'statusFlow'));
    }

    public function create()
    {
        $customers = \App\Models\Customer::orderBy('name')->get();
        $services = \App\Models\Service::where('status', 'active')->orderBy('name')->get();
        $statusFlow = $this->orderService->getStatusFlow();

        return view('admin.orders.create', compact('customers', 'services', 'statusFlow'));
    }

    public function store(OrderRequest $request)
    {
        try {
            $order = $this->orderService->create($request->validated());

            return redirect()->route('orders.show', $order)->with('success', 'Đơn hàng đã được tạo thành công.');
        } catch (\Exception $e) {
            return redirect()->route('orders.create')->with('error', 'Có lỗi xảy ra: ' . $e->getMessage())->withInput();
        }
    }

    public function show(int $id)
    {
        $order = $this->orderService->find($id);

        if (!$order) {
            abort(404);
        }

        $statusFlow = $this->orderService->getStatusFlow();

        return view('admin.orders.show', compact('order', 'statusFlow'));
    }

    public function edit(int $id)
    {
        $order = $this->orderService->find($id);

        if (!$order) {
            abort(404);
        }

        $customers = \App\Models\Customer::orderBy('name')->get();
        $services = \App\Models\Service::where('status', 'active')->orderBy('name')->get();
        $statusFlow = $this->orderService->getStatusFlow();

        return view('admin.orders.edit', compact('order', 'customers', 'services', 'statusFlow'));
    }

    public function update(OrderRequest $request, int $id)
    {
        $order = $this->orderService->find($id);

        if (!$order) {
            abort(404);
        }

        try {
            $this->orderService->update($order, $request->validated());

            return redirect()->route('orders.index')->with('success', 'Đơn hàng đã được cập nhật.');
        } catch (\Exception $e) {
            return redirect()->route('orders.edit', $order)->with('error', 'Có lỗi xảy ra: ' . $e->getMessage())->withInput();
        }
    }

    public function destroy(int $id)
    {
        $order = $this->orderService->find($id);

        if (!$order) {
            abort(404);
        }

        try {
            $this->orderService->delete($order);

            return redirect()->route('orders.index')->with('success', 'Đơn hàng đã được xóa.');
        } catch (\Exception $e) {
            return redirect()->route('orders.index')->with('error', 'Có lỗi xảy ra: ' . $e->getMessage());
        }
    }

    public function updateStatus(Request $request, int $id)
    {
        $order = $this->orderService->find($id);

        if (!$order) {
            abort(404);
        }

        try {
            $order->update(['status' => $request->input('status')]);

            return back()->with('success', 'Trạng thái đơn hàng đã được cập nhật.');
        } catch (\Exception $e) {
            return back()->with('error', 'Có lỗi xảy ra: ' . $e->getMessage());
        }
    }
}
