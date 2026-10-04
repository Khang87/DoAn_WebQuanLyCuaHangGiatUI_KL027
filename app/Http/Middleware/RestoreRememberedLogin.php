<?php

namespace App\Http\Middleware;

use App\Services\RememberedLogin;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RestoreRememberedLogin
{
    public function __construct(
        private RememberedLogin $rememberedLogin,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->cookie(RememberedLogin::COOKIE_NAME);

        if (Auth::guest() && is_string($token) && $token !== '') {
            $user = $this->rememberedLogin->resolve($token);

            if ($user) {
                Auth::login($user);
                $request->session()->regenerate();
            } else {
                $this->rememberedLogin->revoke($token);
                $this->rememberedLogin->queueCookie($request, null);
            }
        }

        return $next($request);
    }
}
