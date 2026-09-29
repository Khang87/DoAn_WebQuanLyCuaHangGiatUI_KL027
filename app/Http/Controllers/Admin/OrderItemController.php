<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\RejectsSettledRecords;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OrderItemRequest;
use App\Models\OrderItem;
use App\Models\Pricing;
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

        // Nạp sẵn invoice + payments của đơn để isLocked() không N+1
        $items = $items->with('order', 'order.invoice', 'order.payments', 'service')->latest()->paginate(10);

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

            // Tự động điền đơn giá từ price_lists nếu form không gửi giá.
            if (empty($data['price'])) {
                $pricing = Pricing::getLatestPricing(
                    (int) $data['service_id'],
                    (int) ($data['garment_id'] ?? 0)
                );
                if ($pricing) {
                    $data['price'] = $pricing->price;
                }
            }

            // Tính thành tiền: đơn vị kg → quantity * price * weight;
            // đơn vị khác → quantity * price (bỏ qua khối lượng).
            $pricing = Pricing::getLatestPricing(
                (int) $data['service_id'],
                (int) ($data['garment_id'] ?? 0)
            );
            $isWeightUnit = Pricing::isWeightUnit($pricing?->unit);
            $weight = max(0, (float) ($data['weight'] ?? 0));

            if ($isWeightUnit && $weight > 0) {
                $data['subtotal'] = round($data['price'] * $data['quantity'] * $weight, 2);
            } else {
                $data['subtotal'] = round($data['price'] * $data['quantity'], 2);
            }

            OrderItem::create($data);

            return redirect()->route('order-items.index')->with('success', 'Chi tiết đơn hàng đã được thêm.');
        } catch (\Exception $e) {
            return redirect()->route('order-items.create')->with('error', \App\Support\FriendlyError::message($e))->withInput();
        }
    }

    public function show(int $id)
    {
        $item = OrderItem::with('order', 'service')->findOrFail($id);

        return view('admin.order-items.show', compact('item'));
    }

    public function edit(int $id)
    {
        $item = OrderItem::with('order')->findOrFail($id);

        if ($rejected = $this->assertOrderEditable($item, request(), route('order-items.index'))) {
            return $rejected;
        }

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

            // Tự động điền đơn giá từ price_lists nếu form không gửi giá.
            if (empty($data['price'])) {
                $pricing = Pricing::getLatestPricing(
                    (int) $data['service_id'],
                    (int) ($data['garment_id'] ?? 0)
                );
                if ($pricing) {
                    $data['price'] = $pricing->price;
                }
            }

            // Tính thành tiền: đơn vị kg → quantity * price * weight;
            // đơn vị khác → quantity * price (bỏ khối lượng).
            $pricing = Pricing::getLatestPricing(
                (int) $data['service_id'],
                (int) ($data['garment_id'] ?? 0)
            );
            $isWeightUnit = Pricing::isWeightUnit($pricing?->unit);
            $weight = max(0, (float) ($data['weight'] ?? 0));

            if ($isWeightUnit && $weight > 0) {
                $data['subtotal'] = round($data['price'] * $data['quantity'] * $weight, 2);
            } else {
                $data['subtotal'] = round($data['price'] * $data['quantity'], 2);
            }

            $item->update($data);

            return redirect()->route('order-items.index')->with('success', 'Chi tiết đã được cập nhật.');
        } catch (\Exception $e) {
            return redirect()->route('order-items.edit', $id)->with('error', \App\Support\FriendlyError::message($e))->withInput();
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
            return redirect()->route('order-items.index')->with('error', \App\Support\FriendlyError::message($e));
        }
    }
}
