<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Promotion;
use Illuminate\Http\Request;

class PromotionController extends Controller
{
    public function index()
    {
        return view('admin.promotions.index', ['promotions' => Promotion::latest()->get()]);
    }

    public function create()
    {
        return view('admin.promotions.create');
    }

    public function store(Request $request)
    {
        Promotion::create($request->validate(['name' => 'required|string|max:255', 'code' => 'required|string|max:50|unique:promotions,code', 'discount' => 'required|string|max:50', 'expires_at' => 'nullable|date', 'status' => 'nullable|string']));
        return redirect()->route('promotions.index')->with('success', 'Khuyến mãi đã được tạo thành công.');
    }

    public function show($id)
    {
        return view('admin.promotions.show', ['promotion' => Promotion::findOrFail($id)]);
    }

    public function edit($id)
    {
        return view('admin.promotions.edit', ['promotion' => Promotion::findOrFail($id)]);
    }

    public function update(Request $request, $id)
    {
        Promotion::findOrFail($id)->update($request->validate(['name' => 'required|string|max:255', 'code' => 'required|string|max:50|unique:promotions,code,' . $id, 'discount' => 'required|string|max:50', 'expires_at' => 'nullable|date', 'status' => 'nullable|string']));
        return redirect()->route('promotions.index')->with('success', 'Khuyến mãi đã được cập nhật.');
    }

    public function destroy($id)
    {
        Promotion::findOrFail($id)->delete();
        return redirect()->route('promotions.index')->with('success', 'Khuyến mãi đã được xóa.');
    }
}
