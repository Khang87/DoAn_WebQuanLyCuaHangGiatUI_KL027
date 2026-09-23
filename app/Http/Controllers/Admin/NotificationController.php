<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserRequest;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class NotificationController extends Controller
{
    public function index()
    {
        return view('admin.notifications.index', ['notifications' => \App\Models\Notification::latest()->get()]);
    }

    public function create()
    {
        return view('admin.notifications.create');
    }

    public function store(Request $request)
    {
        try {
            \App\Models\Notification::create($request->validate([
                'title' => 'required|string|max:255',
                'message' => 'required|string',
            ]));

            return redirect()->route('notifications.index')->with('success', 'Thông báo đã được tạo thành công.');
        } catch (\Exception $e) {
            return redirect()->route('notifications.create')->with('error', 'Có lỗi xảy ra: ' . $e->getMessage())->withInput();
        }
    }

    public function show($id)
    {
        return view('admin.notifications.show', ['notification' => \App\Models\Notification::findOrFail($id)]);
    }

    public function edit($id)
    {
        return view('admin.notifications.edit', ['notification' => \App\Models\Notification::findOrFail($id)]);
    }

    public function update(Request $request, $id)
    {
        try {
            \App\Models\Notification::findOrFail($id)->update($request->validate([
                'title' => 'required|string|max:255',
                'message' => 'required|string',
            ]));

            return redirect()->route('notifications.index')->with('success', 'Thông báo đã được cập nhật.');
        } catch (\Exception $e) {
            return redirect()->route('notifications.edit', $id)->with('error', 'Có lỗi xảy ra: ' . $e->getMessage())->withInput();
        }
    }

    public function destroy($id)
    {
        try {
            \App\Models\Notification::findOrFail($id)->delete();

            return redirect()->route('notifications.index')->with('success', 'Thông báo đã được xóa.');
        } catch (\Exception $e) {
            return redirect()->route('notifications.index')->with('error', 'Có lỗi xảy ra: ' . $e->getMessage());
        }
    }
}
