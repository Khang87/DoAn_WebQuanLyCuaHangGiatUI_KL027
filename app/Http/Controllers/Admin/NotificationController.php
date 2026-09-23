<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index()
    {
        return view('admin.notifications.index');
    }

    public function create()
    {
        return view('admin.notifications.create');
    }

    public function store(Request $request)
    {
        return redirect()->route('notifications.index')->with('success', 'Thông báo đã được tạo thành công.');
    }

    public function show($id)
    {
        return view('admin.notifications.show', compact('id'));
    }

    public function edit($id)
    {
        return view('admin.notifications.edit', compact('id'));
    }

    public function update(Request $request, $id)
    {
        return redirect()->route('notifications.index')->with('success', 'Thông báo đã được cập nhật.');
    }

    public function destroy($id)
    {
        return redirect()->route('notifications.index')->with('success', 'Thông báo đã được xóa.');
    }
}
