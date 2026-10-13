<?php

use App\Http\Controllers\Api\V1\BeePayController;
use App\Support\BankTransferReference;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

function pgBeePay(int $orderId, string $reference): Response
{
    Auth::forgetGuards(); // Real signed integrations do not have a Laravel user session.
    config(['services.beepay.webhook_secret' => 'local-beepay-fixture', 'services.beepay.audit_account_id' => 2,
        'services.vietqr.account_number' => '123456789']);
    $body = json_encode(['order_id' => BankTransferReference::forOrder($orderId), 'transaction_type' => 'credit',
        'transaction_ref' => $reference, 'amount' => 10000, 'account_number' => '123456789'], JSON_THROW_ON_ERROR);
    $request = Request::create('/api/v1/webhooks/beepay', 'POST', server: ['CONTENT_TYPE' => 'application/json'], content: $body);
    $request->headers->set('X-Webhook-Signature', hash_hmac('sha256', $body, 'local-beepay-fixture'));

    return app(BeePayController::class)->webhook($request);
}
