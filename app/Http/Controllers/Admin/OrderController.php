<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    /**
     * Hiển thị danh sách đơn hàng
     */
    public function index()
    {
        return view('admin.orders.index');
    }

    /**
     * Hiển thị form tạo đơn hàng mới
     */
    public function create()
    {
        return view('admin.orders.create');
    }

    /**
     * Lưu đơn hàng mới
     */
    public function store(Request $request)
    {
        // TODO: Implement store logic
        return redirect()->route('orders.index')->with('success', 'Đơn hàng đã được tạo thành công.');
    }

    /**
     * Hiển thị chi tiết đơn hàng
     */
    public function show($id)
    {
        return view('admin.orders.show', compact('id'));
    }

    /**
     * Hiển thị form chỉnh sửa đơn hàng
     */
    public function edit($id)
    {
        return view('admin.orders.edit', compact('id'));
    }

    /**
     * Cập nhật đơn hàng
     */
    public function update(Request $request, $id)
    {
        // TODO: Implement update logic
        return redirect()->route('orders.index')->with('success', 'Đơn hàng đã được cập nhật.');
    }

    /**
     * Xóa đơn hàng
     */
    public function destroy($id)
    {
        // TODO: Implement destroy logic
        return redirect()->route('orders.index')->with('success', 'Đơn hàng đã được xóa.');
    }
}
