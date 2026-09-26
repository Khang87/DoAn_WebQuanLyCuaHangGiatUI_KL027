<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\RejectsSettledRecords;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OrderItemRequest;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OrderItemController extends Controller
{
    use RejectsSettledRecords;

    /**
     * Đơn đã quyết toán thì các dòng mặt hàng của đơn cũng bị khoá theo.
     */
    private function assertOrderEditable(?OrderItem $item, Request $request, string $fallbackUrl): ?Response
    {
        $order = $item?->order;

        if ($order?->isLocked()) {
            return $this->rejectSettled(
                $request,
                'Đơn hàng '.$order->code.' đã quyết toán nên không thể thay đổi chi tiết mặt hàng.',
                $fallbackUrl
            );
        }

        return null;
    }

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
        $order = \App\Models\Order::find($request->input('order_id'));

        if ($order?->isLocked()) {
            return $this->rejectSettled(
                $request,
                'Đơn hàng '.$order->code.' đã quyết toán nên không thể thêm chi tiết mặt hàng.',
                route('order-items.index')
            );
        }

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
        $item = OrderItem::with('order')->findOrFail($id);

        if ($rejected = $this->assertOrderEditable($item, $request, route('order-items.index'))) {
            return $rejected;
        }

        try {
            $data = $request->validated();
            $data['subtotal'] = $data['price'] * $data['quantity'];
            $item->update($data);

            return redirect()->route('order-items.index')->with('success', 'Chi tiết đã được cập nhật.');
        } catch (\Exception $e) {
            return redirect()->route('order-items.edit', $id)->with('error', 'Có lỗi xảy ra: ' . $e->getMessage())->withInput();
        }
    }

    public function destroy(Request $request, int $id)
    {
        $item = OrderItem::with('order')->findOrFail($id);

        if ($rejected = $this->assertOrderEditable($item, $request, route('order-items.index'))) {
            return $rejected;
        }

        try {
            $item->delete();

            return redirect()->route('order-items.index')->with('success', 'Đã xóa chi tiết.');
        } catch (\Exception $e) {
            return redirect()->route('order-items.index')->with('error', 'Có lỗi xảy ra: ' . $e->getMessage());
        }
    }
}
