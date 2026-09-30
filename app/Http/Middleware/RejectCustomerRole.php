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

        if ($user && ! $user->isActive()) {
            abort(403, 'Tài khoản không hoạt động.');
        }

        if ($user && $user->isCustomer()) {
            abort(403, 'Khách hàng chỉ truy cập ứng dụng di động.');
        }

        return $next($request);
    }
}
