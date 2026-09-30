<?php

namespace App\Http\Middleware;

use App\Support\PermissionCache;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Chặn truy cập theo mã quyền động, ví dụ: ->middleware('permission:orders.edit').
 *
 * Khác với `role:` (chỉ so tên vai trò), middleware này tra chuỗi
 * `TaiKhoan_VaiTro` -> `VaiTro_Quyen` -> `Quyen` nên Chủ cửa hàng có thể tuỳ
 * biến ma trận quyền. Danh sách mã quyền được đọc từ {@see PermissionCache}
 * nên mỗi request chỉ query một lần.
 */
class EnsureUserHasPermission
{
    public function handle(Request $request, Closure $next, string ...$codes): Response
    {
        $user = $request->user();

        $message = 'Bạn không có quyền thực hiện thao tác này.';

        // Nhiều mã quyền viết bằng dấu "|" (vd: permission:orders.view|orders.edit)
        // vì Laravel chỉ tách tham số middleware bằng dấu phẩy.
        $codes = collect($codes)
            ->flatMap(fn (string $code) => explode('|', $code))
            ->map('trim')
            ->filter()
            ->all();

        if ($user && $user->isActive()) {
            foreach ($codes as $code) {
                if ($user->canPermission($code)) {
                    return $next($request);
                }
            }
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['success' => false, 'message' => $message], 403);
        }

        abort(403, $message);
    }
}
