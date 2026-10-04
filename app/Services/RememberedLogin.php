<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;

class RememberedLogin
{
    public const COOKIE_NAME = 'remember_web_auth';

    private const CACHE_PREFIX = 'auth:remember:';

    private const USER_CACHE_PREFIX = 'auth:remember-user:';

    private const LIFETIME_MINUTES = 43200;

    public function issue(User $user): string
    {
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $cache = Cache::store('file');
        $userKey = $this->userCacheKey($user);
        $previousTokenHash = $cache->get($userKey);

        if (is_string($previousTokenHash)) {
            $cache->forget(self::CACHE_PREFIX.$previousTokenHash);
        }

        $cache->put(
            self::CACHE_PREFIX.$tokenHash,
            $user->getKey(),
            now()->addMinutes(self::LIFETIME_MINUTES),
        );
        $cache->put(
            $userKey,
            $tokenHash,
            now()->addMinutes(self::LIFETIME_MINUTES),
        );

        return $token;
    }

    public function resolve(string $token): ?User
    {
        $tokenHash = hash('sha256', $token);
        $cache = Cache::store('file');
        $userId = $cache->get(self::CACHE_PREFIX.$tokenHash);

        if (! $userId) {
            return null;
        }

        $user = User::query()->find($userId);

        if (
            ! $user
            || $cache->get($this->userCacheKey($user)) !== $tokenHash
            || $user->TrangThai !== 'Hoạt động'
            || $user->isCustomer()
        ) {
            $this->revoke($token);

            return null;
        }

        return $user;
    }

    public function revoke(?string $token): void
    {
        if ($token !== null && $token !== '') {
            $cache = Cache::store('file');
            $tokenHash = hash('sha256', $token);
            $userId = $cache->get(self::CACHE_PREFIX.$tokenHash);
            $cache->forget(self::CACHE_PREFIX.$tokenHash);

            if ($userId) {
                $userKey = self::USER_CACHE_PREFIX.$userId;

                if ($cache->get($userKey) === $tokenHash) {
                    $cache->forget($userKey);
                }
            }
        }
    }

    public function revokeForUser(User $user): void
    {
        $cache = Cache::store('file');
        $userKey = $this->userCacheKey($user);
        $tokenHash = $cache->pull($userKey);

        if (is_string($tokenHash)) {
            $cache->forget(self::CACHE_PREFIX.$tokenHash);
        }
    }

    public function queueCookie(Request $request, ?string $token): void
    {
        Cookie::queue(Cookie::make(
            self::COOKIE_NAME,
            $token ?? '',
            $token === null ? -1 : self::LIFETIME_MINUTES,
            '/',
            config('session.domain'),
            config('session.secure') ?? $request->isSecure(),
            true,
            false,
            'lax',
        ));
    }

    private function userCacheKey(User $user): string
    {
        return self::USER_CACHE_PREFIX.$user->getKey();
    }
}
