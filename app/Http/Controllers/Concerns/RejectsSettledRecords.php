<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

trait RejectsSettledRecords
{
    /**
     * Trả về phản hồi từ chối thao tác lên đơn/hóa đơn đã quyết toán.
     *
     * Web: redirect kèm flash "error". JSON/API: HTTP 409 Conflict.
     */
    protected function rejectSettled(Request $request, string $message, ?string $fallbackUrl = null): Response
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['success' => false, 'message' => $message], 409);
        }

        return redirect()
            ->to($fallbackUrl ?? url()->previous())
            ->with('error', $message);
    }
}
