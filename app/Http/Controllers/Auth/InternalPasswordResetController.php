<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\InternalPasswordOtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class InternalPasswordResetController extends Controller
{
    public function show(Request $request)
    {
        return view('auth.passwords.reset', [
            'email' => $request->old('email', ''),
            'submitRoute' => 'internal-password.update',
            'internalReset' => true,
        ]);
    }

    public function update(Request $request, InternalPasswordOtpService $service)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:150'],
            'otp' => ['required', 'digits:6'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
        try {
            $success = $service->reset($data['email'], $data['otp'], $data['password']);
        } catch (\Throwable $exception) {
            Log::error('Internal OTP reset failed.', ['exception' => $exception::class]);

            return redirect()->route('internal-password.reset')->withInput(['email' => $data['email']])
                ->with('error', 'Không thể xác thực OTP lúc này. Vui lòng thử lại hoặc yêu cầu quản trị viên cấp mã mới.');
        }
        if (! $success) {
            return redirect()->route('internal-password.reset')->withInput(['email' => $data['email']])
                ->with('error', 'Email hoặc OTP không hợp lệ, đã hết hạn hoặc đã vượt quá số lần thử.');
        }

        if (mb_strtolower(trim($request->user()?->Email ?? '')) === mb_strtolower(trim($data['email']))) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return redirect()->route('login')->with('success', 'Mật khẩu đã được cập nhật. Vui lòng đăng nhập lại.');
    }
}
