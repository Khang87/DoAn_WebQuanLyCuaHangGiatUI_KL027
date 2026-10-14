<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\DonHang;
use App\Models\HoaDon;
use App\Models\ThanhToan;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class BeePayController extends Controller
{
    public function __construct(private PaymentService $paymentService) {}

    public function config(): JsonResponse
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
            'payment_prefix' => strtoupper((string) config('services.beepay.payment_prefix', 'DH')),
        ]);
    }

    public function webhook(Request $request): Response
    {
        // BeePay generates a hex secret; trimming protects against accidental
        // whitespace when the value is copied into a deployment environment.
        $secret = trim((string) config('services.beepay.webhook_secret'));
        $configuredAccount = preg_replace('/\D+/', '', (string) config('services.vietqr.account_number'));
        if ($secret === '' || $configuredAccount === '') {
            return response()->json(['ok' => false, 'error' => 'Webhook is not configured.'], 503);
        }

        $rawBody = $request->getContent();
        $webhookSignature = (string) $request->header('X-Webhook-Signature');
        $beePaySignature = (string) $request->header('X-BeePay-Signature');
        $signature = $webhookSignature ?: $beePaySignature;
        $providedHash = preg_replace('/^sha256=/i', '', trim($signature));
        $providedHash = strtolower((string) $providedHash);
        $expectedHash = hash_hmac('sha256', $rawBody, $secret);

        if ($providedHash === '' || ! hash_equals($expectedHash, $providedHash)) {
            $diagnosticPayload = json_decode($rawBody, true);
            $payloadKeys = is_array($diagnosticPayload) ? array_keys($diagnosticPayload) : [];
            sort($payloadKeys);

            Log::warning('BeePay webhook signature rejected', [
                'webhook_signature_header_present' => $webhookSignature !== '',
                'beepay_signature_header_present' => $beePaySignature !== '',
                'signature_has_sha256_prefix' => str_starts_with(strtolower(trim($signature)), 'sha256='),
                'signature_hex_length' => strlen($providedHash),
                'signature_is_hex' => $providedHash !== '' && ctype_xdigit($providedHash),
                'secret_bytes' => strlen($secret),
                'raw_body_sha256' => hash('sha256', $rawBody),
                'raw_body_bytes' => strlen($rawBody),
                'content_type' => $request->header('Content-Type'),
                'payload_event' => is_array($diagnosticPayload) && is_string($diagnosticPayload['event'] ?? null)
                    ? substr($diagnosticPayload['event'], 0, 40)
                    : null,
                'payload_keys' => $payloadKeys,
            ]);

            return response()->json(['ok' => false, 'error' => 'Invalid signature.'], 401);
        }

        $payload = json_decode($rawBody, true);
        if (! is_array($payload)) {
            return response()->json(['ok' => false, 'error' => 'Invalid JSON.'], 400);
        }

        if (($payload['event'] ?? null) === 'test') {
            return response()->json(['ok' => true]);
        }

        $prefix = preg_quote(strtoupper((string) config('services.beepay.payment_prefix', 'DH')), '/');
        $orderCode = strtoupper(trim((string) ($payload['order_id'] ?? $payload['reference'] ?? '')));
        if (! preg_match('/^'.$prefix.'(\d{8})$/', $orderCode, $matches)) {
            return response()->json(['ok' => false, 'error' => 'Unknown payment reference.'], 422);
        }

        if (($payload['transaction_type'] ?? 'credit') !== 'credit') {
            return response()->json(['ok' => true, 'ignored' => true]);
        }

        $transactionReference = trim((string) ($payload['transaction_ref'] ?? $payload['tx_id'] ?? $payload['transaction_id'] ?? ''));
        $receivedAmount = filter_var($payload['amount'] ?? null, FILTER_VALIDATE_FLOAT);
        if ($transactionReference === '' || $receivedAmount === false || $receivedAmount <= 0) {
            return response()->json(['ok' => false, 'error' => 'Incomplete transaction data.'], 422);
        }

        $receivedAccount = preg_replace('/\D+/', '', (string) ($payload['account_number'] ?? ''));
        if ($receivedAccount === '' || ! hash_equals($configuredAccount, $receivedAccount)) {
            return response()->json(['ok' => false, 'error' => 'Transaction was sent to another account.'], 422);
        }

        try {
            DB::transaction(function () use ($matches, $transactionReference, $receivedAmount, $payload): void {
                $order = DonHang::query()->with('booking')->lockForUpdate()->findOrFail((int) $matches[1]);
                if ($order->booking?->PhuongThucThanhToan !== 'Chuyển khoản') {
                    throw new \DomainException('This order was not configured for bank transfer.');
                }
                $invoice = HoaDon::query()->where('DonHangID', $order->DonHangID)->lockForUpdate()->first();

                if (! $invoice || InvoiceStatus::parse($invoice->TrangThai) !== InvoiceStatus::Unpaid) {
                    if ($invoice && InvoiceStatus::parse($invoice->TrangThai) === InvoiceStatus::Paid) {
                        return;
                    }
                    throw new \DomainException('No unpaid invoice is available for this order.');
                }

                if ($order->statusEnum() === OrderStatus::Cancelled) {
                    throw new \DomainException('Payment is not available for a cancelled order.');
                }

                if (round((float) $receivedAmount, 2) !== round((float) $invoice->ThanhTien, 2)) {
                    throw new \DomainException('The received amount does not match the invoice.');
                }

                $existingTransaction = ThanhToan::query()
                    ->where('MaGiaoDich', $transactionReference)
                    ->first();
                if ($existingTransaction) {
                    if ((int) $existingTransaction->DonHangID === (int) $order->DonHangID) {
                        return;
                    }
                    throw new \DomainException('The bank transaction reference was already used.');
                }

                $pendingPayment = ThanhToan::query()
                    ->where('DonHangID', $order->DonHangID)
                    ->where('TrangThai', PaymentStatus::Pending->value)
                    ->orderByDesc('ThoiGian')
                    ->lockForUpdate()
                    ->first();

                $attributes = [
                    'amount' => (float) $invoice->ThanhTien,
                    'method' => 'bank_transfer',
                    'transaction_code' => $transactionReference,
                    'paid_at' => now(),
                    'status' => PaymentStatus::Paid->value,
                    'notes' => 'BeePay: '.mb_substr((string) ($payload['description'] ?? 'Bank transfer confirmed'), 0, 400),
                ];

                if ($pendingPayment) {
                    $this->paymentService->update($pendingPayment, $attributes);
                } else {
                    $this->paymentService->create([
                        ...$attributes,
                        'order_id' => $order->DonHangID,
                        'invoice_id' => $invoice->HoaDonID,
                    ]);
                }

                // Keep active laundry/delivery states intact. The legacy paid order
                // state is only used once an already-delivered order is settled.
                if ($order->statusEnum() === OrderStatus::Delivered) {
                    $order->update([
                        'TrangThai' => OrderStatus::Paid->value,
                        'NgayCapNhat' => now(),
                    ]);
                }
            });
        } catch (\DomainException $exception) {
            Log::warning('BeePay transaction rejected.', [
                'order_id' => $orderCode,
                'transaction_ref' => $transactionReference,
                'reason' => $exception->getMessage(),
            ]);

            return response()->json(['ok' => false, 'error' => $exception->getMessage()], 422);
        } catch (\Throwable $exception) {
            Log::error('BeePay webhook processing failed.', [
                'order_id' => $orderCode,
                'transaction_ref' => $transactionReference,
                'exception' => $exception,
            ]);

            return response()->json(['ok' => false, 'error' => 'Could not process payment.'], 500);
        }

        return response()->json(['ok' => true]);
    }
}
