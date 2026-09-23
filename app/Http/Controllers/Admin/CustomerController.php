<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    /**
     * Hiển thị danh sách khách hàng
     */
    public function index()
    {
        return view('admin.customers.index');
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
        return redirect()->route('customers.index')->with('success', 'Khách hàng đã được tạo thành công.');
    }

    /**
     * Hiển thị chi tiết khách hàng
     */
    public function show($id)
    {
        return view('admin.customers.show', compact('id'));
    }

    /**
     * Hiển thị form chỉnh sửa khách hàng
     */
    public function edit($id)
    {
        return view('admin.customers.edit', compact('id'));
    }

    /**
     * Cập nhật khách hàng
     */
    public function update(Request $request, $id)
    {
        return redirect()->route('customers.index')->with('success', 'Khách hàng đã được cập nhật.');
    }

    /**
     * Xóa khách hàng
     */
    public function destroy($id)
    {
        return redirect()->route('customers.index')->with('success', 'Khách hàng đã được xóa.');
    }
}
