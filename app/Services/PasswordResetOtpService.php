<?php

namespace App\Services;

use App\Exceptions\PasswordResetOtpException;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Throwable;

class PasswordResetOtpService
{
    private const OTP_PREFIX = 'pwd_reset_';

    private const THROTTLE_PREFIX = 'auth:password-reset-throttle:';

    private const IP_THROTTLE_PREFIX = 'auth:password-reset-ip-throttle:';

    private const OTP_LIFETIME_MINUTES = 15;

    public function __construct(private ResendOtpMailer $otpMailer) {}

    public function cooldownSeconds(string $email): int
    {
        $deadline = Cache::store(config('cache.auth_store'))->get($this->throttleKey($email), 0);

        return max(0, min(60, (int) $deadline - now()->timestamp));
    }

    public function send(string $email, string $ipAddress): void
    {
        $email = mb_strtolower(trim($email));
        $cache = Cache::store(config('cache.auth_store'));
        $key = $this->otpKey($email);
        $cache->lock($key.':lock', 90)->block(5, function () use ($cache, $key, $email, $ipAddress): void {
            if (! $cache->add($this->ipThrottleKey($ipAddress), now()->addMinute()->timestamp, 60)) {
                throw new PasswordResetOtpException('Vui lòng chờ một phút trước khi yêu cầu mã khôi phục tiếp theo.', throttled: true);
            }

            $users = User::query()->whereRaw('LOWER("Email") = ?', [$email])->limit(2)->get();
            if ($users->isEmpty()) {
                throw new PasswordResetOtpException('Email này không có trong hệ thống.');
            }
            if ($users->count() !== 1) {
                throw new PasswordResetOtpException('Email này thuộc nhiều tài khoản. Vui lòng liên hệ quản trị viên.');
            }
            if (! $cache->add($this->throttleKey($email), now()->addMinute()->timestamp, 60)) {
                throw new PasswordResetOtpException('Vui lòng chờ một phút trước khi yêu cầu mã OTP mới.', throttled: true);
            }
            $user = $users->first();

            if ($user->TrangThai !== 'Hoạt động') {
                $status = mb_strtolower((string) $user->TrangThai);
                $message = str_contains($status, 'xóa') || str_contains($status, 'xoa')
                    ? 'Tài khoản đang chờ xử lý xóa nên không thể khôi phục mật khẩu.'
                    : 'Tài khoản đang bị khóa hoặc ngừng hoạt động nên không thể khôi phục mật khẩu.';

                throw new PasswordResetOtpException($message);
            }

            $otp = (string) random_int(100000, 999999);
            $generation = bin2hex(random_bytes(32));
            try {
                if (! $cache->put($key, [
                    'account_id' => (int) $user->getKey(),
                    'hash' => Hash::make($otp),
                    'attempts' => 0,
                    'generation' => $generation,
                    'expires_at' => now()->addMinutes(self::OTP_LIFETIME_MINUTES)->timestamp,
                ], 900) || ! $cache->put($key.':generation', $generation, 1800)) {
                    throw new \RuntimeException('OTP cache write failed.');
                }
                $this->otpMailer->send($email, $otp);
            } catch (Throwable $exception) {
                $cache->forget($key);
                $cache->forget($key.':generation');
                $cache->forget($this->throttleKey($email));
                $context = [
                    'email_hash' => hash('sha256', $email),
                    'exception' => $exception::class,
                    'provider_message' => $this->sanitizeProviderMessage($exception->getMessage(), $email, $otp),
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
        });
    }

    /** Consume the OTP and return a one-use authorization kept only in the session. */
    public function verify(string $email, string $otp, string $sessionId): ?string
    {
        $email = mb_strtolower(trim($email));
        $cache = Cache::store(config('cache.auth_store'));
        $key = $this->otpKey($email);

        return $cache->lock($key.':lock', 90)->block(5, function () use ($cache, $key, $email, $otp, $sessionId): ?string {
            $record = $cache->get($key);
            if (! is_array($record) || $record['expires_at'] <= now()->timestamp || $record['attempts'] >= 5) {
                $cache->forget($key);

                return null;
            }
            $users = User::query()->whereRaw('LOWER("Email") = ?', [$email])->get();
            $user = $users->count() === 1 ? $users->first() : null;
            if (! $user || $user->TrangThai !== 'Hoạt động'
                || (int) $user->getKey() !== $record['account_id'] || ! Hash::check($otp, $record['hash'])) {
                $record['attempts']++;
                if ($record['attempts'] >= 5) {
                    $cache->forget($key);
                } else {
                    $cache->put($key, $record, $record['expires_at'] - now()->timestamp);
                }

                return null;
            }
            $cache->forget($key);
            $token = bin2hex(random_bytes(32));
            if (! $cache->put($this->grantKey($token), [
                'account_id' => (int) $user->getKey(),
                'email' => $email,
                'password_digest' => hash('sha256', (string) $user->MatKhau),
                'session_digest' => hash('sha256', $sessionId),
                'generation' => $record['generation'],
                'expires_at' => now()->addMinutes(15)->timestamp,
            ], 900)) {
                return null;
            }

            return $token;
        });
    }

    public function canReset(?string $token, string $sessionId): bool
    {
        return $this->validGrant($token, $sessionId) !== null;
    }

    public function passwordMatchesCurrent(?string $token, string $password, string $sessionId): bool
    {
        $grant = $this->validGrant($token, $sessionId);
        if ($grant === null) {
            return false;
        }
        $user = User::query()->find($grant['account_id']);

        return is_string($user?->MatKhau) && Hash::check($password, $user->MatKhau);
    }

    public function reset(?string $token, string $password, string $sessionId): bool
    {
        $grant = $this->validGrant($token, $sessionId);
        if ($grant === null) {
            return false;
        }
        $cache = Cache::store(config('cache.auth_store'));

        return $cache->lock($this->otpKey($grant['email']).':lock', 90)->block(5, function () use ($cache, $token, $password, $sessionId): bool {
            return DB::transaction(function () use ($cache, $token, $password, $sessionId): bool {
                $grant = $this->validGrant($token, $sessionId);
                if ($grant === null) {
                    return false;
                }
                $user = User::query()->lockForUpdate()->find($grant['account_id']);
                if (! $user || mb_strtolower(trim($user->Email ?? '')) !== $grant['email']
                    || $user->TrangThai !== 'Hoạt động'
                    || (is_string($user->MatKhau) && Hash::check($password, $user->MatKhau))
                    || ! hash_equals($grant['password_digest'], hash('sha256', (string) $user->MatKhau))) {
                    return false;
                }
                // Consume before writing: even a failed write must never permit replay.
                $cache->forget($this->grantKey($token));
                $user->forceFill(['MatKhau' => Hash::make($password)])->save();
                app(RememberedLogin::class)->revokeForUser($user);

                return true;
            });
        });
    }

    private function validGrant(?string $token, string $sessionId): ?array
    {
        if ($token === null || preg_match('/^[a-f0-9]{64}$/D', $token) !== 1) {
            return null;
        }
        $cache = Cache::store(config('cache.auth_store'));
        $grant = $cache->get($this->grantKey($token));
        if (! is_array($grant) || ! hash_equals($grant['session_digest'], hash('sha256', $sessionId))
            || $grant['expires_at'] <= now()->timestamp
            || $cache->get($this->otpKey($grant['email']).':generation') !== $grant['generation']) {
            return null;
        }
        $user = User::query()->find($grant['account_id']);
        if (! $user || mb_strtolower(trim($user->Email ?? '')) !== $grant['email']
            || $user->TrangThai !== 'Hoạt động'
            || ! hash_equals($grant['password_digest'], hash('sha256', (string) $user->MatKhau))) {
            return null;
        }

        return $grant;
    }

    private function grantKey(string $token): string
    {
        return 'auth:password-reset-grant:'.hash('sha256', $token);
    }

    private function throttleKey(string $email): string
    {
        return self::THROTTLE_PREFIX.hash('sha256', mb_strtolower(trim($email)));
    }

    private function ipThrottleKey(string $ipAddress): string
    {
        return self::IP_THROTTLE_PREFIX.hash('sha256', trim($ipAddress));
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
