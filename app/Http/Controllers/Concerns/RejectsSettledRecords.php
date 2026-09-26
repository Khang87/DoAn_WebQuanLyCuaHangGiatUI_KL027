<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

trait RejectsSettledRecords
{
    /**
     * Trả về phản hồi từ chối thao tác lên đơn/hóa đơn đã quyết toán.
     *
     * Web: redirect kèm flash "error". JSON/API: HTTP 409 Conflict.
     */
    protected function rejectSettled(Request $request, string $message, ?string $fallbackUrl = null): Response
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['success' => false, 'message' => $message], 409);
        }

        return redirect()
            ->to($fallbackUrl ?? url()->previous())
            ->with('error', $message);
    }

    /**
     * Từ chối thao tác lên bản ghi đã quyết toán với HTTP 403 Forbidden.
     *
     * Dùng cho đơn hàng / hóa đơn: Chủ cửa hàng là vai trò duy nhất được phép
     * sửa / xóa / hoàn tiền các bản ghi đã thanh toán, nên Quản lý và Nhân viên
     * phải bị chặn ở tầng HTTP thay vì chỉ ẩn nút trên giao diện.
     */
    protected function denySettled(Request $request, string $message, ?string $fallbackUrl = null): Response
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['success' => false, 'message' => $message], 403);
        }

        // Ghi lại nơi người dùng đến để họ quay lại được sau khi đọc thông báo.
        if (session()->has('_settled_rejected_url') === false) {
            session()->flash('_settled_rejected_url', $fallbackUrl ?? url()->previous());
        }

        return response()->view('admin.errors.settled-forbidden', [
            'message' => $message,
        ], 403);
    }
}
