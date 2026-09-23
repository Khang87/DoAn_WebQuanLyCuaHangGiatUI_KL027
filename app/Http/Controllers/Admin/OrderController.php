<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Service;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    /**
     * Hiển thị danh sách đơn hàng
     */
    public function index()
    {
        return view('admin.orders.index', ['orders' => Order::with(['customer', 'service'])->latest()->get()]);
    }

    /**
     * Hiển thị form tạo đơn hàng mới
     */
    public function create()
    {
        return view('admin.orders.create', [
            'customers' => Customer::orderBy('name')->get(),
            'services' => Service::where('status', 'active')->orderBy('name')->get(),
        ]);
    }

    /**
     * Lưu đơn hàng mới
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'service_id' => ['required', 'exists:services,id'],
            'weight_kg' => ['nullable', 'string', 'max:50'],
            'quantity_items' => ['required', 'string', 'max:255'],
            'total_amount' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'in:pending,processing,completed,cancelled'],
        ]);
        $data['code'] = 'DH' . str_pad((string) ((Order::max('id') ?? 0) + 1), 3, '0', STR_PAD_LEFT);
        Order::create($data);

        return redirect()->route('orders.index')->with('success', 'Đơn hàng đã được tạo thành công.');
    }

    /**
     * Hiển thị chi tiết đơn hàng
     */
    public function show($id)
    {
        return view('admin.orders.show', ['order' => Order::with(['customer', 'service'])->findOrFail($id)]);
    }

    /**
     * Hiển thị form chỉnh sửa đơn hàng
     */
    public function edit($id)
    {
        return view('admin.orders.edit', [
            'order' => Order::findOrFail($id),
            'customers' => Customer::orderBy('name')->get(),
            'services' => Service::where('status', 'active')->orderBy('name')->get(),
        ]);
    }

    /**
     * Cập nhật đơn hàng
     */
    public function update(Request $request, $id)
    {
        $order = Order::findOrFail($id);
        $order->update($request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'service_id' => ['required', 'exists:services,id'],
            'weight_kg' => ['nullable', 'string', 'max:50'],
            'quantity_items' => ['required', 'string', 'max:255'],
            'total_amount' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'in:pending,processing,completed,cancelled'],
        ]));

        return redirect()->route('orders.index')->with('success', 'Đơn hàng đã được cập nhật.');
    }

    /**
     * Xóa đơn hàng
     */
    public function destroy($id)
    {
        Order::findOrFail($id)->delete();

        return redirect()->route('orders.index')->with('success', 'Đơn hàng đã được xóa.');
    }
}
