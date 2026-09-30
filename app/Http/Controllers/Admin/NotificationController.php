<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DonHang;
use App\Models\ThongBao;
use App\Models\TaiKhoan;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(
        private NotificationService $notificationService,
    ) {}

    public function index(Request $request)
    {
        // Khi người dùng click vào một item trong dropdown (URL có ?id=X),
        // đánh dấu thông báo đó là đã đọc trước khi hiển thị danh sách.
        $requestedId = $request->input('id');
        if (is_numeric($requestedId)) {
            $this->notificationService->markAsRead((int) $requestedId);
        }

        $notifications = $this->notificationService->getAll([
            'search' => $request->input('search'),
            'user_id' => $request->input('user_id'),
            'type' => $request->input('type'),
            'order_id' => $request->input('order_id'),
            'read' => $request->input('read'),
        ]);

        $users = TaiKhoan::where('TrangThai', 'Hoạt động')->orderBy('TenDangNhap')->get();
        $orders = DonHang::orderBy('NgayTao', 'desc')->get();

        return view('admin.notifications.index', compact('notifications', 'users', 'orders'));
    }

    public function create()
    {
        $users = TaiKhoan::where('TrangThai', 'Hoạt động')->orderBy('TenDangNhap')->get();
        $orders = DonHang::orderBy('NgayTao', 'desc')->get();

        return view('admin.notifications.create', compact('users', 'orders'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'TaiKhoanID' => 'required|exists:TaiKhoan,TaiKhoanID',
            'LoaiThongBao' => 'nullable|string|max:100',
            'NoiDung' => 'required|string|max:1000',
            'DonHangID' => 'nullable|exists:DonHang,DonHangID',
            'ThoiGianGui' => 'nullable|date',
        ]);

        try {
            if (empty($validated['ThoiGianGui'])) {
                $validated['ThoiGianGui'] = now();
            }
            ThongBao::create($validated);

            return redirect()->route('notifications.index')->with('success', 'Thông báo đã được tạo thành công.');
        } catch (\Exception $e) {
            return redirect()->route('notifications.create')->with('error', \App\Support\FriendlyError::message($e))->withInput();
        }
    }

    public function show(int $id)
    {
        $notification = $this->notificationService->find($id);

        if (!$notification) {
            abort(404);
        }

        // Tự động đánh dấu đã đọc khi người dùng mở chi tiết thông báo
        if (! $notification->DaDoc) {
            $this->notificationService->markAsRead($notification->ThongBaoID);
            $notification = $this->notificationService->find($id);
        }

        return view('admin.notifications.show', compact('notification'));
    }

    public function edit(int $id)
    {
        $notification = $this->notificationService->find($id);

        if (!$notification) {
            abort(404);
        }

        $users = TaiKhoan::where('TrangThai', 'Hoạt động')->orderBy('TenDangNhap')->get();
        $orders = DonHang::orderBy('NgayTao', 'desc')->get();

        return view('admin.notifications.edit', compact('notification', 'users', 'orders'));
    }

    public function update(Request $request, int $id)
    {
        $notification = $this->notificationService->find($id);

        if (!$notification) {
            abort(404);
        }

        $validated = $request->validate([
            'TaiKhoanID' => 'required|exists:TaiKhoan,TaiKhoanID',
            'LoaiThongBao' => 'nullable|string|max:100',
            'NoiDung' => 'required|string|max:1000',
            'DonHangID' => 'nullable|exists:DonHang,DonHangID',
            'ThoiGianGui' => 'nullable|date',
            'DaDoc' => 'nullable|boolean',
        ]);

        try {
            $this->notificationService->update($notification, $validated);

            return redirect()->route('notifications.index')->with('success', 'Thông báo đã được cập nhật.');
        } catch (\Exception $e) {
            return redirect()->route('notifications.edit', $id)->with('error', \App\Support\FriendlyError::message($e))->withInput();
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
            return redirect()->route('notifications.index')->with('error', \App\Support\FriendlyError::message($e));
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

    public function markAllAsRead(Request $request)
    {
        $userId = $request->user()->getKey();
        $count = $this->notificationService->markAllAsRead($userId);

        return back()->with('success', $count > 0
            ? "Đã đánh dấu {$count} thông báo là đã đọc."
            : 'Không có thông báo mới nào cần đánh dấu.');
    }
}