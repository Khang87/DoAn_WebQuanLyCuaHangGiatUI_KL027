<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\SupabasePasswordRecoveryException;
use App\Http\Controllers\Api\ApiController;
use App\Models\User;
use App\Services\SupabasePasswordRecoveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PasswordRecoveryController extends ApiController
{
    public function __construct(private SupabasePasswordRecoveryService $passwordRecoveryService) {}

    public function forgot(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:150'],
        ]);
        $email = mb_strtolower(trim($validated['email']));
        $users = User::query()
            ->whereRaw('LOWER("Email") = ?', [$email])
            ->get();

        if ($users->isEmpty()) {
            return $this->errorResponse('Email không tồn tại trong hệ thống, vui lòng nhập lại.', 404);
        }

        if ($users->count() !== 1) {
            return $this->errorResponse('Email được liên kết với nhiều tài khoản, vui lòng liên hệ hỗ trợ.', 409);
        }

        if (! $users->first()->isActive()) {
            return $this->errorResponse('Tài khoản đã bị vô hiệu hóa.', 403);
        }

        try {
            $this->passwordRecoveryService->send($email);
        } catch (SupabasePasswordRecoveryException) {
            return $this->errorResponse('Không thể gửi mã khôi phục lúc này. Vui lòng thử lại sau.', 502);
        }

        return response()->json([
            'success' => true,
            'message' => 'Yêu cầu khôi phục mật khẩu đã được gửi.',
        ]);
    }
}
