<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\InternalPasswordOtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class InternalPasswordResetController extends Controller
{
    public function show()
    {
        return view('auth.passwords.reset', [
            'submitRoute' => 'internal-password.update',
            'internalReset' => true,
        ]);
    }

    public function update(Request $request, InternalPasswordOtpService $service)
    {
        $data = $request->validate([
            'otp' => ['required', 'digits:6'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
        try {
            $account = $service->reset($data['otp'], $data['password']);
        } catch (\Throwable $exception) {
            Log::error('Internal OTP reset failed.', ['exception' => $exception::class]);

            return redirect()->route('internal-password.reset')
                ->with('error', 'Không thể xác thực OTP lúc này. Vui lòng thử lại hoặc yêu cầu quản trị viên cấp mã mới.');
        }
        if (! $account) {
            return redirect()->route('internal-password.reset')
                ->with('error', 'OTP không hợp lệ, đã hết hạn hoặc đã vượt quá số lần thử.');
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->with('success', 'Mật khẩu đã được thay đổi thành công. Vui lòng đăng nhập lại với mật khẩu mới.');
    }
}
