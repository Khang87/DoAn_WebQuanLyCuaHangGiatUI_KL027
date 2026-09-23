<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    /**
     * Hiển thị danh sách khách hàng
     */
    public function index()
    {
        return view('admin.customers.index', ['customers' => Customer::latest()->get()]);
    }

    /**
     * Hiển thị form tạo khách hàng mới
     */
    public function create()
    {
        return view('admin.customers.create');
    }

    /**
     * Lưu khách hàng mới
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string'],
            'type' => ['nullable', 'string', 'max:50'],
        ]);
        $data['code'] = 'KH' . str_pad((string) ((Customer::max('id') ?? 0) + 1), 3, '0', STR_PAD_LEFT);
        Customer::create($data);

        return redirect()->route('customers.index')->with('success', 'Khách hàng đã được tạo thành công.');
    }

    /**
     * Hiển thị chi tiết khách hàng
     */
    public function show($id)
    {
        return view('admin.customers.show', ['customer' => Customer::with(['orders.service'])->findOrFail($id)]);
    }

    /**
     * Hiển thị form chỉnh sửa khách hàng
     */
    public function edit($id)
    {
        return view('admin.customers.edit', ['customer' => Customer::findOrFail($id)]);
    }

    /**
     * Cập nhật khách hàng
     */
    public function update(Request $request, $id)
    {
        $customer = Customer::findOrFail($id);
        $customer->update($request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string'],
            'type' => ['nullable', 'string', 'max:50'],
        ]));

        return redirect()->route('customers.index')->with('success', 'Khách hàng đã được cập nhật.');
    }

    /**
     * Xóa khách hàng
     */
    public function destroy($id)
    {
        Customer::findOrFail($id)->delete();

        return redirect()->route('customers.index')->with('success', 'Khách hàng đã được xóa.');
    }
}
