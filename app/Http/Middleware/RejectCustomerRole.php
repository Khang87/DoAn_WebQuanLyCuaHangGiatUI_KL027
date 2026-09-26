<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RejectCustomerRole
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->role === 'customer') {
            abort(403, 'Khách hàng chỉ truy cập ứng dụng di động.');
        }

        return $next($request);
    }
}