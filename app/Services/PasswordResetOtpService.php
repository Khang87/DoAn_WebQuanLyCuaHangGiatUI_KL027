<?php

namespace App\Services;

use App\Exceptions\PasswordResetOtpException;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class PasswordResetOtpService
{
    private const OTP_PREFIX = 'pwd_reset_';

    private const THROTTLE_PREFIX = 'auth:password-reset-throttle:';

    private const OTP_LIFETIME_MINUTES = 15;

    public function __construct(private ResendOtpMailer $otpMailer) {}

    public function send(string $email): void
    {
        $email = mb_strtolower(trim($email));
        $cache = Cache::store(config('cache.auth_store'));
        $throttleKey = self::THROTTLE_PREFIX.hash('sha256', $email);

        if (! $cache->add($throttleKey, true, now()->addMinute())) {
            throw new PasswordResetOtpException('Vui lòng chờ một phút trước khi yêu cầu mã OTP mới.', throttled: true);
        }

        $user = User::query()->whereRaw('LOWER("Email") = ?', [$email])->first();
        if (! $user) {
            return;
        }

        $otp = (string) random_int(100000, 999999);
        $cache->put(
            $this->otpKey($email),
            hash('sha256', $otp),
            now()->addMinutes(self::OTP_LIFETIME_MINUTES),
        );

        try {
            $this->otpMailer->send($email, $otp);
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

            throw new PasswordResetOtpException('Không thể gửi email mã OTP lúc này. Vui lòng thử lại sau.');
        }

    }

    private function otpKey(string $email): string
    {
        return self::OTP_PREFIX.$email;
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
