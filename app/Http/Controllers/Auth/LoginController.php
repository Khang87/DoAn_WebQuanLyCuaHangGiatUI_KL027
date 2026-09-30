<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    /**
     * Hiển thị form đăng nhập
     */
    public function showLoginForm()
    {
        return view('auth.login');
    }

    /**
     * Xử lý đăng nhập
     */
    public function login(Request $request)
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
        ], $request->boolean('remember'));

        if ($attempt) {
            $request->session()->regenerate();

            $user = Auth::user();

            // Kiểm tra role khách hàng - từ chối đăng nhập trên Web
            if ($user->isCustomer()) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return back()->withErrors([
                    'email' => 'Tài khoản Khách hàng chỉ hỗ trợ đăng nhập trên ứng dụng Mobile.',
                ])->onlyInput('email');
            }

            // Phân quyền redirect sau đăng nhập
            if ($user->isManager()) {
                // Quản lý (admin/manager) -> Dashboard quản lý
                return redirect()->route('dashboard');
            }

            if ($user->isStaff()) {
                // Nhân viên (staff/employee) -> Dashboard nhân viên
                return redirect()->route('dashboard');
            }

            // Mặc định redirect về dashboard
            return redirect()->route('dashboard');
        }

        return back()->withErrors([
            'email' => 'Email hoặc mật khẩu không đúng.',
        ])->onlyInput('email');
    }

    /**
     * Xử lý đăng xuất
     */
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
