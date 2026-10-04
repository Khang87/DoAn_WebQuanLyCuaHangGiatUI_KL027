<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\RememberedLogin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    /**
     * Hiển thị form đăng nhập
     */
    public function showLoginForm(): View
    {
        return view('auth.login');
    }

    /**
     * Xử lý đăng nhập
     */
    public function login(Request $request, RememberedLogin $rememberedLogin): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        // Bảng xác thực là `TaiKhoan` với cột `Email` (hoa chữ đầu). Provider
        // Eloquent where thẳng key của mảng credentials thành tên cột, nên phải
        // truyền đúng key `Email`, nếu không sẽ sinh SQL `where "email" = ?`.
        $attempt = Auth::attempt([
            'Email' => $credentials['email'],
            'password' => $credentials['password'],
            'TrangThai' => 'Hoạt động',
        ], false);

        if ($attempt) {
            $request->session()->regenerate();

            $user = Auth::user();

            // Kiểm tra role khách hàng - từ chối đăng nhập trên Web
            if ($user->isCustomer()) {
                $rememberedLogin->revoke($request->cookie(RememberedLogin::COOKIE_NAME));
                $rememberedLogin->queueCookie($request, null);
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return back()
                    ->with('error', 'Tài khoản Khách hàng chỉ hỗ trợ đăng nhập trên ứng dụng Mobile.')
                    ->onlyInput('email');
            }

            $rememberedLogin->revoke($request->cookie(RememberedLogin::COOKIE_NAME));
            $rememberedLogin->queueCookie(
                $request,
                $request->boolean('remember') ? $rememberedLogin->issue($user) : null,
            );

            if ($user->canPermission('reports.view') && $user->canPermission('dashboard.view')) {
                return redirect()->route('admin.dashboard')->with('success', 'Đăng nhập thành công.');
            }

            if ($user->canPermission('dashboard.view') && $user->canPermission('orders.view')) {
                return redirect()->route('staff.dashboard')->with('success', 'Đăng nhập thành công.');
            }

            if ($user->canPermission('dashboard.view')) {
                return redirect()->route('admin.dashboard')->with('success', 'Đăng nhập thành công.');
            }

            if ($user->canPermission('orders.view')) {
                return redirect()->route('orders.index')->with('success', 'Đăng nhập thành công.');
            }

            if ($user->canPermission('customers.view')) {
                return redirect()->route('customers.index')->with('success', 'Đăng nhập thành công.');
            }

            if ($user->canPermission('reports.view')) {
                return redirect()->route('reports.index')->with('success', 'Đăng nhập thành công.');
            }

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()
                ->with('error', 'Tài khoản chưa được cấp quyền truy cập chức năng nào.')
                ->onlyInput('email');
        }

        return back()
            ->with('error', 'Email hoặc mật khẩu không đúng.')
            ->onlyInput('email');
    }

    /**
     * Xử lý đăng xuất
     */
    public function logout(Request $request, RememberedLogin $rememberedLogin): RedirectResponse
    {
        $rememberedLogin->revoke($request->cookie(RememberedLogin::COOKIE_NAME));
        $rememberedLogin->queueCookie($request, null);
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'Đăng xuất thành công.');
    }
}
