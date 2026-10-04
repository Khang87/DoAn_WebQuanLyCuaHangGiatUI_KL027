<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\RememberedLogin;
use App\Services\ResendOtpMailer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class PasswordResetController extends Controller
{
    private const OTP_PREFIX = 'pwd_reset_';

    private const THROTTLE_PREFIX = 'auth:password-reset-throttle:';

    private const OTP_LIFETIME_MINUTES = 15;

    public function showLinkRequestForm(): View
    {
        return view('auth.passwords.email');
    }

    public function sendResetOtp(Request $request, ResendOtpMailer $otpMailer): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:150'],
        ]);
        $email = mb_strtolower(trim($validated['email']));
        $cache = Cache::store(config('cache.auth_store'));
        $throttleKey = self::THROTTLE_PREFIX.hash('sha256', $email);

        if (! $cache->add($throttleKey, true, now()->addMinute())) {
            return redirect()->route('password.reset')
                ->with('reset_email', $email)
                ->with('error', 'Vui lòng chờ một phút trước khi yêu cầu mã OTP mới.');
        }

        $user = $this->findUserByEmail($email);
        if (! $user) {
            return redirect()->route('password.reset')
                ->with('reset_email', $email)
                ->with('status', 'Nếu email đã đăng ký, mã xác minh sẽ được tạo.');
        }

        $otp = (string) random_int(100000, 999999);
        $cache->put(
            $this->otpKey($email),
            hash('sha256', $otp),
            now()->addMinutes(self::OTP_LIFETIME_MINUTES),
        );

        try {
            $otpMailer->send($email, $otp);
        } catch (Throwable $exception) {
            $cache->forget($this->otpKey($email));
            $cache->forget($throttleKey);
            $context = [
                'email_hash' => hash('sha256', $email),
                'exception' => $exception::class,
                'provider_message' => $this->sanitizeProviderMessage(
                    $exception->getMessage(),
                    $email,
                    $otp,
                ),
            ];

            if (method_exists($exception, 'getErrorType')) {
                $context['provider_error_type'] = $exception->getErrorType();
            }
            if (method_exists($exception, 'getErrorCode')) {
                $context['provider_error_code'] = $exception->getErrorCode();
            }

            Log::error('Unable to send password reset OTP through Resend.', $context);

            return redirect()->route('password.request')
                ->withInput(['email' => $email])
                ->with('error', 'Không thể gửi email mã OTP lúc này. Vui lòng thử lại sau.');
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

    private function sanitizeProviderMessage(string $message, string $email, string $otp): string
    {
        $message = str_replace(
            array_filter([
                $email,
                $otp,
                (string) config('services.resend.key'),
                (string) config('services.resend.from'),
            ]),
            '[redacted]',
            $message,
        );
        $message = preg_replace(
            [
                '/[A-Z0-9._%+\-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i',
                '/\b\d{6}\b/',
                '/\bBearer\s+\S+/i',
                '/\bre_[A-Z0-9_-]{10,}\b/i',
            ],
            [
                '[redacted-email]',
                '[redacted-otp]',
                'Bearer [redacted]',
                '[redacted-key]',
            ],
            $message,
        );

        return $message ?? 'Provider error (message redacted).';
    }
}
