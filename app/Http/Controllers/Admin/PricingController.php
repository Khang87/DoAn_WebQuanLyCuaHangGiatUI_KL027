<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pricing;
use Illuminate\Http\Request;

class PricingController extends Controller
{
    public function index()
    {
        return view('admin.pricings.index', ['pricings' => Pricing::latest()->get()]);
    }

    public function create()
    {
        return view('admin.pricings.create');
    }

    public function store(Request $request)
    {
        Pricing::create($request->validate(['name' => 'required|string|max:255', 'unit' => 'required|string|max:20', 'price' => 'required|numeric|min:0', 'status' => 'nullable|string', 'description' => 'nullable|string']));
        return redirect()->route('pricings.index')->with('success', 'Bảng giá đã được tạo thành công.');
    }

    public function show($id)
    {
        return view('admin.pricings.show', ['pricing' => Pricing::findOrFail($id)]);
    }

    public function edit($id)
    {
        return view('admin.pricings.edit', ['pricing' => Pricing::findOrFail($id)]);
    }

    public function update(Request $request, $id)
    {
        Pricing::findOrFail($id)->update($request->validate(['name' => 'required|string|max:255', 'unit' => 'required|string|max:20', 'price' => 'required|numeric|min:0', 'status' => 'nullable|string', 'description' => 'nullable|string']));
        return redirect()->route('pricings.index')->with('success', 'Bảng giá đã được cập nhật.');
    }

    public function destroy($id)
    {
        Pricing::findOrFail($id)->delete();
        return redirect()->route('pricings.index')->with('success', 'Bảng giá đã được xóa.');
    }
}
