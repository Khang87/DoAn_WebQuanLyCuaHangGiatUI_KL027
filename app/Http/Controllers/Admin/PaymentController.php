<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index()
    {
        return view('admin.payments.index', ['payments' => Payment::latest()->get()]);
    }

    public function create()
    {
        return view('admin.payments.create');
    }

    public function store(Request $request)
    {
        Payment::create($request->validate(['order_id' => 'nullable|exists:orders,id', 'amount' => 'required|numeric|min:0', 'method' => 'required|string', 'status' => 'nullable|string']));
        return redirect()->route('payments.index')->with('success', 'Thanh toán đã được tạo thành công.');
    }

    public function show($id)
    {
        return view('admin.payments.show', ['payment' => Payment::findOrFail($id)]);
    }

    public function edit($id)
    {
        return view('admin.payments.edit', ['payment' => Payment::findOrFail($id)]);
    }

    public function update(Request $request, $id)
    {
        Payment::findOrFail($id)->update($request->validate(['order_id' => 'nullable|exists:orders,id', 'amount' => 'required|numeric|min:0', 'method' => 'required|string', 'status' => 'nullable|string']));
        return redirect()->route('payments.index')->with('success', 'Thanh toán đã được cập nhật.');
    }

    public function destroy($id)
    {
        Payment::findOrFail($id)->delete();
        return redirect()->route('payments.index')->with('success', 'Thanh toán đã được xóa.');
    }
}
