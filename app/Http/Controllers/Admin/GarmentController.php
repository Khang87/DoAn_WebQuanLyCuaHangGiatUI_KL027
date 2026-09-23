<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class GarmentController extends Controller
{
    public function index()
    {
        return view('admin.garments.index');
    }

    public function create()
    {
        return view('admin.garments.create');
    }

    public function store(Request $request)
    {
        return redirect()->route('garments.index')->with('success', 'Loại đồ giặt đã được tạo thành công.');
    }

    public function show($id)
    {
        return view('admin.garments.show', compact('id'));
    }

    public function edit($id)
    {
        return view('admin.garments.edit', compact('id'));
    }

    public function update(Request $request, $id)
    {
        return redirect()->route('garments.index')->with('success', 'Loại đồ giặt đã được cập nhật.');
    }

    public function destroy($id)
    {
        return redirect()->route('garments.index')->with('success', 'Loại đồ giặt đã được xóa.');
    }
}
