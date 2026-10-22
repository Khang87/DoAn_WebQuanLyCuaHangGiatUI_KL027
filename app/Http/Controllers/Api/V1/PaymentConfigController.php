<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class PaymentConfigController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $bankBin = trim((string) config('services.vietqr.bank_bin'));
        $accountNumber = trim((string) config('services.vietqr.account_number'));
        $accountName = trim((string) config('services.vietqr.account_name'));

        return response()->json([
            'enabled' => $bankBin === '970422' && ctype_digit($accountNumber) && $accountName !== '',
            'bank_bin' => $bankBin,
            'bank_name' => config('services.vietqr.bank_name'),
            'account_number' => $accountNumber,
            'account_name' => $accountName,
            'payment_prefix' => strtoupper((string) config('services.payments.payment_prefix', 'DH')),
        ]);
    }
}
