<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\PasswordResetOtpException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PasswordResetOtpService;
use App\Services\RememberedLogin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    private const OTP_PREFIX = 'pwd_reset_';

    public function showLinkRequestForm(): View
    {
        return view('auth.passwords.email');
    }

    public function sendResetOtp(Request $request, PasswordResetOtpService $otpService): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:150'],
        ]);
        $email = mb_strtolower(trim($validated['email']));
        try {
            $otpService->send($email);
        } catch (PasswordResetOtpException $exception) {
            if ($exception->throttled) {
                return redirect()->route('password.reset')->with('reset_email', $email)->with('error', $exception->getMessage());
            }

            return redirect()->route('password.request')->withInput(['email' => $email])->with('error', $exception->getMessage());
        }

        return redirect()->route('password.reset')
            ->with('reset_email', $email)
            ->with('status', 'Nếu email đã đăng ký, mã OTP sẽ được gửi đến hộp thư và có hiệu lực trong 15 phút.');
    }

    public function showResetForm(Request $request): View
    {
        return view('auth.passwords.reset', [
            'email' => (string) $request->session()->get('reset_email', $request->old('email', '')),
        ]);
    }

    public function resetPassword(Request $request, RememberedLogin $rememberedLogin): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:150'],
            'otp' => ['required', 'digits:6'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
        $email = mb_strtolower(trim($validated['email']));
        $user = $this->findUserByEmail($email);
        $cache = Cache::store(config('cache.auth_store'));
        $storedOtp = $cache->get($this->otpKey($email));

        if (
            ! $user
            || ! is_string($storedOtp)
            || ! hash_equals($storedOtp, hash('sha256', $validated['otp']))
        ) {
            return redirect()->route('password.reset')
                ->withInput(['email' => $email])
                ->with('error', 'Email hoặc mã OTP không đúng hoặc mã đã hết hạn.');
        }

        $user->forceFill([
            'MatKhau' => Hash::make($validated['password']),
        ])->save();

        $cache->forget($this->otpKey($email));
        $rememberedLogin->revokeForUser($user);

        return redirect()->route('login')
            ->with('success', 'Mật khẩu đã được cập nhật. Vui lòng đăng nhập.');
    }

    private function findUserByEmail(string $email): ?User
    {
        return User::query()
            ->whereRaw('LOWER("Email") = ?', [mb_strtolower(trim($email))])
            ->first();
    }

    private function otpKey(string $email): string
    {
        return self::OTP_PREFIX.mb_strtolower(trim($email));
    }
}
