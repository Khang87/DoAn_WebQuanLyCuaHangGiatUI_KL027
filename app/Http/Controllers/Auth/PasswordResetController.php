<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\PasswordResetOtpException;
use App\Http\Controllers\Controller;
use App\Services\PasswordResetOtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    public function showLinkRequestForm(Request $request, PasswordResetOtpService $otpService): View
    {
        if ($request->boolean('change_email')) {
            $request->session()->forget(['reset_email', 'password_reset_grant']);
        }
        $email = (string) $request->session()->get('reset_email', '');

        return view('auth.passwords.email', [
            'email' => $email,
            'cooldownSeconds' => $email !== '' ? $otpService->cooldownSeconds($email) : 0,
        ]);
    }

    public function sendResetOtp(Request $request, PasswordResetOtpService $otpService): RedirectResponse
    {
        $validated = $request->validate(['email' => ['required', 'email', 'max:150']]);
        $email = mb_strtolower(trim($validated['email']));
        $request->session()->forget('password_reset_grant');
        try {
            $otpService->send($email);
        } catch (PasswordResetOtpException $exception) {
            if ($exception->throttled) {
                $request->session()->put('reset_email', $email);
            }

            return redirect()->route('password.request')->withInput(['email' => $email])->with('error', $exception->getMessage());
        }
        $request->session()->put('reset_email', $email);

        return redirect()->route('password.request')
            ->with('status', 'Mã OTP đã được gửi và có hiệu lực trong 15 phút.')
            ->with('otp_privacy_notice', 'Nếu email đã đăng ký, bạn sẽ nhận được mã trong hộp thư.');
    }

    public function verifyOtp(Request $request, PasswordResetOtpService $otpService): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:150'],
            'otp' => ['required', 'digits:6'],
        ]);
        $email = mb_strtolower(trim($validated['email']));
        $request->session()->put('reset_email', $email);
        $request->session()->regenerate();
        $token = $otpService->verify($email, $validated['otp'], $request->session()->getId());
        if ($token === null) {
            return redirect()->route('password.request')->with('error', 'Email hoặc mã OTP không đúng hoặc mã đã hết hạn.');
        }
        $request->session()->put('password_reset_grant', $token);

        return redirect()->route('password.reset');
    }

    public function showResetForm(Request $request, PasswordResetOtpService $otpService): View|RedirectResponse
    {
        if (! $otpService->canReset($request->session()->get('password_reset_grant'), $request->session()->getId())) {
            $request->session()->forget('password_reset_grant');

            return redirect()->route('password.request')->with('error', 'Vui lòng xác thực OTP trước khi đặt mật khẩu mới.');
        }

        return view('auth.passwords.new-password');
    }

    public function resetPassword(Request $request, PasswordResetOtpService $otpService): RedirectResponse
    {
        $token = $request->session()->get('password_reset_grant');
        if (! $otpService->canReset($token, $request->session()->getId())) {
            $request->session()->forget('password_reset_grant');

            return redirect()->route('password.request')->with('error', 'Phiên đặt lại mật khẩu không hợp lệ hoặc đã hết hạn. Vui lòng xác thực OTP lại.');
        }
        $validated = $request->validate(['password' => ['required', 'string', 'min:8', 'confirmed']]);
        if (! $otpService->reset($token, $validated['password'], $request->session()->getId())) {
            $request->session()->forget('password_reset_grant');

            return redirect()->route('password.request')->with('error', 'Phiên đặt lại mật khẩu không hợp lệ hoặc đã hết hạn. Vui lòng xác thực OTP lại.');
        }
        $request->session()->forget(['password_reset_grant', 'reset_email']);

        return redirect()->route('login')->with('success', 'Mật khẩu đã được cập nhật. Vui lòng đăng nhập.');
    }
}
