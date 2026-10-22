<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateStaffApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        if (! $token || strlen($token) !== 64) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $stored = DB::table('StaffApiToken')
            ->where('TokenHash', hash('sha256', $token))
            ->where('NgayHetHan', '>', now())
            ->first();
        $user = $stored ? User::query()->with('nhanVien')->find($stored->TaiKhoanID) : null;
        if (! $user || ! $user->isActive() || ! $user->isEmployee()
            || ! $user->nhanVien || $user->nhanVien->TrangThai !== 'Hoạt động') {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        Auth::setUser($user);
        $request->attributes->set('staff_api_token_hash', hash('sha256', $token));

        return $next($request);
    }
}
