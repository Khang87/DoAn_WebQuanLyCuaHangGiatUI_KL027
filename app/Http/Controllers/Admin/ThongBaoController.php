<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DonHang;
use App\Models\TaiKhoan;
use App\Services\NotificationService;
use App\Support\FriendlyError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ThongBaoController extends Controller
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
            $this->notificationService->markAsRead((int) $requestedId, (int) $request->user()->getKey());
        }

        $notifications = $this->notificationService->getAll([
            'search' => $request->input('search'),
            'user_id' => (int) $request->user()->getKey(),
            'type' => $request->input('type'),
            'order_id' => $request->input('order_id'),
            'read' => $request->input('read'),
        ]);

        $users = collect([$request->user()]);
        $orders = collect();

        return view('admin.notifications.index', compact('notifications', 'users', 'orders'));
    }

    public function updates(Request $request): JsonResponse
    {
        $query = $request->user()->notifications();

        return response()->json([
            'unread_count' => (clone $query)->where('DaDoc', false)->count(),
            'notifications' => $query->orderByDesc('ThoiGianGui')->orderByDesc('ThongBaoID')->limit(5)->get()
                ->map(fn ($notification): array => [
                    'id' => (int) $notification->getKey(),
                    'title' => (string) $notification->TieuDe,
                    'is_read' => (bool) $notification->DaDoc,
                    'url' => route('notifications.show', $notification->getKey()),
                    'time' => $notification->ThoiGianGui?->format('d/m/Y H:i'),
                ])->values(),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function create()
    {
        $users = TaiKhoan::where('TrangThai', 'Hoạt động')->orderBy('TenDangNhap')->get();
        $orders = DonHang::orderBy('NgayTao', 'desc')->get();
        $recipientGroups = NotificationService::RECIPIENT_GROUPS;

        return view('admin.notifications.create', compact('users', 'orders', 'recipientGroups'));
    }

    public function store(Request $request)
    {
        $recipient = (string) $request->input('recipient', $request->input('TaiKhoanID', ''));
        $request->merge(['recipient' => $recipient]);
        $validated = $request->validate([
            'recipient' => [
                'required',
                'string',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (array_key_exists((string) $value, NotificationService::RECIPIENT_GROUPS)) {
                        return;
                    }

                    if (
                        ! ctype_digit((string) $value)
                        || ! TaiKhoan::query()
                            ->where('TaiKhoanID', (int) $value)
                            ->where('TrangThai', 'Hoạt động')
                            ->exists()
                    ) {
                        $fail('Vui lòng chọn nhóm người nhận hoặc tài khoản đang hoạt động.');
                    }
                },
            ],
            'LoaiThongBao' => 'nullable|string|max:50|not_in:internal_password_otp',
            'TieuDe' => 'required|string|max:200',
            'NoiDung' => 'required|string|max:1000',
            'DonHangID' => 'nullable|exists:DonHang,DonHangID',
        ]);

        try {
            unset($validated['recipient']);
            $createdCount = $this->notificationService->createForRecipient($recipient, $validated);

            if ($createdCount === 0) {
                throw ValidationException::withMessages([
                    'recipient' => 'Không tìm thấy tài khoản đang hoạt động trong nhóm đã chọn.',
                ]);
            }

            return redirect()->route('notifications.index')->with('success', $createdCount === 1
                ? 'Thông báo đã được tạo thành công.'
                : "Đã tạo thông báo cho {$createdCount} tài khoản.");
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Exception $e) {
            return redirect()->route('notifications.create')->with('error', FriendlyError::message($e))->withInput();
        }
    }

    public function show(int $id)
    {
        $notification = $this->notificationService->findForDetails($id, (int) auth()->id());

        if (! $notification) {
            abort(404);
        }

        if (! $notification->DaDoc) {
            $this->notificationService->markNotificationAsRead($notification);
        }

        return view('admin.notifications.show', compact('notification'));
    }

    public function edit(int $id)
    {
        $notification = $this->notificationService->find($id);
        // OTP messages cannot be edited, reassigned, or deleted through management routes.
        abort_if($notification?->LoaiThongBao === 'internal_password_otp', 404);

        if (! $notification) {
            abort(404);
        }

        $users = TaiKhoan::where('TrangThai', 'Hoạt động')->orderBy('TenDangNhap')->get();
        $orders = DonHang::orderBy('NgayTao', 'desc')->get();

        return view('admin.notifications.edit', compact('notification', 'users', 'orders'));
    }

    public function update(Request $request, int $id)
    {
        $notification = $this->notificationService->find($id);
        // OTP messages cannot be edited, reassigned, or deleted through management routes.
        abort_if($notification?->LoaiThongBao === 'internal_password_otp', 404);

        if (! $notification) {
            abort(404);
        }

        $validated = $request->validate([
            'TaiKhoanID' => 'required|exists:TaiKhoan,TaiKhoanID',
            'LoaiThongBao' => 'nullable|string|max:50|not_in:internal_password_otp',
            'TieuDe' => 'required|string|max:200',
            'NoiDung' => 'required|string|max:1000',
            'DonHangID' => 'nullable|exists:DonHang,DonHangID',
            'DaDoc' => 'nullable|boolean',
        ]);

        try {
            $this->notificationService->update($notification, $validated);

            return redirect()->route('notifications.index')->with('success', 'Thông báo đã được cập nhật.');
        } catch (\Exception $e) {
            return redirect()->route('notifications.edit', $id)->with('error', FriendlyError::message($e))->withInput();
        }
    }

    public function destroy(int $id)
    {
        $notification = $this->notificationService->find($id);
        // OTP messages cannot be edited, reassigned, or deleted through management routes.
        abort_if($notification?->LoaiThongBao === 'internal_password_otp', 404);

        if (! $notification) {
            abort(404);
        }

        try {
            $this->notificationService->delete($notification);

            return redirect()->route('notifications.index')->with('success', 'Thông báo đã được xóa.');
        } catch (\Exception $e) {
            return redirect()->route('notifications.index')->with('error', FriendlyError::message($e));
        }
    }

    public function markAsRead(int $id)
    {
        $notification = $this->notificationService->markAsRead($id, (int) auth()->id());

        if (! $notification) {
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
