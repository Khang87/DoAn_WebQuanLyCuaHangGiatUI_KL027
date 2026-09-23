<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DeliveryController extends Controller
{
    public function index()
    {
        return view('admin.deliveries.index');
    }

    public function create()
    {
        return view('admin.deliveries.create');
    }

    public function store(Request $request)
    {
        return redirect()->route('deliveries.index')->with('success', 'Giao nhận đã được tạo thành công.');
    }

    public function show($id)
    {
        return view('admin.deliveries.show', compact('id'));
    }

    public function edit($id)
    {
        return view('admin.deliveries.edit', compact('id'));
    }

    public function update(Request $request, $id)
    {
        return redirect()->route('deliveries.index')->with('success', 'Giao nhận đã được cập nhật.');
    }

    public function destroy($id)
    {
        return redirect()->route('deliveries.index')->with('success', 'Giao nhận đã được xóa.');
    }
}
