<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function index()
    {
        return view('admin.services.index');
    }

    public function create()
    {
        return view('admin.services.create');
    }

    public function store(Request $request)
    {
        return redirect()->route('services.index')->with('success', 'Dịch vụ đã được tạo thành công.');
    }

    public function show($id)
    {
        return view('admin.services.show', compact('id'));
    }

    public function edit($id)
    {
        return view('admin.services.edit', compact('id'));
    }

    public function update(Request $request, $id)
    {
        return redirect()->route('services.index')->with('success', 'Dịch vụ đã được cập nhật.');
    }

    public function destroy($id)
    {
        return redirect()->route('services.index')->with('success', 'Dịch vụ đã được xóa.');
    }
}
