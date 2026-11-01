<?php

namespace App\Services;

use App\Exceptions\PasswordResetOtpException;
use App\Models\ThongBao;
use App\Models\User;
use App\Notifications\InternalPasswordResetOtp;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Throwable;

class InternalPasswordOtpService
{
    private function cache(): Repository
    {
        return Cache::store(config('cache.internal_otp_store', 'redis'));
    }

    public function key(string $email): string
    {
        return 'auth:internal-password-otp:'.hash('sha256', mb_strtolower(trim($email)));
    }

    public function send(User $account): void
    {
        if (! $account->NhanVienID || $account->KhachHangID || $account->TrangThai !== 'Hoạt động'
            || ! filter_var($account->Email, FILTER_VALIDATE_EMAIL)) {
            throw new PasswordResetOtpException('Chỉ có thể gửi OTP cho tài khoản nhân viên đang hoạt động có email hợp lệ.');
        }
        $email = mb_strtolower(trim($account->Email));
        if (User::query()->whereRaw('LOWER("Email") = ?', [$email])->count() !== 1) {
            throw new PasswordResetOtpException('Email phải thuộc duy nhất một tài khoản.');
        }
        $cache = $this->cache();
        $key = $this->key($email);
        $cache->lock($key.':lock', 30)->block(5, function () use ($cache, $key, $account): void {
            if (! $cache->add($key.':cooldown', true, 60)) {
                throw new PasswordResetOtpException('Vui lòng chờ một phút trước khi yêu cầu mã OTP mới.', throttled: true);
            }

            $previousRecord = $cache->get($key);
            $lookupKey = null;
            $otp = null;
            for ($attempt = 0; $attempt < 10; $attempt++) {
                $candidate = (string) random_int(100000, 999999);
                $candidateLookupKey = $this->otpLookupKey($candidate);
                if ($cache->add($candidateLookupKey, [
                    'account_id' => (int) $account->getKey(),
                    'email' => $account->Email,
                ], 600)) {
                    $otp = $candidate;
                    $lookupKey = $candidateLookupKey;
                    break;
                }
            }

            if ($otp === null || $lookupKey === null) {
                $cache->forget($key.':cooldown');
                throw new PasswordResetOtpException('Không thể tạo mã OTP duy nhất lúc này.');
            }

            if (! $cache->put($key, [
                'account_id' => (int) $account->getKey(),
                'email' => $account->Email,
                'hash' => Hash::make($otp),
                'attempts' => 0,
                'expires_at' => now()->addMinutes(10)->timestamp,
                'lookup_key' => $lookupKey,
            ], 600)) {
                $cache->forget($lookupKey);
                $cache->forget($key.':cooldown');
                throw new PasswordResetOtpException('Không thể lưu mã OTP lúc này.');
            }
            if (is_array($previousRecord) && isset($previousRecord['lookup_key'])) {
                $this->forgetLookup($cache, $previousRecord['lookup_key'], (int) $account->getKey());
            }

            try {
                DB::transaction(function () use ($account, $otp): void {
                    $this->clearNotifications((int) $account->getKey());
                    $account->notify(new InternalPasswordResetOtp($otp));
                });
            } catch (Throwable $exception) {
                $this->forgetOtp($cache, $key, [
                    'account_id' => (int) $account->getKey(),
                    'lookup_key' => $lookupKey,
                ]);
                $cache->forget($key.':cooldown');
                // Provider messages may contain the OTP or credentials.
                Log::error('Internal OTP delivery failed.', ['account_id' => $account->getKey(), 'exception' => $exception::class]);
                throw new PasswordResetOtpException('Không thể gửi OTP qua cả email và thông báo. Vui lòng thử lại.');
            }
        });
    }

    public function reset(string $otp, string $password): ?User
    {
        $cache = $this->cache();
        $lookupKey = $this->otpLookupKey($otp);
        $lookup = $cache->get($lookupKey);
        if (! is_array($lookup) || ! is_string($lookup['email'] ?? null) || ! is_numeric($lookup['account_id'] ?? null)) {
            return null;
        }

        $email = mb_strtolower(trim($lookup['email']));
        $key = $this->key($email);

        return $cache->lock($key.':lock', 30)->block(5, function () use ($cache, $key, $email, $otp, $password, $lookup, $lookupKey): ?User {
            $record = $cache->get($key);
            if (! is_array($record)
                || ($record['lookup_key'] ?? null) !== $lookupKey
                || (int) ($record['account_id'] ?? 0) !== (int) $lookup['account_id']) {
                $this->forgetLookup($cache, $lookupKey, (int) $lookup['account_id']);

                return null;
            }
            if ($record['expires_at'] <= now()->timestamp || $record['attempts'] >= 5) {
                $this->forgetOtp($cache, $key, $record);
                $this->clearNotifications($record['account_id']);

                return null;
            }
            $account = User::query()->find($record['account_id']);
            if (! $account || User::query()->whereRaw('LOWER("Email") = ?', [$email])->count() !== 1 || ! $account->NhanVienID || $account->KhachHangID
                || $account->TrangThai !== 'Hoạt động' || mb_strtolower(trim($account->Email ?? '')) !== $email
                || ! Hash::check($otp, $record['hash'])) {
                $record['attempts']++;
                if ($record['attempts'] >= 5) {
                    $this->forgetOtp($cache, $key, $record);
                    $this->clearNotifications($record['account_id']);
                } else {
                    $cache->put($key, $record, max(1, $record['expires_at'] - now()->timestamp));
                }

                return null;
            }
            // Consume first: a crash must never allow replay of a valid OTP.
            $this->forgetOtp($cache, $key, $record);
            DB::transaction(function () use ($account, $password): void {
                $account->forceFill(['MatKhau' => Hash::make($password)])->save();
                $this->clearNotifications((int) $account->getKey());
            });
            app(RememberedLogin::class)->revokeForUser($account);

            return $account;
        });
    }

    private function otpLookupKey(string $otp): string
    {
        return 'auth:internal-password-otp-lookup:'.hash_hmac('sha256', $otp, (string) config('app.key'));
    }

    private function forgetOtp(Repository $cache, string $key, array $record): void
    {
        $cache->forget($key);
        if (isset($record['lookup_key'])) {
            $this->forgetLookup($cache, $record['lookup_key'], (int) ($record['account_id'] ?? 0));
        }
    }

    private function forgetLookup(Repository $cache, string $lookupKey, int $accountId): void
    {
        $lookup = $cache->get($lookupKey);
        if (is_array($lookup) && (int) ($lookup['account_id'] ?? 0) === $accountId) {
            $cache->forget($lookupKey);
        }
    }

    private function clearNotifications(int $accountId): void
    {
        ThongBao::withoutGlobalScopes()->where('TaiKhoanID', $accountId)
            ->where('LoaiThongBao', 'internal_password_otp')->delete();
    }
}
