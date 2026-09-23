<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Garment;
use Illuminate\Http\Request;

class GarmentController extends Controller
{
    /**
     * Hiển thị danh sách loại đồ giặt
     */
    public function index(Request $request)
    {
        $query = Garment::query();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('name', 'like', "%{$search}%")
                  ->orWhere('category', 'like', "%{$search}%")
                  ->orWhere('condition_note', 'like', "%{$search}%");
        }

        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        $garments = $query->latest()->get();

        return view('admin.garments.index', compact('garments'));
    }

    /**
     * Form tạo mới loại đồ giặt
     */
    public function create()
    {
        return view('admin.garments.create');
    }

    /**
     * Lưu loại đồ giặt mới
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'condition_note' => ['nullable', 'string'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        Garment::create($data);

        return redirect()->route('garments.index')->with('success', 'Loại đồ giặt đã được thêm thành công.');
    }

    /**
     * Chi tiết loại đồ giặt
     */
    public function show($id)
    {
        $garment = Garment::findOrFail($id);
        return view('admin.garments.show', compact('garment'));
    }

    /**
     * Form chỉnh sửa
     */
    public function edit($id)
    {
        $garment = Garment::findOrFail($id);
        return view('admin.garments.edit', compact('garment'));
    }

    /**
     * Cập nhật loại đồ giặt
     */
    public function update(Request $request, $id)
    {
        $garment = Garment::findOrFail($id);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'condition_note' => ['nullable', 'string'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        $garment->update($data);

        return redirect()->route('garments.index')->with('success', 'Loại đồ giặt đã được cập nhật.');
    }

    /**
     * Xóa loại đồ giặt
     */
    public function destroy($id)
    {
        Garment::findOrFail($id)->delete();
        return redirect()->route('garments.index')->with('success', 'Loại đồ giặt đã được xóa.');
    }
}
