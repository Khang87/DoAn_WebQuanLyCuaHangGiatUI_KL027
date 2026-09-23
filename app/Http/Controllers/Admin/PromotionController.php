<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PromotionController extends Controller
{
    public function index()
    {
        return view('admin.promotions.index');
    }

    public function create()
    {
        return view('admin.promotions.create');
    }

    public function store(Request $request)
    {
        return redirect()->route('promotions.index')->with('success', 'Khuyến mãi đã được tạo thành công.');
    }

    public function show($id)
    {
        return view('admin.promotions.show', compact('id'));
    }

    public function edit($id)
    {
        return view('admin.promotions.edit', compact('id'));
    }

    public function update(Request $request, $id)
    {
        return redirect()->route('promotions.index')->with('success', 'Khuyến mãi đã được cập nhật.');
    }

    public function destroy($id)
    {
        return redirect()->route('promotions.index')->with('success', 'Khuyến mãi đã được xóa.');
    }
}
