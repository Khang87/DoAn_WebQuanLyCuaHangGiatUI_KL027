<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Chặn truy cập theo vai trò.
     *
     * Lưu ý: Laravel tự tách middleware theo dấu phẩy, nên nhiều vai trò
     * phải khai báo bằng dấu "|" (vd: role:manager|admin) thay vì "role:manager,admin".
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $allowedRoles = collect($roles)
            ->flatMap(fn (string $role) => explode('|', $role))
            ->map('trim')
            ->filter()
            ->all();

        $user = $request->user();
        $message = 'Bạn không có quyền truy cập chức năng này.';

        if ($user && in_array($user->role, $allowedRoles, true)) {
            return $next($request);
        }

        if ($this->wantsJson($request)) {
            return response()->json(['success' => false, 'message' => $message], 403);
        }

        // Nhân viên cố truy cập khu vực quản lý -> đưa về dashboard nhân viên kèm thông báo.
        if ($user && $user->isEmployee()) {
            return redirect()
                ->route('staff.dashboard')
                ->with('error', $message);
        }

        return $this->forbiddenPage($message)->setStatusCode(403);
    }

    private function wantsJson(Request $request): bool
    {
        return $request->expectsJson() || $request->is('api/*');
    }

    /**
     * Trang 403 gọn nhưng có thông báo rõ ràng, không phụ thuộc view Blade.
     */
    private function forbiddenPage(string $message): Response
    {
        $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

        $html = <<<HTML
        <!DOCTYPE html>
        <html lang="vi">
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>403 - Không có quyền truy cập</title>
            <style>
                body { margin: 0; font-family: system-ui, -apple-system, "Segoe UI", sans-serif; background: #f5f6f8; color: #1f2937; }
                .wrap { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; }
                .card { max-width: 480px; background: #fff; border-radius: 12px; padding: 40px; text-align: center; box-shadow: 0 10px 30px rgba(15, 23, 42, .08); }
                .code { font-size: 56px; font-weight: 700; color: #dc2626; margin: 0; }
                h1 { font-size: 20px; margin: 8px 0 12px; }
                p { margin: 0 0 24px; color: #4b5563; }
                a { display: inline-block; background: #2563eb; color: #fff; text-decoration: none; padding: 10px 20px; border-radius: 8px; }
            </style>
        </head>
        <body>
            <div class="wrap">
                <div class="card">
                    <p class="code">403</p>
                    <h1>Không có quyền truy cập</h1>
                    <p>{$safeMessage}</p>
                    <a href="/dashboard">Về trang chủ</a>
                </div>
            </div>
        </body>
        </html>
        HTML;

        return response($html, 403, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}
