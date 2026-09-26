<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\User;
use App\Models\Order;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(
        private NotificationService $notificationService,
    ) {}

    public function index(Request $request)
    {
        $notifications = $this->notificationService->getAll([
            'search' => $request->input('search'),
            'user_id' => $request->input('user_id'),
            'type' => $request->input('type'),
            'order_id' => $request->input('order_id'),
        ]);

        $users = User::orderBy('name')->get();
        $orders = Order::orderBy('created_at', 'desc')->get();

        return view('admin.notifications.index', compact('notifications', 'users', 'orders'));
    }

    public function create()
    {
        $users = User::orderBy('name')->get();
        $orders = Order::orderBy('created_at', 'desc')->get();

        return view('admin.notifications.create', compact('users', 'orders'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'type' => 'nullable|string|max:100',
            'message' => 'required|string|max:1000',
            'order_id' => 'nullable|exists:orders,id',
            'sent_at' => 'nullable|date',
        ]);

        try {
            if (empty($validated['sent_at'])) {
                $validated['sent_at'] = now();
            }
            Notification::create($validated);

            return redirect()->route('notifications.index')->with('success', 'Thông báo đã được tạo thành công.');
        } catch (\Exception $e) {
            return redirect()->route('notifications.create')->with('error', 'Có lỗi xảy ra: ' . $e->getMessage())->withInput();
        }
    }

    public function show(int $id)
    {
        $notification = $this->notificationService->find($id);

        if (!$notification) {
            abort(404);
        }

        return view('admin.notifications.show', compact('notification'));
    }

    public function edit(int $id)
    {
        $notification = $this->notificationService->find($id);

        if (!$notification) {
            abort(404);
        }

        $users = User::orderBy('name')->get();
        $orders = Order::orderBy('created_at', 'desc')->get();

        return view('admin.notifications.edit', compact('notification', 'users', 'orders'));
    }

    public function update(Request $request, int $id)
    {
        $notification = $this->notificationService->find($id);

        if (!$notification) {
            abort(404);
        }

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'type' => 'nullable|string|max:100',
            'message' => 'required|string|max:1000',
            'order_id' => 'nullable|exists:orders,id',
            'sent_at' => 'nullable|date',
            'read_at' => 'nullable|date',
        ]);

        try {
            $this->notificationService->update($notification, $validated);

            return redirect()->route('notifications.index')->with('success', 'Thông báo đã được cập nhật.');
        } catch (\Exception $e) {
            return redirect()->route('notifications.edit', $id)->with('error', 'Có lỗi xảy ra: ' . $e->getMessage())->withInput();
        }
    }

    public function destroy(int $id)
    {
        $notification = $this->notificationService->find($id);

        if (!$notification) {
            abort(404);
        }

        try {
            $this->notificationService->delete($notification);

            return redirect()->route('notifications.index')->with('success', 'Thông báo đã được xóa.');
        } catch (\Exception $e) {
            return redirect()->route('notifications.index')->with('error', 'Có lỗi xảy ra: ' . $e->getMessage());
        }
    }

    public function markAsRead(int $id)
    {
        $notification = $this->notificationService->markAsRead($id);

        if (!$notification) {
            abort(404);
        }

        return back()->with('success', 'Đã đánh dấu là đã đọc.');
    }
}