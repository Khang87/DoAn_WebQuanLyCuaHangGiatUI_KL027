<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OrderItemRequest;
use App\Models\OrderItem;
use Illuminate\Http\Request;

class OrderItemController extends Controller
{
    public function index(Request $request)
    {
        $items = OrderItem::query();

        if (!empty($request->input('order_id'))) {
            $items->where('order_id', $request->input('order_id'));
        }

        $items = $items->with('order', 'service')->latest()->paginate(20);

        $orders = \App\Models\Order::orderBy('code')->get();

        return view('admin.order-items.index', compact('items', 'orders'));
    }

    public function create()
    {
        $orders = \App\Models\Order::orderBy('code')->get();
        $services = \App\Models\Service::where('status', 'active')->orderBy('name')->get();

        return view('admin.order-items.create', compact('orders', 'services'));
    }

    public function store(OrderItemRequest $request)
    {
        try {
            $data = $request->validated();
            $data['subtotal'] = $data['price'] * $data['quantity'];

            OrderItem::create($data);

            return redirect()->route('order-items.index')->with('success', 'Chi tiết đơn hàng đã được thêm.');
        } catch (\Exception $e) {
            return redirect()->route('order-items.create')->with('error', 'Có lỗi xảy ra: ' . $e->getMessage())->withInput();
        }
    }

    public function show(int $id)
    {
        $item = OrderItem::with('order', 'service')->findOrFail($id);

        return view('admin.order-items.show', compact('item'));
    }

    public function edit(int $id)
    {
        $item = OrderItem::findOrFail($id);
        $orders = \App\Models\Order::orderBy('code')->get();
        $services = \App\Models\Service::where('status', 'active')->orderBy('name')->get();

        return view('admin.order-items.edit', compact('item', 'orders', 'services'));
    }

    public function update(OrderItemRequest $request, int $id)
    {
        try {
            $item = OrderItem::findOrFail($id);
            $data = $request->validated();
            $data['subtotal'] = $data['price'] * $data['quantity'];
            $item->update($data);

            return redirect()->route('order-items.index')->with('success', 'Chi tiết đã được cập nhật.');
        } catch (\Exception $e) {
            return redirect()->route('order-items.edit', $id)->with('error', 'Có lỗi xảy ra: ' . $e->getMessage())->withInput();
        }
    }

    public function destroy(int $id)
    {
        try {
            OrderItem::findOrFail($id)->delete();

            return redirect()->route('order-items.index')->with('success', 'Đã xóa chi tiết.');
        } catch (\Exception $e) {
            return redirect()->route('order-items.index')->with('error', 'Có lỗi xảy ra: ' . $e->getMessage());
        }
    }
}
