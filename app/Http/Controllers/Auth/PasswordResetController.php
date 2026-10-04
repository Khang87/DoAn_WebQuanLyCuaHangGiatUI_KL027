<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\PasswordResetLinkMail;
use App\Models\User;
use App\Services\RememberedLogin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    private const TOKEN_PREFIX = 'auth:password-reset:';

    private const THROTTLE_PREFIX = 'auth:password-reset-throttle:';

    private const TOKEN_LIFETIME_MINUTES = 60;

    public function showLinkRequestForm(): View
    {
        return view('auth.forgot-password');
    }

    public function sendResetLink(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:150'],
        ]);
        $email = mb_strtolower(trim($validated['email']));
        $cache = Cache::store('file');
        $throttleKey = self::THROTTLE_PREFIX.hash('sha256', $email);

        if ($cache->add($throttleKey, true, now()->addMinute())) {
            $user = $this->findUserByEmail($email);

            if ($user) {
                $token = bin2hex(random_bytes(32));
                $cache->put(
                    $this->tokenKey($user->getKey()),
                    hash('sha256', $token),
                    now()->addMinutes(self::TOKEN_LIFETIME_MINUTES),
                );
                $resetUrl = route('password.reset', [
                    'token' => $token,
                    'email' => $email,
                ]);

                Mail::to($email)->send(new PasswordResetLinkMail(
                    $user->TenDangNhap,
                    $resetUrl,
                ));
            }
        }

        return back()->with(
            'status',
            'Nếu email này đã đăng ký, hướng dẫn đặt lại mật khẩu sẽ được gửi đến hộp thư.',
        );
    }

    public function showResetForm(string $token, Request $request): View|RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:150'],
        ]);
        $user = $this->findUserByEmail($validated['email']);

        if (! $user || ! $this->tokenIsValid($user, $token)) {
            return redirect()->route('password.request')
                ->with('error', 'Liên kết đặt lại mật khẩu không hợp lệ hoặc đã hết hạn.');
        }

        return view('auth.reset-password', [
            'email' => $user->Email,
            'token' => $token,
        ]);
    }

    public function reset(Request $request, string $token, RememberedLogin $rememberedLogin): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:150'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
        $user = $this->findUserByEmail($validated['email']);

        if (! $user || ! $this->tokenIsValid($user, $token)) {
            return redirect()->route('password.request')
                ->with('error', 'Liên kết đặt lại mật khẩu không hợp lệ hoặc đã hết hạn.');
        }

        $user->forceFill(['MatKhau' => Hash::make($validated['password'])])->save();
        Cache::store('file')->forget($this->tokenKey($user->getKey()));
        $rememberedLogin->revokeForUser($user);

        return redirect()->route('login')
            ->with('success', 'Mật khẩu đã được cập nhật. Vui lòng đăng nhập.');
    }

    private function tokenIsValid(User $user, string $token): bool
    {
        $storedHash = Cache::store('file')->get($this->tokenKey($user->getKey()));

        return is_string($storedHash) && hash_equals($storedHash, hash('sha256', $token));
    }

    private function findUserByEmail(string $email): ?User
    {
        return User::query()
            ->whereRaw('LOWER("Email") = ?', [mb_strtolower(trim($email))])
            ->first();
    }

    private function tokenKey(int|string $userId): string
    {
        return self::TOKEN_PREFIX.$userId;
    }
}
