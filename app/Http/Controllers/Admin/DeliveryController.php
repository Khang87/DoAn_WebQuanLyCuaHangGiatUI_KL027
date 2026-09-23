<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use Illuminate\Http\Request;

class DeliveryController extends Controller
{
    public function index()
    {
        return view('admin.deliveries.index', ['deliveries' => Delivery::with('customer')->latest()->get()]);
    }

    public function create()
    {
        return view('admin.deliveries.create');
    }

    public function store(Request $request)
    {
        Delivery::create($request->validate(['customer_id' => 'nullable|exists:customers,id', 'method' => 'required|in:pickup,dropoff', 'address' => 'nullable|string', 'pickup_date' => 'required|date', 'pickup_time' => 'required', 'status' => 'nullable|string']));
        return redirect()->route('deliveries.index')->with('success', 'Giao nhận đã được tạo thành công.');
    }

    public function show($id)
    {
        return view('admin.deliveries.show', ['delivery' => Delivery::findOrFail($id)]);
    }

    public function edit($id)
    {
        return view('admin.deliveries.edit', ['delivery' => Delivery::findOrFail($id)]);
    }

    public function update(Request $request, $id)
    {
        Delivery::findOrFail($id)->update($request->validate(['customer_id' => 'nullable|exists:customers,id', 'method' => 'required|in:pickup,dropoff', 'address' => 'nullable|string', 'pickup_date' => 'required|date', 'pickup_time' => 'required', 'status' => 'nullable|string']));
        return redirect()->route('deliveries.index')->with('success', 'Giao nhận đã được cập nhật.');
    }

    public function destroy($id)
    {
        Delivery::findOrFail($id)->delete();
        return redirect()->route('deliveries.index')->with('success', 'Giao nhận đã được xóa.');
    }
}
