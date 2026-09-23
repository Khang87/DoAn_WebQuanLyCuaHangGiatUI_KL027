<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function index()
    {
        return view('admin.services.index', ['services' => Service::latest()->get()]);
    }

    public function create()
    {
        return view('admin.services.create');
    }

    public function store(Request $request)
    {
        Service::create($request->validate(['name' => 'required|string|max:255', 'type' => 'nullable|string|max:50', 'price' => 'required|numeric|min:0', 'unit' => 'nullable|string|max:20', 'status' => 'required|string', 'description' => 'nullable|string']));
        return redirect()->route('services.index')->with('success', 'Dịch vụ đã được tạo thành công.');
    }

    public function show($id)
    {
        return view('admin.services.show', ['service' => Service::findOrFail($id)]);
    }

    public function edit($id)
    {
        return view('admin.services.edit', ['service' => Service::findOrFail($id)]);
    }

    public function update(Request $request, $id)
    {
        Service::findOrFail($id)->update($request->validate(['name' => 'required|string|max:255', 'type' => 'nullable|string|max:50', 'price' => 'required|numeric|min:0', 'unit' => 'nullable|string|max:20', 'status' => 'required|string', 'description' => 'nullable|string']));
        return redirect()->route('services.index')->with('success', 'Dịch vụ đã được cập nhật.');
    }

    public function destroy($id)
    {
        Service::findOrFail($id)->delete();
        return redirect()->route('services.index')->with('success', 'Dịch vụ đã được xóa.');
    }
}
