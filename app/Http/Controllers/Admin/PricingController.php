<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PricingController extends Controller
{
    public function index()
    {
        return view('admin.pricings.index');
    }

    public function create()
    {
        return view('admin.pricings.create');
    }

    public function store(Request $request)
    {
        return redirect()->route('pricings.index')->with('success', 'Bảng giá đã được tạo thành công.');
    }

    public function show($id)
    {
        return view('admin.pricings.show', compact('id'));
    }

    public function edit($id)
    {
        return view('admin.pricings.edit', compact('id'));
    }

    public function update(Request $request, $id)
    {
        return redirect()->route('pricings.index')->with('success', 'Bảng giá đã được cập nhật.');
    }

    public function destroy($id)
    {
        return redirect()->route('pricings.index')->with('success', 'Bảng giá đã được xóa.');
    }
}
