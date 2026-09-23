<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index()
    {
        return view('admin.payments.index');
    }

    public function create()
    {
        return view('admin.payments.create');
    }

    public function store(Request $request)
    {
        return redirect()->route('payments.index')->with('success', 'Thanh toán đã được tạo thành công.');
    }

    public function show($id)
    {
        return view('admin.payments.show', compact('id'));
    }

    public function edit($id)
    {
        return view('admin.payments.edit', compact('id'));
    }

    public function update(Request $request, $id)
    {
        return redirect()->route('payments.index')->with('success', 'Thanh toán đã được cập nhật.');
    }

    public function destroy($id)
    {
        return redirect()->route('payments.index')->with('success', 'Thanh toán đã được xóa.');
    }
}
