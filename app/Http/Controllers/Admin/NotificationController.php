<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index()
    {
        return view('admin.notifications.index', ['notifications' => Notification::latest()->get()]);
    }

    public function create()
    {
        return view('admin.notifications.create');
    }

    public function store(Request $request)
    {
        Notification::create($request->validate(['title' => 'required|string|max:255', 'message' => 'required|string']));
        return redirect()->route('notifications.index')->with('success', 'Thông báo đã được tạo thành công.');
    }

    public function show($id)
    {
        return view('admin.notifications.show', ['notification' => Notification::findOrFail($id)]);
    }

    public function edit($id)
    {
        return view('admin.notifications.edit', ['notification' => Notification::findOrFail($id)]);
    }

    public function update(Request $request, $id)
    {
        Notification::findOrFail($id)->update($request->validate(['title' => 'required|string|max:255', 'message' => 'required|string']));
        return redirect()->route('notifications.index')->with('success', 'Thông báo đã được cập nhật.');
    }

    public function destroy($id)
    {
        Notification::findOrFail($id)->delete();
        return redirect()->route('notifications.index')->with('success', 'Thông báo đã được xóa.');
    }
}
